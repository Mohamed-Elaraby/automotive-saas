<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Admin\AdminActivityLogger;
use App\Services\Admin\AdminSubscriptionControlService;
use App\Services\Billing\BillingNotificationService;
use App\Services\Billing\StripeInvoiceHistoryService;
use App\Services\Billing\StripeInvoiceLedgerBackfillService;
use App\Services\Billing\StripeSubscriptionManagementService;
use App\Services\Billing\StripeSubscriptionPlanChangeService;
use App\Services\Billing\StripeSubscriptionSyncService;
use App\Services\Billing\SubscriptionLifecycleNormalizationService;
use App\Services\Billing\TenantBillingLifecycleService;
use App\Support\Billing\SubscriptionStatuses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class SubscriptionController extends Controller
{
    public function __construct(
        protected AdminSubscriptionControlService $adminSubscriptionControlService,
        protected AdminActivityLogger $activityLogger,
        protected StripeInvoiceHistoryService $stripeInvoiceHistoryService,
        protected StripeSubscriptionSyncService $stripeSubscriptionSyncService,
        protected StripeSubscriptionManagementService $stripeSubscriptionManagementService,
        protected StripeSubscriptionPlanChangeService $stripeSubscriptionPlanChangeService,
        protected StripeInvoiceLedgerBackfillService $stripeInvoiceLedgerBackfillService,
        protected TenantBillingLifecycleService $tenantBillingLifecycleService,
        protected SubscriptionLifecycleNormalizationService $subscriptionLifecycleNormalizationService,
        protected BillingNotificationService $billingNotificationService
    ) {
    }

public function index(Request $request): View
{
    $connection = $this->centralConnection();

    $filters = [
        'tenant_id' => trim((string) $request->string('tenant_id')),
        'status' => trim((string) $request->string('status')),
        'plan_id' => $request->filled('plan_id') ? (int) $request->input('plan_id') : null,
        'gateway' => trim((string) $request->string('gateway')),
    ];

    $baseQuery = DB::connection($connection)
        ->table('subscriptions')
        ->leftJoin('plans', 'plans.id', '=', 'subscriptions.plan_id')
        ->select([
            'subscriptions.id',
            'subscriptions.tenant_id',
            'subscriptions.plan_id',
            'subscriptions.status',
            'subscriptions.trial_ends_at',
            'subscriptions.grace_ends_at',
            'subscriptions.last_payment_failed_at',
            'subscriptions.past_due_started_at',
            'subscriptions.suspended_at',
            'subscriptions.cancelled_at',
            'subscriptions.ends_at',
            'subscriptions.payment_failures_count',
            'subscriptions.gateway',
            'subscriptions.gateway_customer_id',
            'subscriptions.gateway_subscription_id',
            'subscriptions.gateway_checkout_session_id',
            'subscriptions.gateway_price_id',
            'subscriptions.created_at',
            'subscriptions.updated_at',
            'plans.name as plan_name',
            'plans.slug as plan_slug',
            'plans.billing_period as plan_billing_period',
            'plans.price as plan_price',
            'plans.currency as plan_currency',
        ]);

    $this->applyFilters($baseQuery, $filters);

    $subscriptions = $baseQuery
        ->orderByDesc('subscriptions.id')
        ->paginate(20)
        ->withQueryString();

    $statusCounts = $this->buildStatusCounts($connection);

    $plans = Plan::query()
        ->orderBy('sort_order')
        ->orderBy('name')
        ->get(['id', 'name', 'slug']);

    $gatewayOptions = DB::connection($connection)
        ->table('subscriptions')
        ->select('gateway')
        ->whereNotNull('gateway')
        ->where('gateway', '!=', '')
        ->distinct()
        ->orderBy('gateway')
        ->pluck('gateway');

    return view('admin.subscriptions.index', [
        'subscriptions' => $subscriptions,
        'filters' => $filters,
        'statusCounts' => $statusCounts,
        'plans' => $plans,
        'gatewayOptions' => $gatewayOptions,
        'statusOptions' => [
            SubscriptionStatuses::TRIALING,
            SubscriptionStatuses::ACTIVE,
            SubscriptionStatuses::PAST_DUE,
            SubscriptionStatuses::SUSPENDED,
            SubscriptionStatuses::CANCELLED,
            SubscriptionStatuses::EXPIRED,
        ],
    ]);
}

    public function show(int $subscriptionId): View
{
    $subscription = $this->loadSubscriptionRecord($subscriptionId);

    abort_unless($subscription, 404);

    $invoiceHistory = $this->loadInvoiceHistoryForSubscription($subscription);
    $resolvedState = $this->tenantBillingLifecycleService->resolveState($subscription);
    $normalizationPreview = $this->subscriptionLifecycleNormalizationService->normalizeOne($subscriptionId, false);

    return view('admin.subscriptions.show', [
        'subscription' => $subscription,
        'invoiceHistory' => $invoiceHistory,
        'resolvedState' => $resolvedState,
        'normalizationPreview' => $normalizationPreview,
        'statusOptions' => [
            SubscriptionStatuses::TRIALING,
            SubscriptionStatuses::ACTIVE,
            SubscriptionStatuses::PAST_DUE,
            SubscriptionStatuses::SUSPENDED,
            SubscriptionStatuses::CANCELLED,
            SubscriptionStatuses::EXPIRED,
        ],
        'stripePlanOptions' => $this->stripePlanOptions($subscription),
        'isStripeLinked' => $this->isStripeLinkedRecord($subscription),
        'canResumeOnStripe' => $this->canResumeOnStripeRecord($subscription),
        'stripeLinkDiagnostics' => $this->stripeLinkDiagnostics($subscription),
    ]);
}

public function syncFromStripe(Request $request, int $subscriptionId): RedirectResponse
{
    $subscription = Subscription::query()->find($subscriptionId);

    if (! $subscription) {
        return $this->redirectAfterAction($request, $subscriptionId)
            ->with('error', 'The subscription record was not found.');
    }

    if (($subscription->gateway ?? null) !== 'stripe') {
        return $this->redirectAfterAction($request, $subscriptionId)
            ->with('error', 'This subscription is not linked to the Stripe gateway.');
    }

    if (! $subscription->gateway_subscription_id) {
        return $this->redirectAfterAction($request, $subscriptionId)
            ->with('error', 'No Stripe subscription ID is linked to this subscription.');
    }

    try {
        $synced = $this->stripeSubscriptionSyncService->syncByGatewaySubscriptionId(
            (string) $subscription->gateway_subscription_id
        );

        if (! $synced) {
            return redirect()
                ->to($this->redirectAfterAction($request, $subscriptionId)->getTargetUrl())
                ->with('error', 'No local subscription could be matched for the Stripe subscription ID.');
        }

        $subscription->refresh();

        $this->billingNotificationService->manualSync($subscription->fresh(), [
            'source' => 'admin.sync_from_stripe',
            'gateway_subscription_id' => $subscription->gateway_subscription_id,
        ]);

        return redirect()
            ->to($this->redirectAfterAction($request, $subscriptionId)->getTargetUrl())
            ->with('success', 'Subscription data was synced successfully from Stripe.');
    } catch (Throwable $e) {
        report($e);

        return redirect()
            ->to($this->redirectAfterAction($request, $subscriptionId)->getTargetUrl())
            ->with('error', 'Unable to sync the subscription from Stripe right now.');
    }
}

public function backfillInvoices(Request $request, int $subscriptionId): RedirectResponse
{
    $subscription = Subscription::query()->find($subscriptionId);

    if (! $subscription) {
        return $this->redirectAfterAction($request, $subscriptionId)
            ->with('error', 'The subscription record was not found.');
    }

    if (($subscription->gateway ?? null) !== 'stripe') {
        return $this->redirectAfterAction($request, $subscriptionId)
            ->with('error', 'This subscription is not linked to the Stripe gateway.');
    }

    if (! $subscription->gateway_customer_id) {
        return $this->redirectAfterAction($request, $subscriptionId)
            ->with('error', 'No Stripe customer ID is linked to this subscription.');
    }

    try {
        $result = $this->stripeInvoiceLedgerBackfillService->backfillForSubscription($subscription, 100);

        return redirect()
            ->to($this->redirectAfterAction($request, $subscriptionId)->getTargetUrl())
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    } catch (Throwable $e) {
        report($e);

        return redirect()
            ->to($this->redirectAfterAction($request, $subscriptionId)->getTargetUrl())
            ->with('error', 'Unable to backfill Stripe invoices for this subscription right now.');
    }
}

public function refreshState(Request $request, int $subscriptionId): RedirectResponse
{
    $subscription = Subscription::query()->find($subscriptionId);

    if (! $subscription) {
        return $this->redirectAfterAction($request, $subscriptionId)
            ->with('error', 'The subscription record was not found.');
    }

    $resolvedState = $this->tenantBillingLifecycleService->resolveState($subscription);

    $this->billingNotificationService->manualRefreshState($subscription->fresh(), [
        'source' => 'admin.refresh_state',
        'resolved_state' => $resolvedState,
    ]);

    return redirect()
        ->to($this->redirectAfterAction($request, $subscriptionId)->getTargetUrl())
        ->with(
            'success',
            'Local billing state was refreshed. Current resolved status: ' .
            ucfirst(str_replace('_', ' ', (string) ($resolvedState['status'] ?? 'unknown')))
        );
}

public function normalizeLifecycle(Request $request, int $subscriptionId): RedirectResponse
{
    $subscription = Subscription::query()->find($subscriptionId);

    if (! $subscription) {
        return $this->redirectAfterAction($request, $subscriptionId)
            ->with('error', 'The subscription record was not found.');
    }

    $result = $this->subscriptionLifecycleNormalizationService->normalizeOne($subscriptionId, true);

    if (! ($result['ok'] ?? false)) {
        return $this->redirectAfterAction($request, $subscriptionId)
            ->with('error', $result['message'] ?? 'Unable to normalize lifecycle fields.');
    }

    $this->billingNotificationService->manualNormalizeLifecycle(
        $subscription->fresh(),
        (bool) ($result['applied'] ?? false),
        [
            'source' => 'admin.normalize_lifecycle',
            'normalization_result' => $result,
        ]
    );

    return redirect()
        ->to($this->redirectAfterAction($request, $subscriptionId)->getTargetUrl())
        ->with('success', $result['message'] ?? 'Lifecycle fields were normalized successfully.');
}

public function manualAction(Request $request, int $subscriptionId): RedirectResponse
{
    $validated = $request->validate([
        'action' => ['required', 'string', 'in:force_lifecycle,cancel,resume,renew'],
        'target_status' => ['required_if:action,force_lifecycle', 'nullable', 'string', 'in:' . implode(',', [
            SubscriptionStatuses::TRIALING,
            SubscriptionStatuses::ACTIVE,
            SubscriptionStatuses::PAST_DUE,
            SubscriptionStatuses::SUSPENDED,
            SubscriptionStatuses::CANCELLED,
            SubscriptionStatuses::EXPIRED,
        ])],
        'redirect_to' => ['nullable', 'string', 'in:index,show'],
    ]);

    try {
        $result = $validated['action'] === 'force_lifecycle'
            ? $this->adminSubscriptionControlService->forceLifecycle(
                $subscriptionId,
                (string) $validated['target_status']
            )
            : $this->adminSubscriptionControlService->applyQuickAction(
                $subscriptionId,
                (string) $validated['action']
            );

        /** @var Subscription|null $after */
        $after = $result['after'] ?? null;

        if ($after) {
            $this->billingNotificationService->manualLifecycleChange($after, [
                'source' => 'admin.manual_subscription_action',
                'action' => $result['action'] ?? $validated['action'],
                'target_status' => $result['target_status'] ?? $after->status,
                'before' => $this->snapshot($result['before'] ?? null),
                'after' => $this->snapshot($after),
            ]);

            $this->activityLogger->log(
                action: 'subscription.manual_action',
                subjectType: 'subscription',
                subjectId: $after->id,
                tenantId: (string) $after->tenant_id,
                contextPayload: [
                    'action' => $result['action'] ?? $validated['action'],
                    'target_status' => $result['target_status'] ?? $after->status,
                    'before' => $this->snapshot($result['before'] ?? null),
                    'after' => $this->snapshot($after),
                ]
            );
        }

        return redirect()
            ->to($this->redirectAfterAction($request, $subscriptionId)->getTargetUrl())
            ->with('success', $result['message'] ?? 'Subscription action completed successfully.');
    } catch (Throwable $e) {
        report($e);

        return redirect()
            ->to($this->redirectAfterAction($request, $subscriptionId)->getTargetUrl())
            ->with('error', $e->getMessage() ?: 'Unable to apply the subscription action right now.');
    }
}

public function updateTimestamps(Request $request, int $subscriptionId): RedirectResponse
{
    $validated = $request->validate([
        'trial_ends_at' => ['nullable', 'date'],
        'grace_ends_at' => ['nullable', 'date'],
        'ends_at' => ['nullable', 'date'],
    ]);

    try {
        $result = $this->adminSubscriptionControlService->updateTimestamps($subscriptionId, $validated);

        /** @var Subscription|null $after */
        $after = $result['after'] ?? null;

        if ($after) {
            $this->billingNotificationService->manualTimestampUpdate($after, [
                'source' => 'admin.update_subscription_timestamps',
                'before' => $this->snapshot($result['before'] ?? null),
                'after' => $this->snapshot($after),
            ]);

            $this->activityLogger->log(
                action: 'subscription.timestamps.updated',
                subjectType: 'subscription',
                subjectId: $after->id,
                tenantId: (string) $after->tenant_id,
                contextPayload: [
                    'before' => $this->snapshot($result['before'] ?? null),
                    'after' => $this->snapshot($after),
                ]
            );
        }

        return redirect()
            ->route('admin.subscriptions.show', $subscriptionId)
            ->with('success', $result['message'] ?? 'Subscription timestamps were updated successfully.');
    } catch (Throwable $e) {
        report($e);

        return redirect()
            ->route('admin.subscriptions.show', $subscriptionId)
            ->with('error', $e->getMessage() ?: 'Unable to update subscription timestamps right now.');
    }
}

public function cancelOnStripe(Request $request, int $subscriptionId): RedirectResponse
{
    $subscription = Subscription::query()->find($subscriptionId);

    if (! $subscription) {
        return $this->redirectAfterAction($request, $subscriptionId)
            ->with('error', 'The subscription record was not found.');
    }

    $result = $this->stripeSubscriptionManagementService->cancelAtPeriodEnd($subscription);
    $fresh = $subscription->fresh();

    if (($result['success'] ?? false) && $fresh) {
        $this->activityLogger->log(
            action: 'subscription.stripe_cancel_scheduled',
            subjectType: 'subscription',
            subjectId: $fresh->id,
            tenantId: (string) $fresh->tenant_id,
            contextPayload: [
                'source' => 'admin.cancel_on_stripe',
                'result' => $result,
                'after' => $this->snapshot($fresh),
            ]
        );
    }

    return redirect()
        ->to($this->redirectAfterAction($request, $subscriptionId)->getTargetUrl())
        ->with(($result['success'] ?? false) ? 'success' : 'error', $result['message'] ?? 'Unable to update Stripe subscription.');
}

public function resumeOnStripe(Request $request, int $subscriptionId): RedirectResponse
{
    $subscription = Subscription::query()->find($subscriptionId);

    if (! $subscription) {
        return $this->redirectAfterAction($request, $subscriptionId)
            ->with('error', 'The subscription record was not found.');
    }

    $result = $this->stripeSubscriptionManagementService->resume($subscription);
    $fresh = $subscription->fresh();

    if (($result['success'] ?? false) && $fresh) {
        $this->activityLogger->log(
            action: 'subscription.stripe_resumed',
            subjectType: 'subscription',
            subjectId: $fresh->id,
            tenantId: (string) $fresh->tenant_id,
            contextPayload: [
                'source' => 'admin.resume_on_stripe',
                'result' => $result,
                'after' => $this->snapshot($fresh),
            ]
        );
    }

    return redirect()
        ->to($this->redirectAfterAction($request, $subscriptionId)->getTargetUrl())
        ->with(($result['success'] ?? false) ? 'success' : 'error', $result['message'] ?? 'Unable to resume Stripe subscription.');
}

public function cancelImmediatelyOnStripe(Request $request, int $subscriptionId): RedirectResponse
{
    $subscription = Subscription::query()->find($subscriptionId);

    if (! $subscription) {
        return $this->redirectAfterAction($request, $subscriptionId)
            ->with('error', 'The subscription record was not found.');
    }

    $result = $this->stripeSubscriptionManagementService->cancelImmediately($subscription);
    $fresh = $subscription->fresh();

    if (($result['success'] ?? false) && $fresh) {
        $this->activityLogger->log(
            action: 'subscription.stripe_cancelled_immediately',
            subjectType: 'subscription',
            subjectId: $fresh->id,
            tenantId: (string) $fresh->tenant_id,
            contextPayload: [
                'source' => 'admin.cancel_immediately_on_stripe',
                'result' => $result,
                'after' => $this->snapshot($fresh),
            ]
        );
    }

    return redirect()
        ->to($this->redirectAfterAction($request, $subscriptionId)->getTargetUrl())
        ->with(($result['success'] ?? false) ? 'success' : 'error', $result['message'] ?? 'Unable to cancel Stripe subscription immediately.');
}

public function changePlanOnStripe(Request $request, int $subscriptionId): RedirectResponse
{
    $validated = $request->validate([
        'target_plan_id' => ['required', 'integer'],
    ]);

    $subscription = Subscription::query()->find($subscriptionId);

    if (! $subscription) {
        return $this->redirectAfterAction($request, $subscriptionId)
            ->with('error', 'The subscription record was not found.');
    }

    $targetPlan = Plan::query()->find((int) $validated['target_plan_id']);

    if (! $targetPlan) {
        return $this->redirectAfterAction($request, $subscriptionId)
            ->with('error', 'The selected plan record was not found.');
    }

    $result = $this->stripeSubscriptionPlanChangeService->changePlan($subscription, $targetPlan);
    $fresh = $subscription->fresh();

    if (($result['ok'] ?? false) && $fresh) {
        $this->activityLogger->log(
            action: 'subscription.stripe_plan_changed',
            subjectType: 'subscription',
            subjectId: $fresh->id,
            tenantId: (string) $fresh->tenant_id,
            contextPayload: [
                'source' => 'admin.change_plan_on_stripe',
                'target_plan_id' => $targetPlan->id,
                'result' => $result,
                'after' => $this->snapshot($fresh),
            ]
        );
    }

    return redirect()
        ->to($this->redirectAfterAction($request, $subscriptionId)->getTargetUrl())
        ->with(($result['ok'] ?? false) ? 'success' : 'error', $result['message'] ?? 'Unable to change plan on Stripe.');
}

protected function loadSubscriptionRecord(int $subscriptionId): ?object
{
    return DB::connection($this->centralConnection())
        ->table('subscriptions')
        ->leftJoin('plans', 'plans.id', '=', 'subscriptions.plan_id')
        ->select([
            'subscriptions.id',
            'subscriptions.tenant_id',
            'subscriptions.plan_id',
            'subscriptions.status',
            'subscriptions.trial_ends_at',
            'subscriptions.grace_ends_at',
            'subscriptions.last_payment_failed_at',
            'subscriptions.past_due_started_at',
            'subscriptions.suspended_at',
            'subscriptions.cancelled_at',
            'subscriptions.ends_at',
            'subscriptions.payment_failures_count',
            'subscriptions.gateway',
            'subscriptions.gateway_customer_id',
            'subscriptions.gateway_subscription_id',
            'subscriptions.gateway_checkout_session_id',
            'subscriptions.gateway_price_id',
            'subscriptions.created_at',
            'subscriptions.updated_at',
            'plans.name as plan_name',
            'plans.slug as plan_slug',
            'plans.billing_period as plan_billing_period',
            'plans.price as plan_price',
            'plans.currency as plan_currency',
        ])
        ->where('subscriptions.id', $subscriptionId)
        ->first();
}

protected function loadInvoiceHistoryForSubscription(object $subscription): array
{
    $invoiceHistory = [
        'ok' => true,
        'invoices' => [],
        'message' => null,
    ];

    if (($subscription->gateway ?? null) === 'stripe' && ! empty($subscription->gateway_customer_id)) {
        $invoiceHistory = $this->stripeInvoiceHistoryService->listCustomerInvoices(
            (string) $subscription->gateway_customer_id,
            15
        );

        if (! empty($subscription->gateway_subscription_id) && ! empty($invoiceHistory['invoices'])) {
            $invoiceHistory['invoices'] = collect($invoiceHistory['invoices'])
                ->filter(function (array $invoice) use ($subscription) {
                    $invoiceSubscriptionId = (string) ($invoice['subscription_id'] ?? '');

                    return $invoiceSubscriptionId === ''
                        || $invoiceSubscriptionId === (string) $subscription->gateway_subscription_id;
                })
                ->values()
                ->all();
        }
    }

    return $invoiceHistory;
}

protected function applyFilters(object $query, array $filters): void
{
    if ($filters['tenant_id'] !== '') {
        $query->where('subscriptions.tenant_id', 'like', '%' . $filters['tenant_id'] . '%');
    }

    if ($filters['status'] !== '') {
        $query->where('subscriptions.status', $filters['status']);
    }

    if (! empty($filters['plan_id'])) {
        $query->where('subscriptions.plan_id', $filters['plan_id']);
    }

    if ($filters['gateway'] !== '') {
        $query->where('subscriptions.gateway', $filters['gateway']);
    }
}

protected function buildStatusCounts(string $connection): array
{
    $rows = DB::connection($connection)
        ->table('subscriptions')
        ->select('status', DB::raw('COUNT(*) as aggregate_count'))
        ->groupBy('status')
        ->pluck('aggregate_count', 'status');

    return [
        'total' => (int) DB::connection($connection)->table('subscriptions')->count(),
        SubscriptionStatuses::ACTIVE => (int) ($rows[SubscriptionStatuses::ACTIVE] ?? 0),
        SubscriptionStatuses::TRIALING => (int) ($rows[SubscriptionStatuses::TRIALING] ?? 0),
        SubscriptionStatuses::PAST_DUE => (int) ($rows[SubscriptionStatuses::PAST_DUE] ?? 0),
        SubscriptionStatuses::SUSPENDED => (int) ($rows[SubscriptionStatuses::SUSPENDED] ?? 0),
        SubscriptionStatuses::CANCELLED => (int) ($rows[SubscriptionStatuses::CANCELLED] ?? 0),
        SubscriptionStatuses::EXPIRED => (int) ($rows[SubscriptionStatuses::EXPIRED] ?? 0),
    ];
}

protected function centralConnection(): string
{
    return (string) (config('tenancy.database.central_connection') ?? config('database.default'));
}

protected function snapshot(?Subscription $subscription): array
{
    if (! $subscription) {
        return [];
    }

    return [
        'id' => $subscription->id,
        'tenant_id' => $subscription->tenant_id,
        'status' => $subscription->status,
        'trial_ends_at' => optional($subscription->trial_ends_at)->format('Y-m-d H:i:s'),
        'grace_ends_at' => optional($subscription->grace_ends_at)->format('Y-m-d H:i:s'),
        'past_due_started_at' => optional($subscription->past_due_started_at)->format('Y-m-d H:i:s'),
        'last_payment_failed_at' => optional($subscription->last_payment_failed_at)->format('Y-m-d H:i:s'),
        'suspended_at' => optional($subscription->suspended_at)->format('Y-m-d H:i:s'),
        'cancelled_at' => optional($subscription->cancelled_at)->format('Y-m-d H:i:s'),
        'ends_at' => optional($subscription->ends_at)->format('Y-m-d H:i:s'),
        'payment_failures_count' => (int) ($subscription->payment_failures_count ?? 0),
        'gateway' => $subscription->gateway,
    ];
}

protected function isStripeLinkedRecord(object $subscription): bool
{
    return ! empty($subscription->gateway_subscription_id);
}

protected function stripeLinkDiagnostics(object $subscription): array
{
    $hasGateway = ! empty($subscription->gateway);
    $hasCustomerId = ! empty($subscription->gateway_customer_id);
    $hasSubscriptionId = ! empty($subscription->gateway_subscription_id);
    $hasCheckoutSessionId = ! empty($subscription->gateway_checkout_session_id);

    return [
        'is_blocked' => $hasSubscriptionId,
        'reason' => $hasSubscriptionId
            ? 'Manual local controls are blocked because a live Stripe subscription ID is linked to this record.'
            : 'Manual local controls are allowed because no Stripe subscription ID is linked to this record.',
        'signals' => [
            'gateway' => $hasGateway ? (string) $subscription->gateway : null,
            'has_gateway_customer_id' => $hasCustomerId,
            'has_gateway_subscription_id' => $hasSubscriptionId,
            'has_gateway_checkout_session_id' => $hasCheckoutSessionId,
        ],
    ];
}

protected function stripePlanOptions(object $subscription): \Illuminate\Support\Collection
{
    if (! $this->isStripeLinkedRecord($subscription)) {
        return collect();
    }

    return Plan::query()
        ->where('is_active', true)
        ->where('billing_period', '!=', 'trial')
        ->whereNotNull('stripe_price_id')
        ->orderBy('sort_order')
        ->orderBy('name')
        ->get(['id', 'name', 'slug', 'billing_period', 'price', 'currency', 'stripe_price_id']);
}

protected function canResumeOnStripeRecord(object $subscription): bool
{
    if (! $this->isStripeLinkedRecord($subscription)) {
        return false;
    }

    $status = (string) ($subscription->status ?? '');

    if ($status === SubscriptionStatuses::ACTIVE) {
        return true;
    }

    if ($status !== SubscriptionStatuses::CANCELLED) {
        return false;
    }

    if (empty($subscription->ends_at)) {
        return false;
    }

    return now()->lt(\Carbon\Carbon::parse((string) $subscription->ends_at));
}

protected function redirectAfterAction(Request $request, int $subscriptionId): RedirectResponse
{
    if ($request->input('redirect_to') === 'index') {
        return redirect()->route('admin.subscriptions.index');
    }

    return redirect()->route('admin.subscriptions.show', $subscriptionId);
}

}
