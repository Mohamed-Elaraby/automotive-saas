<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\TenantProductSubscription;
use Illuminate\Support\Collection;
use Throwable;

class StripeSubscriptionConsistencyReviewService
{
    public function __construct(
        protected StripeSubscriptionSyncService $stripeSubscriptionSyncService,
        protected LocalBillingInvoiceService $localBillingInvoiceService
    ) {
    }

    public function review(
        bool $applySync = false,
        ?string $tenantId = null,
        ?int $subscriptionId = null,
        int $limit = 100
    ): Collection {
        $query = Subscription::query()
            ->with('plan')
            ->where(function ($builder) {
                $builder
                    ->where('gateway', 'stripe')
                    ->orWhereNotNull('gateway_subscription_id')
                    ->orWhereNotNull('gateway_checkout_session_id');
            })
            ->orderBy('id');

        if ($tenantId !== null && $tenantId !== '') {
            $query->where('tenant_id', $tenantId);
        }

        if ($subscriptionId !== null) {
            $query->whereKey($subscriptionId);
        }

        return $query
            ->limit(max(1, min($limit, 500)))
            ->get()
            ->map(fn (Subscription $subscription) => $this->reviewOne($subscription, $applySync));
    }

    protected function reviewOne(Subscription $subscription, bool $applySync): array
    {
        $before = Subscription::query()->with('plan')->findOrFail($subscription->id);
        $beforeMirror = $this->loadMirror($before);
        $beforeIssues = $this->detectIssues($before, $beforeMirror);

        $after = $before;
        $syncState = 'SKIPPED';
        $syncMessage = null;

        if ($applySync && ($before->gateway ?? null) === 'stripe') {
            try {
                $synced = $this->stripeSubscriptionSyncService->syncLocalStripeSubscription($before);

                if ($synced) {
                    $after = Subscription::query()->with('plan')->findOrFail($synced->id);
                    $syncState = 'SYNCED';
                } else {
                    $syncState = 'NO_RESULT';
                    $syncMessage = 'Stripe sync returned no subscription update.';
                }
            } catch (Throwable $e) {
                $syncState = 'FAILED';
                $syncMessage = $e->getMessage();
            }
        }

        $afterMirror = $this->loadMirror($after);
        $afterIssues = $this->detectIssues($after, $afterMirror);

        $subscriptionInvoiceCount = 0;
        if (filled($after->gateway_subscription_id)) {
            $subscriptionInvoiceCount = count(
                $this->localBillingInvoiceService
                    ->getSubscriptionInvoiceHistory((string) $after->gateway_subscription_id, 100)['invoices']
            );
        }

        $customerInvoices = [];
        if (filled($after->gateway_customer_id)) {
            $customerInvoices = $this->localBillingInvoiceService
                ->getCustomerInvoiceHistory((string) $after->gateway_customer_id, 100)['invoices'];
        }

        $mixedCustomerInvoiceCount = collect($customerInvoices)
            ->filter(function (array $invoice) use ($after) {
                $invoiceSubscriptionId = (string) ($invoice['subscription_id'] ?? '');

                return $invoiceSubscriptionId !== ''
                    && $invoiceSubscriptionId !== (string) ($after->gateway_subscription_id ?? '');
            })
            ->count();

        if ($mixedCustomerInvoiceCount > 0) {
            $afterIssues[] = 'mixed_customer_invoice_history';
        }

        $result = empty($afterIssues) && $syncState !== 'FAILED'
            ? 'OK'
            : 'NEEDS_REVIEW';

        return [
            'subscription_id' => $after->id,
            'tenant_id' => (string) $after->tenant_id,
            'status_before' => (string) $before->status,
            'status_after' => (string) $after->status,
            'plan_before' => $this->planLabel($before->plan),
            'plan_after' => $this->planLabel($after->plan),
            'gateway_subscription_id_before' => (string) ($before->gateway_subscription_id ?? ''),
            'gateway_subscription_id_after' => (string) ($after->gateway_subscription_id ?? ''),
            'gateway_price_id_before' => (string) ($before->gateway_price_id ?? ''),
            'gateway_price_id_after' => (string) ($after->gateway_price_id ?? ''),
            'sync_state' => $syncState,
            'sync_message' => $syncMessage,
            'mirror_status' => $this->mirrorStatus($after, $afterMirror),
            'subscription_invoice_count' => $subscriptionInvoiceCount,
            'mixed_customer_invoice_count' => $mixedCustomerInvoiceCount,
            'issues_before' => $this->implodeIssues($beforeIssues),
            'issues_after' => $this->implodeIssues($afterIssues),
            'result' => $result,
        ];
    }

    protected function detectIssues(Subscription $subscription, ?TenantProductSubscription $mirror): array
    {
        $issues = [];

        if (($subscription->gateway ?? null) === 'stripe' && blank($subscription->gateway_customer_id)) {
            $issues[] = 'missing_gateway_customer_id';
        }

        if (($subscription->gateway ?? null) === 'stripe' && blank($subscription->gateway_subscription_id)) {
            $issues[] = filled($subscription->gateway_checkout_session_id)
                ? 'recoverable_missing_gateway_subscription_id'
                : 'missing_gateway_subscription_id';
        }

        $plan = $subscription->plan;
        if ($plan && filled($plan->stripe_price_id) && filled($subscription->gateway_price_id)) {
            if ((string) $plan->stripe_price_id !== (string) $subscription->gateway_price_id) {
                $issues[] = 'local_plan_price_mismatch';
            }
        }

        if ($plan?->product_id) {
            if (! $mirror) {
                $issues[] = 'missing_product_subscription_mirror';
            } elseif (! $this->mirrorMatches($subscription, $mirror)) {
                $issues[] = 'product_subscription_mirror_mismatch';
            }
        }

        return array_values(array_unique($issues));
    }

    protected function loadMirror(Subscription $subscription): ?TenantProductSubscription
    {
        return TenantProductSubscription::query()
            ->where('legacy_subscription_id', $subscription->id)
            ->first();
    }

    protected function mirrorMatches(Subscription $subscription, TenantProductSubscription $mirror): bool
    {
        return (int) $mirror->plan_id === (int) $subscription->plan_id
            && (string) $mirror->status === (string) $subscription->status
            && (string) ($mirror->gateway_customer_id ?? '') === (string) ($subscription->gateway_customer_id ?? '')
            && (string) ($mirror->gateway_subscription_id ?? '') === (string) ($subscription->gateway_subscription_id ?? '')
            && (string) ($mirror->gateway_price_id ?? '') === (string) ($subscription->gateway_price_id ?? '');
    }

    protected function mirrorStatus(Subscription $subscription, ?TenantProductSubscription $mirror): string
    {
        if (! $subscription->plan?->product_id) {
            return 'NOT_PRODUCT_AWARE';
        }

        if (! $mirror) {
            return 'MISSING';
        }

        return $this->mirrorMatches($subscription, $mirror) ? 'MATCHED' : 'MISMATCH';
    }

    protected function planLabel(?Plan $plan): string
    {
        if (! $plan) {
            return '-';
        }

        return "{$plan->id}:{$plan->slug}";
    }

    protected function implodeIssues(array $issues): string
    {
        if (empty($issues)) {
            return 'OK';
        }

        return implode(', ', $issues);
    }
}
