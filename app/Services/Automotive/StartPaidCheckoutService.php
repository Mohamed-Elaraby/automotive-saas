<?php

namespace App\Services\Automotive;

use App\Models\CustomerOnboardingProfile;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\BillingPlanCatalogService;
use App\Services\Billing\CheckoutStripePlanRecoveryService;
use App\Services\Billing\PaymentGatewayManager;
use App\Services\Billing\TenantProductSubscriptionSyncService;
use App\Services\Tenancy\WorkspaceProductActivationService;
use App\Support\Billing\SubscriptionStatuses;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StartPaidCheckoutService
{
    public function __construct(
        protected BillingPlanCatalogService $billingPlanCatalogService,
        protected CheckoutStripePlanRecoveryService $checkoutStripePlanRecoveryService,
        protected PaymentGatewayManager $paymentGatewayManager,
        protected TenantProductSubscriptionSyncService $tenantProductSubscriptionSyncService,
        protected WorkspaceProductActivationService $workspaceProductActivationService
    ) {
    }

    public function start(User $user, CustomerOnboardingProfile $profile, int $planId, ?int $productId = null): array
    {
        $product = $this->resolveCheckoutProduct($planId, $productId);
        $plan = $product
            ? $this->checkoutStripePlanRecoveryService->recoverPaidPlan($planId, (string) $product->code)
            : null;
        $reservedTenantId = strtolower(trim((string) $profile->subdomain));

        if (! $plan) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'The selected paid plan was not found or is not active.',
                'errors' => [
                    'plan_id' => ['The selected paid plan was not found or is not active.'],
                ],
            ];
        }

        if (empty($plan->stripe_price_id)) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'The selected paid plan is not linked to a Stripe price yet.',
                'errors' => [
                    'plan_id' => ['The selected paid plan is not linked to a Stripe price yet.'],
                ],
            ];
        }

        $workspace = $this->resolveWorkspaceForUser($user);
        $existingSubscription = $workspace['subscription'];

        if (
            $existingSubscription
            && (string) ($existingSubscription->status ?? '') === SubscriptionStatuses::ACTIVE
            && (string) ($existingSubscription->gateway ?? '') !== ''
            && ! $this->isRestartableTerminalStripeSubscription($existingSubscription)
        ) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'A live paid subscription already exists for this account. Manage billing from inside your system workspace.',
                'errors' => [
                    'portal' => ['A live paid subscription already exists for this account. Manage billing from inside your system workspace.'],
                ],
            ];
        }

        if (
            $existingSubscription
            && $this->hasLiveStripeSubscription($existingSubscription)
        ) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'A live Stripe subscription already exists for this account. Manage billing from inside your system workspace.',
                'errors' => [
                    'portal' => ['A live Stripe subscription already exists for this account. Manage billing from inside your system workspace.'],
                ],
            ];
        }

        if (! empty($workspace['tenant_id'])) {
            $tenantId = (string) $workspace['tenant_id'];
            $subscription = $this->resolveSubscriptionModel(
                $workspace['subscription'],
                $tenantId,
                (int) $plan->id
            );
        } else {
            $tenantId = $reservedTenantId;
            $subscription = null;
        }

        try {
            $session = $this->checkoutStripePlanRecoveryService->retryIfStripePriceNeedsRepair(
                $plan,
                (string) ($product->code ?? ''),
                fn (object $checkoutPlan) => $this->paymentGatewayManager
                    ->driver('stripe')
                    ->createRenewalSession([
                        'tenant_id' => $tenantId,
                        'subscription_row_id' => $subscription->id ?? null,
                        'plan_id' => $checkoutPlan->id,
                        'stripe_price_id' => $checkoutPlan->stripe_price_id,
                        'customer_email' => $user->email,
                        'success_url' => route('automotive.portal.checkout.success', ['product' => $product?->slug]),
                        'cancel_url' => route('automotive.portal.checkout.cancel', ['product' => $product?->slug]),
                        'product_scope' => (string) ($product->code ?? ''),
                        'plan_for_audit' => $this->planAuditPayload($checkoutPlan),
                    ])
            );
        } catch (\Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'status' => 500,
                'message' => 'Unable to start paid checkout right now.',
                'errors' => [
                    'portal' => ['Unable to start paid checkout right now.'],
                ],
            ];
        }

        if (! empty($session['success']) && ! empty($session['checkout_url']) && ! empty($session['session_id'])) {
            if ($subscription) {
                $subscription->fill([
                    'plan_id' => (int) $plan->id,
                    'gateway' => 'stripe',
                    'gateway_checkout_session_id' => (string) $session['session_id'],
                    'gateway_price_id' => (string) $plan->stripe_price_id,
                ]);

                if ($this->isRestartableTerminalStripeSubscription($subscription)) {
                    $subscription->fill([
                        'status' => SubscriptionStatuses::PAST_DUE,
                        'gateway_subscription_id' => null,
                        'cancelled_at' => null,
                        'ends_at' => null,
                    ]);
                }

                $subscription->save();
                $productSubscription = $this->tenantProductSubscriptionSyncService->syncFromLegacySubscription($subscription);

                if ($productSubscription) {
                    $this->workspaceProductActivationService->markProvisioning(
                        $productSubscription,
                        'portal_primary_checkout'
                    );
                }
            }

            return [
                'ok' => true,
                'status' => 201,
                'checkout_url' => (string) $session['checkout_url'],
                'tenant_id' => $tenantId,
                'subscription_id' => $subscription?->id ? (int) $subscription->id : null,
            ];
        }

        return [
            'ok' => false,
            'status' => 422,
            'message' => $session['message'] ?? 'Unable to start the paid checkout session.',
            'errors' => [
                'portal' => [$session['message'] ?? 'Unable to start the paid checkout session.'],
            ],
        ];
    }

    protected function resolveWorkspaceForUser(User $user): array
    {
        $connection = $this->centralConnectionName();

        $tenantLink = DB::connection($connection)
            ->table('tenant_users')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->first();

        if (! $tenantLink) {
            $profile = CustomerOnboardingProfile::query()
                ->where('user_id', $user->id)
                ->first();

            if (! $profile || blank($profile->subdomain)) {
                return [
                    'tenant_id' => null,
                    'subscription' => null,
                ];
            }

            $reservedTenantId = strtolower(trim((string) $profile->subdomain));
            $subscription = DB::connection($connection)
                ->table('subscriptions')
                ->where('tenant_id', $reservedTenantId)
                ->orderByDesc('id')
                ->first();

            return [
                'tenant_id' => $subscription ? $reservedTenantId : null,
                'subscription' => $subscription,
            ];
        }

        $subscription = $this->primaryLegacySubscriptionForTenant((string) $tenantLink->tenant_id, $connection);

        return [
            'tenant_id' => (string) $tenantLink->tenant_id,
            'subscription' => $subscription,
        ];
    }

    protected function resolveSubscriptionModel(?object $subscriptionRow, string $tenantId, int $planId): Subscription
    {
        if ($subscriptionRow && ! empty($subscriptionRow->id)) {
            $subscription = Subscription::query()->find($subscriptionRow->id);

            if ($subscription) {
                return $subscription;
            }
        }

        $subscription = Subscription::query()->create([
            'tenant_id' => $tenantId,
            'plan_id' => $planId,
            'status' => SubscriptionStatuses::PAST_DUE,
            'trial_ends_at' => null,
            'ends_at' => null,
            'external_id' => null,
            'gateway' => null,
            'gateway_customer_id' => null,
            'gateway_subscription_id' => null,
            'gateway_checkout_session_id' => null,
            'gateway_price_id' => null,
        ]);

        $this->tenantProductSubscriptionSyncService->syncFromLegacySubscription($subscription);

        return $subscription;
    }

    protected function centralConnectionName(): string
    {
        return (string) (config('tenancy.database.central_connection') ?? config('database.default'));
    }

    protected function primaryLegacySubscriptionForTenant(string $tenantId, string $connection): ?object
    {
        if (
            $tenantId === ''
            || ! Schema::connection($connection)->hasTable('subscriptions')
            || ! Schema::connection($connection)->hasTable('plans')
        ) {
            return null;
        }

        return DB::connection($connection)
            ->table('subscriptions')
            ->leftJoin('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->leftJoin('products', 'products.id', '=', 'plans.product_id')
            ->where('subscriptions.tenant_id', $tenantId)
            ->where(function ($query) {
                $query->where('products.code', 'automotive_service')
                    ->orWhereNull('plans.product_id');
            })
            ->orderByDesc('subscriptions.id')
            ->select('subscriptions.*')
            ->first();
    }

    protected function resolveCheckoutProduct(int $planId, ?int $productId = null): ?Product
    {
        if ($productId && $productId > 0) {
            return Product::query()
                ->where('id', $productId)
                ->where('is_active', true)
                ->first();
        }

        $plan = \App\Models\Plan::query()
            ->where('id', $planId)
            ->first();

        if (! $plan || empty($plan->product_id)) {
            return null;
        }

        return Product::query()
            ->where('id', $plan->product_id)
            ->where('is_active', true)
            ->first();
    }

    protected function hasLiveStripeSubscription(?object $subscription): bool
    {
        if (! $subscription || ! filled($subscription->gateway_subscription_id ?? null)) {
            return false;
        }

        $status = (string) ($subscription->status ?? '');

        if ($status === SubscriptionStatuses::ACTIVE) {
            return true;
        }

        if ($status !== SubscriptionStatuses::CANCELLED) {
            return false;
        }

        if (blank($subscription->ends_at ?? null)) {
            return false;
        }

        return now()->lt(\Carbon\Carbon::parse((string) $subscription->ends_at));
    }

    protected function isRestartableTerminalStripeSubscription(?object $subscription): bool
    {
        if (! $subscription) {
            return false;
        }

        if ((string) ($subscription->gateway ?? '') !== 'stripe') {
            return false;
        }

        $status = (string) ($subscription->status ?? '');

        if ($status === SubscriptionStatuses::EXPIRED) {
            return true;
        }

        if ($status !== SubscriptionStatuses::CANCELLED) {
            return false;
        }

        if (blank($subscription->ends_at ?? null)) {
            return true;
        }

        return ! now()->lt(\Carbon\Carbon::parse((string) $subscription->ends_at));
    }

    protected function planAuditPayload(object $plan): array
    {
        return [
            'id' => $plan->id ?? null,
            'name' => $plan->name ?? null,
            'slug' => $plan->slug ?? null,
            'price' => isset($plan->price) ? (float) $plan->price : null,
            'currency' => strtoupper((string) ($plan->currency ?? 'USD')),
            'billing_period' => (string) ($plan->billing_period ?? 'monthly'),
            'stripe_price_id' => $plan->stripe_price_id ?? null,
        ];
    }
}
