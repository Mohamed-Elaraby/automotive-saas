<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Admin\SyncTenantProductSubscriptionFromStripeJob;
use App\Models\TenantProductSubscription;
use App\Services\Admin\AdminActivityLogger;
use App\Services\Admin\AdminTenantLifecycleService;
use App\Services\Admin\TenantImpersonationService;
use App\Services\Billing\AdminTenantProductSubscriptionStripeSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TenantController extends Controller
{
    public function __construct(
        protected AdminTenantLifecycleService $lifecycleService,
        protected AdminActivityLogger $activityLogger,
        protected TenantImpersonationService $tenantImpersonationService
    ) {
    }

public function index(Request $request): View
{
    $tenantModelClass = $this->tenantModelClass();

    abort_unless(class_exists($tenantModelClass), 500, 'Configured tenant model class does not exist.');

    /** @var \Illuminate\Database\Eloquent\Builder $query */
    $query = $tenantModelClass::query();

    $filters = [
        'q' => trim((string) $request->input('q', '')),
        'status' => trim((string) $request->input('status', '')),
        'plan_id' => trim((string) $request->input('plan_id', '')),
        'gateway' => trim((string) $request->input('gateway', '')),
        'has_domain' => trim((string) $request->input('has_domain', '')),
        'created_from' => trim((string) $request->input('created_from', '')),
        'created_to' => trim((string) $request->input('created_to', '')),
    ];

    $matchingIdsFromDomain = [];
    $matchingIdsFromOwnerOrCompany = [];
    if ($filters['q'] !== '') {
        $matchingIdsFromDomain = $this->matchingTenantIdsByDomain($filters['q']);
        $matchingIdsFromOwnerOrCompany = $this->matchingTenantIdsByTenantData($filters['q']);

        $query->where(function ($builder) use ($filters, $matchingIdsFromDomain, $matchingIdsFromOwnerOrCompany) {
            $builder->where('id', 'like', '%' . $filters['q'] . '%');

            if (! empty($matchingIdsFromDomain)) {
                $builder->orWhereIn('id', $matchingIdsFromDomain);
            }

            if (! empty($matchingIdsFromOwnerOrCompany)) {
                $builder->orWhereIn('id', $matchingIdsFromOwnerOrCompany);
            }
        });
    }

    if ($filters['status'] !== '') {
        $matchingIdsFromStatus = $this->matchingTenantIdsBySubscriptionStatus($filters['status']);

        if (empty($matchingIdsFromStatus)) {
            $query->whereRaw('1 = 0');
        } else {
            $query->whereIn('id', $matchingIdsFromStatus);
        }
    }

    if ($filters['plan_id'] !== '') {
        $matchingIdsFromPlan = $this->matchingTenantIdsByPlanId((int) $filters['plan_id']);

        if (empty($matchingIdsFromPlan)) {
            $query->whereRaw('1 = 0');
        } else {
            $query->whereIn('id', $matchingIdsFromPlan);
        }
    }

    if ($filters['gateway'] !== '') {
        $matchingIdsFromGateway = $this->matchingTenantIdsByGateway($filters['gateway']);

        if (empty($matchingIdsFromGateway)) {
            $query->whereRaw('1 = 0');
        } else {
            $query->whereIn('id', $matchingIdsFromGateway);
        }
    }

    if ($filters['has_domain'] !== '') {
        $matchingIdsFromHasDomain = $this->matchingTenantIdsByHasDomain($filters['has_domain'] === 'yes');

        if ($filters['has_domain'] === 'yes' && empty($matchingIdsFromHasDomain)) {
            $query->whereRaw('1 = 0');
        } elseif ($filters['has_domain'] === 'yes') {
            $query->whereIn('id', $matchingIdsFromHasDomain);
        } elseif ($filters['has_domain'] === 'no' && ! empty($matchingIdsFromHasDomain)) {
            $query->whereNotIn('id', $matchingIdsFromHasDomain);
        }
    }

    if ($filters['created_from'] !== '' && $this->tenantTableHasColumn('created_at')) {
        $query->where('created_at', '>=', Carbon::parse($filters['created_from'])->startOfDay());
    }

    if ($filters['created_to'] !== '' && $this->tenantTableHasColumn('created_at')) {
        $query->where('created_at', '<=', Carbon::parse($filters['created_to'])->endOfDay());
    }

    if ($this->tenantTableHasColumn('created_at')) {
        $query->latest('created_at');
    } else {
        $query->orderBy('id');
    }

    /** @var LengthAwarePaginator $tenants */
    $tenants = $query->paginate(20)->withQueryString();

    $tenantRows = $this->buildTenantSummaries(collect($tenants->items()));

    return view('admin.tenants.index', [
        'tenants' => $tenants,
        'tenantRows' => $tenantRows,
        'filters' => $filters,
        'stats' => $this->indexStats(),
        'filterOptions' => [
            'plans' => $this->filterPlans(),
            'gateway_options' => $this->gatewayOptions(),
        ],
    ]);
}

public function show(string $tenantId): View
{
    $tenant = $this->findTenantOrFail($tenantId);

    $tenantRows = $this->buildTenantSummaries(collect([$tenant]));
    $row = $tenantRows[$tenantId] ?? null;

    $domains = $this->domainsByTenantIds([$tenantId])->get($tenantId, collect())->values();
    $subscription = $this->latestSubscriptionsByTenantIds([$tenantId])->get($tenantId);
    $productSubscriptions = $this->productSubscriptionsByTenantIds([$tenantId])->get($tenantId, collect());
    $tenantData = $this->normalizedTenantData($tenant);
    $ownerSnapshot = $this->ownerSnapshot($tenantData);
    $diagnostics = $this->tenantDiagnostics($tenant, $row, $domains, $subscription, $productSubscriptions, $tenantData);
    $availablePlans = $this->lifecycleService->availablePlans();

    return view('admin.tenants.show', [
        'tenant' => $tenant,
        'row' => $row,
        'domains' => $domains,
        'subscription' => $subscription,
        'productSubscriptions' => $productSubscriptions,
        'tenantData' => $tenantData,
        'ownerSnapshot' => $ownerSnapshot,
        'diagnostics' => $diagnostics,
        'availablePlans' => $availablePlans,
    ]);
}

public function productSubscriptionsIndex(Request $request): View
{
    $filters = $this->productSubscriptionFilters($request);

    $subscriptions = new LengthAwarePaginator([], 0, 20, 1, [
        'path' => $request->url(),
        'query' => $request->query(),
    ]);

    if (Schema::connection($this->centralConnectionName())->hasTable('tenant_product_subscriptions')) {
        $query = $this->productSubscriptionsBaseQuery();
        $this->applyProductSubscriptionFilters($query, $filters, 'tenant_product_subscriptions');

        $subscriptions = $query
            ->orderByDesc('tenant_product_subscriptions.id')
            ->paginate(20)
            ->withQueryString();
    }

    $statusCounts = $this->productSubscriptionStatusCounts();

    $products = collect();
    if ($this->productsTableExists()) {
        $products = DB::connection($this->centralConnectionName())
            ->table('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    $gatewayOptions = collect();
    if (Schema::connection($this->centralConnectionName())->hasTable('tenant_product_subscriptions')) {
        $gatewayOptions = DB::connection($this->centralConnectionName())
            ->table('tenant_product_subscriptions')
            ->select('gateway')
            ->whereNotNull('gateway')
            ->where('gateway', '!=', '')
            ->distinct()
            ->orderBy('gateway')
            ->pluck('gateway');
    }

    return view('admin.tenants.product-subscriptions', [
        'subscriptions' => $subscriptions,
        'filters' => $filters,
        'statusCounts' => $statusCounts,
        'products' => $products,
        'gatewayOptions' => $gatewayOptions,
        'statusOptions' => [
            'trialing',
            'active',
            'past_due',
            'suspended',
            'cancelled',
            'expired',
        ],
        'syncStatusOptions' => [
            'success',
            'failed',
            'never',
        ],
        'syncFreshnessOptions' => [
            'never',
            'recent_24h',
            'stale_7d',
        ],
    ]);
}

public function bulkSyncProductSubscriptionsFromStripe(
    Request $request,
    AdminTenantProductSubscriptionStripeSyncService $syncService
): RedirectResponse {
    $validated = $request->validate([
        'bulk_sync_action' => ['required', 'in:selected,filtered,failed_only'],
        'selected_ids' => ['array'],
        'selected_ids.*' => ['integer'],
    ]);

    $filters = $this->productSubscriptionFilters($request);
    $action = (string) $validated['bulk_sync_action'];
    $selectedIds = collect($validated['selected_ids'] ?? [])
        ->filter(fn ($id) => is_numeric($id))
        ->map(fn ($id) => (int) $id)
        ->unique()
        ->values();

    $query = TenantProductSubscription::query();
    $this->applyProductSubscriptionFilters($query, $filters, $query->getModel()->getTable());

    if ($action === 'selected') {
        if ($selectedIds->isEmpty()) {
            return redirect()
                ->route('admin.tenants.product-subscriptions.index', $this->productSubscriptionRouteFilters($filters))
                ->with('error', 'Select at least one product subscription before running bulk sync.');
        }

        $query->whereKey($selectedIds->all());
    }

    if ($action === 'failed_only') {
        $query->where('last_sync_status', 'failed');
    }

    $subscriptions = $query
        ->orderBy('id')
        ->get();

    if ($subscriptions->isEmpty()) {
        return redirect()
            ->route('admin.tenants.product-subscriptions.index', $this->productSubscriptionRouteFilters($filters))
            ->with('error', 'No product subscriptions matched the current bulk sync scope.');
    }

    if ($action !== 'selected') {
        $queued = 0;
        $skipped = 0;
        $admin = $request->user('admin');

        foreach ($subscriptions as $subscription) {
            if (! $this->productSubscriptionCanSyncFromStripe($subscription)) {
                $skipped++;
                continue;
            }

            SyncTenantProductSubscriptionFromStripeJob::dispatch(
                subscriptionId: (int) $subscription->id,
                adminUserId: $admin?->id,
                adminEmail: $admin?->email,
            );

            $queued++;
        }

        $this->activityLogger->log(
            action: 'tenant.product_subscription.bulk_sync_queued_from_stripe',
            subjectType: 'tenant_product_subscription',
            subjectId: null,
            tenantId: null,
            contextPayload: [
                'source' => 'admin.product_subscription.bulk_sync_from_stripe',
                'bulk_sync_action' => $action,
                'filters' => $this->productSubscriptionRouteFilters($filters),
                'selected_ids' => $selectedIds->all(),
                'summary' => [
                    'total' => $subscriptions->count(),
                    'queued' => $queued,
                    'skipped' => $skipped,
                ],
            ]
        );

        return redirect()
            ->route('admin.tenants.product-subscriptions.index', $this->productSubscriptionRouteFilters($filters))
            ->with('success', sprintf(
                'Bulk Stripe sync queued. Queued: %d, skipped: %d.',
                $queued,
                $skipped
            ));
    }

    $summary = [
        'total' => $subscriptions->count(),
        'succeeded' => 0,
        'failed' => 0,
        'skipped' => 0,
    ];

    foreach ($subscriptions as $subscription) {
        if (! $this->productSubscriptionCanSyncFromStripe($subscription)) {
            $summary['skipped']++;
            continue;
        }

        try {
            $syncService->sync($subscription);
            $summary['succeeded']++;
        } catch (Throwable $exception) {
            $summary['failed']++;
        }
    }

    $this->activityLogger->log(
        action: 'tenant.product_subscription.bulk_synced_from_stripe',
        subjectType: 'tenant_product_subscription',
        subjectId: null,
        tenantId: null,
        contextPayload: [
            'source' => 'admin.product_subscription.bulk_sync_from_stripe',
            'bulk_sync_action' => $action,
            'filters' => $this->productSubscriptionRouteFilters($filters),
            'selected_ids' => $selectedIds->all(),
            'summary' => $summary,
        ]
    );

    return redirect()
        ->route('admin.tenants.product-subscriptions.index', $this->productSubscriptionRouteFilters($filters))
        ->with('success', sprintf(
            'Bulk Stripe sync finished. Succeeded: %d, failed: %d, skipped: %d.',
            $summary['succeeded'],
            $summary['failed'],
            $summary['skipped']
        ));
}

public function showProductSubscription(int $subscriptionId): View
{
    $subscription = $this->findProductSubscriptionOrFail($subscriptionId);
    $tenant = $this->findTenantOrFail((string) $subscription['tenant_id']);
    $tenantData = $this->normalizedTenantData($tenant);
    $ownerSnapshot = $this->ownerSnapshot($tenantData);
    $diagnostics = $this->productSubscriptionDiagnostics($subscription);
    $latestInvoice = $this->latestInvoiceForProductSubscription($subscription);
    $healthHints = $this->productSubscriptionHealthHints($subscription, $latestInvoice);

    return view('admin.tenants.product-subscription-show', [
        'subscription' => $subscription,
        'tenant' => $tenant,
        'tenantData' => $tenantData,
        'ownerSnapshot' => $ownerSnapshot,
        'diagnostics' => $diagnostics,
        'latestInvoice' => $latestInvoice,
        'healthHints' => $healthHints,
    ]);
}

public function syncProductSubscriptionFromStripe(
    Request $request,
    int $subscriptionId,
    AdminTenantProductSubscriptionStripeSyncService $syncService
): RedirectResponse {
    $subscription = TenantProductSubscription::query()->find($subscriptionId);

    if (! $subscription) {
        return redirect()
            ->route('admin.tenants.product-subscriptions.show', $subscriptionId)
            ->with('error', 'The product subscription record was not found.');
    }

    try {
        $before = $subscription->only([
            'status',
            'plan_id',
            'gateway_customer_id',
            'gateway_subscription_id',
            'gateway_checkout_session_id',
            'gateway_price_id',
            'ends_at',
        ]);

        $synced = $syncService->sync($subscription);

        $this->activityLogger->log(
            action: 'tenant.product_subscription.synced_from_stripe',
            subjectType: 'tenant_product_subscription',
            subjectId: $synced->id ?? $subscriptionId,
            tenantId: (string) $synced->tenant_id,
            contextPayload: [
                'source' => 'admin.product_subscription.sync_from_stripe',
                'before' => $before,
                'after' => $synced->only([
                    'status',
                    'plan_id',
                    'gateway_customer_id',
                    'gateway_subscription_id',
                    'gateway_checkout_session_id',
                    'gateway_price_id',
                    'ends_at',
                ]),
            ]
        );

        return redirect()
            ->route('admin.tenants.product-subscriptions.show', $subscriptionId)
            ->with('success', 'Product subscription data was synced successfully from Stripe.');
    } catch (RuntimeException $exception) {
        return redirect()
            ->route('admin.tenants.product-subscriptions.show', $subscriptionId)
            ->with('error', $exception->getMessage());
    } catch (Throwable $exception) {
        $subscription->update([
            'last_synced_from_stripe_at' => now(),
            'last_sync_status' => 'failed',
            'last_sync_error' => 'Unable to sync the product subscription from Stripe right now.',
        ]);

        report($exception);

        return redirect()
            ->route('admin.tenants.product-subscriptions.show', $subscriptionId)
            ->with('error', 'Unable to sync the product subscription from Stripe right now.');
    }
}

public function suspend(string $tenantId): RedirectResponse
{
    $this->findTenantOrFail($tenantId);

    $before = $this->lifecycleService->latestSubscriptionByTenantId($tenantId);

    try {
        $this->lifecycleService->suspendLatestSubscription($tenantId);

        $after = $this->lifecycleService->latestSubscriptionByTenantId($tenantId);

        $this->activityLogger->log(
            action: 'tenant.subscription.suspended',
                subjectType: 'subscription',
                subjectId: $after?->id ?? $before?->id,
                tenantId: $tenantId,
                contextPayload: [
            'before' => [
                'status' => $before->status ?? null,
                'suspended_at' => $before->suspended_at ?? null,
            ],
            'after' => [
                'status' => $after->status ?? null,
                'suspended_at' => $after->suspended_at ?? null,
            ],
        ]
            );

            return redirect()
                ->route('admin.tenants.show', $tenantId)
                ->with('success', 'The latest subscription was suspended successfully.');
        } catch (RuntimeException $exception) {
        return redirect()
            ->route('admin.tenants.show', $tenantId)
            ->with('error', $exception->getMessage());
    }
}

public function activate(string $tenantId): RedirectResponse
{
    $this->findTenantOrFail($tenantId);

    $before = $this->lifecycleService->latestSubscriptionByTenantId($tenantId);

    try {
        $this->lifecycleService->activateLatestSubscription($tenantId);

        $after = $this->lifecycleService->latestSubscriptionByTenantId($tenantId);

        $this->activityLogger->log(
            action: 'tenant.subscription.activated',
                subjectType: 'subscription',
                subjectId: $after?->id ?? $before?->id,
                tenantId: $tenantId,
                contextPayload: [
            'before' => [
                'status' => $before->status ?? null,
                'suspended_at' => $before->suspended_at ?? null,
            ],
            'after' => [
                'status' => $after->status ?? null,
                'suspended_at' => $after->suspended_at ?? null,
            ],
        ]
            );

            return redirect()
                ->route('admin.tenants.show', $tenantId)
                ->with('success', 'The latest subscription was activated successfully.');
        } catch (RuntimeException $exception) {
        return redirect()
            ->route('admin.tenants.show', $tenantId)
            ->with('error', $exception->getMessage());
    }
}

public function extendTrial(Request $request, string $tenantId): RedirectResponse
{
    $this->findTenantOrFail($tenantId);

    $validated = $request->validate([
        'days' => ['required', 'integer', 'min:1', 'max:90'],
    ]);

    $before = $this->lifecycleService->latestSubscriptionByTenantId($tenantId);

    try {
        $this->lifecycleService->extendLatestTrial($tenantId, (int) $validated['days']);

        $after = $this->lifecycleService->latestSubscriptionByTenantId($tenantId);

        $this->activityLogger->log(
            action: 'tenant.trial.extended',
                subjectType: 'subscription',
                subjectId: $after?->id ?? $before?->id,
                tenantId: $tenantId,
                contextPayload: [
            'days_added' => (int) $validated['days'],
            'before' => [
                'trial_ends_at' => $before->trial_ends_at ?? null,
                'status' => $before->status ?? null,
            ],
            'after' => [
                'trial_ends_at' => $after->trial_ends_at ?? null,
                'status' => $after->status ?? null,
            ],
        ]
            );

            return redirect()
                ->route('admin.tenants.show', $tenantId)
                ->with('success', 'The tenant trial was extended successfully.');
        } catch (RuntimeException $exception) {
        return redirect()
            ->route('admin.tenants.show', $tenantId)
            ->with('error', $exception->getMessage());
    }
}

public function changePlan(Request $request, string $tenantId): RedirectResponse
{
    $this->findTenantOrFail($tenantId);

    $validated = $request->validate([
        'plan_id' => ['required', 'integer'],
    ]);

    $before = $this->lifecycleService->latestSubscriptionByTenantId($tenantId);

    try {
        $this->lifecycleService->changeLatestPlan($tenantId, (int) $validated['plan_id']);

        $after = $this->lifecycleService->latestSubscriptionByTenantId($tenantId);

        $this->activityLogger->log(
            action: 'tenant.plan.changed',
                subjectType: 'subscription',
                subjectId: $after?->id ?? $before?->id,
                tenantId: $tenantId,
                contextPayload: [
            'requested_plan_id' => (int) $validated['plan_id'],
            'before' => [
                'plan_id' => $before->plan_id ?? null,
                'billing_period' => $before->billing_period ?? null,
                'status' => $before->status ?? null,
            ],
            'after' => [
                'plan_id' => $after->plan_id ?? null,
                'billing_period' => $after->billing_period ?? null,
                'status' => $after->status ?? null,
            ],
        ]
            );

            return redirect()
                ->route('admin.tenants.show', $tenantId)
                ->with('success', 'The latest subscription plan was changed successfully.');
        } catch (RuntimeException $exception) {
        return redirect()
            ->route('admin.tenants.show', $tenantId)
            ->with('error', $exception->getMessage());
    }
}

public function destroy(string $tenantId): RedirectResponse
{
    $this->findTenantOrFail($tenantId);

    try {
        $result = $this->lifecycleService->deleteTenant($tenantId);

        $this->activityLogger->log(
            action: 'tenant.deleted',
            subjectType: 'tenant',
            subjectId: $tenantId,
            tenantId: $tenantId,
            contextPayload: $result
        );

        return redirect()
            ->route('admin.tenants.index')
            ->with('success', 'The tenant and its linked central records were deleted successfully.');
    } catch (RuntimeException $exception) {
        return redirect()
            ->route('admin.tenants.show', $tenantId)
            ->with('error', $exception->getMessage());
    }
}

public function impersonate(string $tenantId): RedirectResponse
{
    $this->findTenantOrFail($tenantId);

    try {
        $redirectUrl = $this->tenantImpersonationService->start($tenantId);

        $this->activityLogger->log(
            action: 'tenant.impersonation.started',
            subjectType: 'tenant',
            subjectId: $tenantId,
            tenantId: $tenantId,
            contextPayload: [
                'redirect_url' => $redirectUrl,
            ]
        );

        return redirect()->away($redirectUrl);
    } catch (RuntimeException $exception) {
        return redirect()
            ->route('admin.tenants.show', $tenantId)
            ->with('error', $exception->getMessage());
    }
}

protected function tenantModelClass(): string
{
    return (string) (Config::get('tenancy.tenant_model') ?: \App\Models\Tenant::class);
}

protected function tenantModelInstance(): Model
{
    $class = $this->tenantModelClass();

    /** @var Model $instance */
    $instance = new $class();

    return $instance;
}

protected function tenantConnectionName(): string
{
    return $this->tenantModelInstance()->getConnectionName()
        ?: (string) (Config::get('tenancy.database.central_connection') ?: Config::get('database.default'));
}

protected function tenantTableName(): string
{
    return $this->tenantModelInstance()->getTable();
}

protected function centralConnectionName(): string
{
    return (string) (Config::get('tenancy.database.central_connection') ?: Config::get('database.default'));
}

protected function tenantTableHasColumn(string $column): bool
{
    return Schema::connection($this->tenantConnectionName())->hasColumn($this->tenantTableName(), $column);
}

protected function domainsTableExists(): bool
{
    return Schema::connection($this->centralConnectionName())->hasTable('domains');
}

protected function subscriptionsTableExists(): bool
{
    return Schema::connection($this->centralConnectionName())->hasTable('subscriptions');
}

protected function plansTableExists(): bool
{
    return Schema::connection($this->centralConnectionName())->hasTable('plans');
}

protected function matchingTenantIdsByDomain(string $search): array
{
    if (! $this->domainsTableExists()) {
        return [];
    }

    return DB::connection($this->centralConnectionName())
        ->table('domains')
        ->where('domain', 'like', '%' . $search . '%')
        ->pluck('tenant_id')
        ->filter()
        ->unique()
        ->map(fn ($id) => (string) $id)
        ->values()
        ->all();
}

protected function matchingTenantIdsBySubscriptionStatus(string $status): array
{
    if (! $this->subscriptionsTableExists()) {
        return [];
    }

    return DB::connection($this->centralConnectionName())
        ->table('subscriptions')
        ->where('status', $status)
        ->pluck('tenant_id')
        ->filter()
        ->unique()
        ->map(fn ($id) => (string) $id)
        ->values()
        ->all();
}

protected function matchingTenantIdsByPlanId(int $planId): array
{
    if (! $this->subscriptionsTableExists()) {
        return [];
    }

    return DB::connection($this->centralConnectionName())
        ->table('subscriptions')
        ->where('plan_id', $planId)
        ->pluck('tenant_id')
        ->filter()
        ->unique()
        ->map(fn ($id) => (string) $id)
        ->values()
        ->all();
}

protected function matchingTenantIdsByGateway(string $gateway): array
{
    if (! $this->subscriptionsTableExists()) {
        return [];
    }

    return DB::connection($this->centralConnectionName())
        ->table('subscriptions')
        ->where('gateway', $gateway)
        ->pluck('tenant_id')
        ->filter()
        ->unique()
        ->map(fn ($id) => (string) $id)
        ->values()
        ->all();
}

protected function matchingTenantIdsByHasDomain(bool $hasDomain): array
{
    if (! $this->domainsTableExists()) {
        return [];
    }

    $ids = DB::connection($this->centralConnectionName())
        ->table('domains')
        ->pluck('tenant_id')
        ->filter()
        ->unique()
        ->map(fn ($id) => (string) $id)
        ->values()
        ->all();

    return $hasDomain ? $ids : [];
}

protected function matchingTenantIdsByTenantData(string $search): array
{
    $tenantModelClass = $this->tenantModelClass();

    return $tenantModelClass::query()
        ->get()
        ->filter(function (Model $tenant) use ($search): bool {
            $tenantData = $this->normalizedTenantData($tenant);
            $haystacks = [
                $this->firstFilledValue($tenantData, ['company_name', 'business_name', 'company', 'name']),
                $this->firstFilledValue($tenantData, ['owner_email', 'admin_email', 'email']),
                $this->firstFilledValue($tenantData, ['owner_name', 'admin_name', 'contact_name', 'name']),
            ];

            foreach ($haystacks as $value) {
                if ($value !== null && stripos($value, $search) !== false) {
                    return true;
                }
            }

            return false;
        })
        ->map(fn (Model $tenant) => (string) $tenant->getKey())
        ->values()
        ->all();
}

/**
 * @param  Collection<int, Model>  $tenants
 * @return array<string, array<string, mixed>>
 */
protected function buildTenantSummaries(Collection $tenants): array
{
    $tenantIds = $tenants
        ->map(fn (Model $tenant) => (string) $tenant->getKey())
        ->values()
        ->all();

    $domainsByTenant = $this->domainsByTenantIds($tenantIds);
    $subscriptionsByTenant = $this->latestSubscriptionsByTenantIds($tenantIds);

    $rows = [];

    foreach ($tenants as $tenant) {
        $tenantId = (string) $tenant->getKey();
        $domains = $domainsByTenant->get($tenantId, collect())->values();
        $subscription = $subscriptionsByTenant->get($tenantId);
        $tenantData = $this->normalizedTenantData($tenant);

        $primaryDomain = $domains->first();
        $primaryDomainString = $primaryDomain['domain'] ?? null;

        $rows[$tenantId] = [
            'tenant_id' => $tenantId,
            'primary_domain' => $primaryDomainString,
            'domains' => $domains,
            'domains_count' => $domains->count(),
            'open_url' => $this->domainToUrl($primaryDomainString),
            'admin_login_url' => $this->tenantAdminLoginUrl($primaryDomainString),
            'company_name' => $this->firstFilledValue($tenantData, [
                'company_name',
                'business_name',
                'company',
                'name',
            ]),
            'owner_email' => $this->firstFilledValue($tenantData, [
                'owner_email',
                'admin_email',
                'email',
            ]),
            'created_at' => $tenant->getAttribute('created_at'),
            'subscription' => $subscription,
            'subscription_status' => $subscription['status'] ?? null,
            'plan_name' => $subscription['plan_name'] ?? null,
            'plan_id' => $subscription['plan_id'] ?? null,
            'billing_period' => $subscription['billing_period'] ?? null,
            'gateway' => $subscription['gateway'] ?? null,
            'is_stripe_linked' => ! empty($subscription['gateway_subscription_id']) || ($subscription['gateway'] ?? null) === 'stripe',
            'subscription_show_url' => ! empty($subscription['id'])
                ? route('admin.subscriptions.show', $subscription['id'])
                : null,
            'show_url' => route('admin.tenants.show', $tenantId),
        ];
    }

    return $rows;
}

/**
 * @param  array<int, string>  $tenantIds
 * @return Collection<string, Collection<int, array<string, mixed>>>
 */
protected function domainsByTenantIds(array $tenantIds): Collection
{
    if (empty($tenantIds) || ! $this->domainsTableExists()) {
        return collect();
    }

    $rows = DB::connection($this->centralConnectionName())
        ->table('domains')
        ->whereIn('tenant_id', $tenantIds)
        ->orderBy('domain')
        ->get(['tenant_id', 'domain']);

    return $rows
        ->groupBy(fn ($row) => (string) $row->tenant_id)
        ->map(function (Collection $group): Collection {
            return $group->map(function ($row): array {
                return [
                    'tenant_id' => (string) $row->tenant_id,
                    'domain' => (string) $row->domain,
                    'url' => $this->domainToUrl((string) $row->domain),
                    'admin_login_url' => $this->tenantAdminLoginUrl((string) $row->domain),
                ];
            })->values();
        });
}

/**
 * @param  array<int, string>  $tenantIds
 * @return Collection<string, array<string, mixed>>
 */
protected function latestSubscriptionsByTenantIds(array $tenantIds): Collection
{
    if (empty($tenantIds) || ! $this->subscriptionsTableExists()) {
        return collect();
    }

    $subscriptions = DB::connection($this->centralConnectionName())
        ->table('subscriptions')
        ->whereIn('tenant_id', $tenantIds)
        ->orderByDesc('id')
        ->get();

    if ($subscriptions->isEmpty()) {
        return collect();
    }

    $planNames = collect();

    if ($this->plansTableExists()) {
        $planIds = $subscriptions
            ->pluck('plan_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! empty($planIds)) {
            $planNames = DB::connection($this->centralConnectionName())
                ->table('plans')
                ->whereIn('id', $planIds)
                ->get(['id', 'name', 'slug'])
                ->mapWithKeys(function ($plan): array {
                    return [
                        (string) $plan->id => [
                            'name' => (string) ($plan->name ?? ''),
                            'slug' => (string) ($plan->slug ?? ''),
                        ],
                    ];
                });
        }
    }

    return $subscriptions
        ->groupBy(fn ($row) => (string) $row->tenant_id)
        ->map(function (Collection $group) use ($planNames): array {
            $subscription = $group->first();

            $plan = $planNames->get((string) ($subscription->plan_id ?? ''), [
                'name' => null,
                'slug' => null,
            ]);

            return [
                'id' => $subscription->id,
                'tenant_id' => (string) $subscription->tenant_id,
                'plan_id' => $subscription->plan_id,
                'plan_name' => $plan['name'] ?: $plan['slug'],
                'status' => $subscription->status ?? null,
                'billing_period' => $subscription->billing_period ?? null,
                'trial_ends_at' => $subscription->trial_ends_at ?? null,
                'grace_ends_at' => $subscription->grace_ends_at ?? null,
                'past_due_started_at' => $subscription->past_due_started_at ?? null,
                'suspended_at' => $subscription->suspended_at ?? null,
                'cancelled_at' => $subscription->cancelled_at ?? null,
                'ends_at' => $subscription->ends_at ?? null,
                'gateway' => $subscription->gateway ?? null,
                'gateway_customer_id' => $subscription->gateway_customer_id ?? null,
                'gateway_subscription_id' => $subscription->gateway_subscription_id ?? null,
            ];
        });
}

/**
 * @param  array<int, string>  $tenantIds
 * @return Collection<string, Collection<int, array<string, mixed>>>
 */
protected function productSubscriptionsByTenantIds(array $tenantIds): Collection
{
    if (
        empty($tenantIds)
        || ! Schema::connection($this->centralConnectionName())->hasTable('tenant_product_subscriptions')
    ) {
        return collect();
    }

    $query = DB::connection($this->centralConnectionName())
        ->table('tenant_product_subscriptions')
        ->whereIn('tenant_id', $tenantIds)
        ->orderByDesc('id')
        ->select('tenant_product_subscriptions.*');

    if ($this->productsTableExists()) {
        $query->leftJoin('products', 'products.id', '=', 'tenant_product_subscriptions.product_id')
            ->addSelect([
                'products.name as product_name',
                'products.slug as product_slug',
                'products.code as product_code',
            ]);
    }

    if ($this->plansTableExists()) {
        $query->leftJoin('plans', 'plans.id', '=', 'tenant_product_subscriptions.plan_id')
            ->addSelect([
                'plans.name as plan_name',
                'plans.slug as plan_slug',
                'plans.billing_period as plan_billing_period',
                'plans.price as plan_price',
                'plans.currency as plan_currency',
            ]);
    }

    $rows = $query->get();

    if ($rows->isEmpty()) {
        return collect();
    }

    return $rows
        ->groupBy(fn ($row) => (string) $row->tenant_id)
        ->map(function (Collection $group): Collection {
            return $group->map(function ($row): array {
                return [
                    'id' => $row->id,
                    'tenant_id' => (string) $row->tenant_id,
                    'product_id' => $row->product_id,
                    'product_name' => $row->product_name ?? null,
                    'product_slug' => $row->product_slug ?? null,
                    'product_code' => $row->product_code ?? null,
                    'plan_id' => $row->plan_id,
                    'plan_name' => $row->plan_name ?? null,
                    'plan_slug' => $row->plan_slug ?? null,
                    'plan_billing_period' => $row->plan_billing_period ?? null,
                    'plan_price' => $row->plan_price ?? null,
                    'plan_currency' => $row->plan_currency ?? null,
                    'status' => $row->status ?? null,
                    'trial_ends_at' => $row->trial_ends_at ?? null,
                    'grace_ends_at' => $row->grace_ends_at ?? null,
                    'last_payment_failed_at' => $row->last_payment_failed_at ?? null,
                    'past_due_started_at' => $row->past_due_started_at ?? null,
                    'suspended_at' => $row->suspended_at ?? null,
                    'cancelled_at' => $row->cancelled_at ?? null,
                    'payment_failures_count' => (int) ($row->payment_failures_count ?? 0),
                    'ends_at' => $row->ends_at ?? null,
                    'external_id' => $row->external_id ?? null,
                    'gateway' => $row->gateway ?? null,
                    'gateway_customer_id' => $row->gateway_customer_id ?? null,
                    'gateway_subscription_id' => $row->gateway_subscription_id ?? null,
                    'gateway_checkout_session_id' => $row->gateway_checkout_session_id ?? null,
                    'gateway_price_id' => $row->gateway_price_id ?? null,
                    'last_synced_from_stripe_at' => $row->last_synced_from_stripe_at ?? null,
                    'last_sync_status' => $row->last_sync_status ?? null,
                    'last_sync_error' => $row->last_sync_error ?? null,
                    'legacy_subscription_id' => $row->legacy_subscription_id ?? null,
                    'created_at' => $row->created_at ?? null,
                    'updated_at' => $row->updated_at ?? null,
                ];
            })->values();
        });
}

protected function productSubscriptionsBaseQuery()
{
    $query = DB::connection($this->centralConnectionName())
        ->table('tenant_product_subscriptions')
        ->select([
            'tenant_product_subscriptions.id',
            'tenant_product_subscriptions.tenant_id',
            'tenant_product_subscriptions.product_id',
            'tenant_product_subscriptions.plan_id',
            'tenant_product_subscriptions.legacy_subscription_id',
            'tenant_product_subscriptions.status',
            'tenant_product_subscriptions.trial_ends_at',
            'tenant_product_subscriptions.grace_ends_at',
            'tenant_product_subscriptions.last_payment_failed_at',
            'tenant_product_subscriptions.past_due_started_at',
            'tenant_product_subscriptions.suspended_at',
            'tenant_product_subscriptions.cancelled_at',
            'tenant_product_subscriptions.payment_failures_count',
            'tenant_product_subscriptions.ends_at',
            'tenant_product_subscriptions.external_id',
            'tenant_product_subscriptions.gateway',
            'tenant_product_subscriptions.gateway_customer_id',
            'tenant_product_subscriptions.gateway_subscription_id',
            'tenant_product_subscriptions.gateway_checkout_session_id',
            'tenant_product_subscriptions.gateway_price_id',
            'tenant_product_subscriptions.last_synced_from_stripe_at',
            'tenant_product_subscriptions.last_sync_status',
            'tenant_product_subscriptions.last_sync_error',
            'tenant_product_subscriptions.created_at',
            'tenant_product_subscriptions.updated_at',
        ]);

    if ($this->productsTableExists()) {
        $query->leftJoin('products', 'products.id', '=', 'tenant_product_subscriptions.product_id')
            ->addSelect([
                'products.name as product_name',
                'products.slug as product_slug',
                'products.code as product_code',
            ]);
    }

    if ($this->plansTableExists()) {
        $query->leftJoin('plans', 'plans.id', '=', 'tenant_product_subscriptions.plan_id')
            ->addSelect([
                'plans.name as plan_name',
                'plans.slug as plan_slug',
                'plans.billing_period as plan_billing_period',
                'plans.price as plan_price',
                'plans.currency as plan_currency',
            ]);
    }

    return $query;
}

protected function productSubscriptionFilters(Request $request): array
{
    return [
        'tenant_id' => trim((string) $request->string('tenant_id')),
        'status' => trim((string) $request->string('status')),
        'product_id' => $request->filled('product_id') ? (int) $request->input('product_id') : null,
        'gateway' => trim((string) $request->string('gateway')),
        'last_sync_status' => trim((string) $request->string('last_sync_status')),
        'sync_freshness' => trim((string) $request->string('sync_freshness')),
    ];
}

protected function applyProductSubscriptionFilters(object $query, array $filters, string $table): void
{
    if (($filters['tenant_id'] ?? '') !== '') {
        $query->where($table . '.tenant_id', 'like', '%' . $filters['tenant_id'] . '%');
    }

    if (($filters['status'] ?? '') !== '') {
        $query->where($table . '.status', $filters['status']);
    }

    if (! empty($filters['product_id'])) {
        $query->where($table . '.product_id', $filters['product_id']);
    }

    if (($filters['gateway'] ?? '') !== '') {
        $query->where($table . '.gateway', $filters['gateway']);
    }

    if (($filters['last_sync_status'] ?? '') !== '') {
        if ($filters['last_sync_status'] === 'never') {
            $query->whereNull($table . '.last_sync_status');
        } else {
            $query->where($table . '.last_sync_status', $filters['last_sync_status']);
        }
    }

    if (($filters['sync_freshness'] ?? '') !== '') {
        if ($filters['sync_freshness'] === 'never') {
            $query->whereNull($table . '.last_synced_from_stripe_at');
        }

        if ($filters['sync_freshness'] === 'recent_24h') {
            $query->whereNotNull($table . '.last_synced_from_stripe_at')
                ->where($table . '.last_synced_from_stripe_at', '>=', now()->subDay());
        }

        if ($filters['sync_freshness'] === 'stale_7d') {
            $query->where(function ($builder) use ($table) {
                $builder->whereNull($table . '.last_synced_from_stripe_at')
                    ->orWhere($table . '.last_synced_from_stripe_at', '<', now()->subDays(7));
            });
        }
    }
}

protected function productSubscriptionRouteFilters(array $filters): array
{
    return collect($filters)
        ->filter(fn ($value) => ! ($value === null || $value === ''))
        ->all();
}

protected function productSubscriptionCanSyncFromStripe(TenantProductSubscription $subscription): bool
{
    return ($subscription->gateway ?? null) === 'stripe'
        || filled($subscription->gateway_subscription_id)
        || filled($subscription->gateway_customer_id)
        || filled($subscription->gateway_checkout_session_id);
}

protected function findProductSubscriptionOrFail(int $subscriptionId): array
{
    if (! Schema::connection($this->centralConnectionName())->hasTable('tenant_product_subscriptions')) {
        throw new NotFoundHttpException();
    }

    $record = $this->productSubscriptionsBaseQuery()
        ->where('tenant_product_subscriptions.id', $subscriptionId)
        ->first();

    if (! $record) {
        throw new NotFoundHttpException();
    }

    return [
        'id' => $record->id,
        'tenant_id' => (string) $record->tenant_id,
        'product_id' => $record->product_id,
        'product_name' => $record->product_name ?? null,
        'product_slug' => $record->product_slug ?? null,
        'product_code' => $record->product_code ?? null,
        'plan_id' => $record->plan_id,
        'plan_name' => $record->plan_name ?? null,
        'plan_slug' => $record->plan_slug ?? null,
        'plan_billing_period' => $record->plan_billing_period ?? null,
        'plan_price' => $record->plan_price ?? null,
        'plan_currency' => $record->plan_currency ?? null,
        'legacy_subscription_id' => $record->legacy_subscription_id ?? null,
        'status' => $record->status ?? null,
        'trial_ends_at' => $record->trial_ends_at ?? null,
        'grace_ends_at' => $record->grace_ends_at ?? null,
        'last_payment_failed_at' => $record->last_payment_failed_at ?? null,
        'past_due_started_at' => $record->past_due_started_at ?? null,
        'suspended_at' => $record->suspended_at ?? null,
        'cancelled_at' => $record->cancelled_at ?? null,
        'payment_failures_count' => (int) ($record->payment_failures_count ?? 0),
        'ends_at' => $record->ends_at ?? null,
        'external_id' => $record->external_id ?? null,
        'gateway' => $record->gateway ?? null,
        'gateway_customer_id' => $record->gateway_customer_id ?? null,
        'gateway_subscription_id' => $record->gateway_subscription_id ?? null,
        'gateway_checkout_session_id' => $record->gateway_checkout_session_id ?? null,
        'gateway_price_id' => $record->gateway_price_id ?? null,
        'last_synced_from_stripe_at' => $record->last_synced_from_stripe_at ?? null,
        'last_sync_status' => $record->last_sync_status ?? null,
        'last_sync_error' => $record->last_sync_error ?? null,
        'created_at' => $record->created_at ?? null,
        'updated_at' => $record->updated_at ?? null,
    ];
}

protected function productSubscriptionDiagnostics(array $subscription): array
{
    return [
        'has_product' => ! empty($subscription['product_id']),
        'has_plan' => ! empty($subscription['plan_id']),
        'has_gateway' => ! empty($subscription['gateway']),
        'is_stripe_linked' => ($subscription['gateway'] ?? null) === 'stripe' || ! empty($subscription['gateway_subscription_id']),
        'has_gateway_customer_id' => ! empty($subscription['gateway_customer_id']),
        'has_gateway_subscription_id' => ! empty($subscription['gateway_subscription_id']),
        'has_gateway_checkout_session_id' => ! empty($subscription['gateway_checkout_session_id']),
        'has_gateway_price_id' => ! empty($subscription['gateway_price_id']),
        'has_last_sync_timestamp' => ! empty($subscription['last_synced_from_stripe_at']),
        'last_sync_status' => $subscription['last_sync_status'] ?? null,
        'has_last_sync_error' => ! empty($subscription['last_sync_error']),
        'has_legacy_subscription_id' => ! empty($subscription['legacy_subscription_id']),
        'has_payment_failures' => (int) ($subscription['payment_failures_count'] ?? 0) > 0,
        'has_end_date' => ! empty($subscription['ends_at']),
    ];
}

protected function latestInvoiceForProductSubscription(array $subscription): ?array
{
    if (! Schema::connection($this->centralConnectionName())->hasTable('billing_invoices')) {
        return null;
    }

    $query = DB::connection($this->centralConnectionName())
        ->table('billing_invoices')
        ->orderByDesc('issued_at')
        ->orderByDesc('id');

    $gatewaySubscriptionId = (string) ($subscription['gateway_subscription_id'] ?? '');

    if ($gatewaySubscriptionId !== '') {
        $query->where('gateway_subscription_id', $gatewaySubscriptionId);
    } else {
        $query->where('tenant_id', (string) $subscription['tenant_id']);

        if (! empty($subscription['gateway'])) {
            $query->where('gateway', (string) $subscription['gateway']);
        }
    }

    $invoice = $query->first();

    if (! $invoice) {
        return null;
    }

    return [
        'id' => $invoice->id,
        'subscription_id' => $invoice->subscription_id ?? null,
        'tenant_id' => (string) ($invoice->tenant_id ?? ''),
        'gateway' => $invoice->gateway ?? null,
        'gateway_invoice_id' => $invoice->gateway_invoice_id ?? null,
        'gateway_customer_id' => $invoice->gateway_customer_id ?? null,
        'gateway_subscription_id' => $invoice->gateway_subscription_id ?? null,
        'invoice_number' => $invoice->invoice_number ?? null,
        'status' => $invoice->status ?? null,
        'billing_reason' => $invoice->billing_reason ?? null,
        'currency' => $invoice->currency ?? null,
        'total_decimal' => $invoice->total_decimal ?? null,
        'amount_paid_decimal' => $invoice->amount_paid_decimal ?? null,
        'amount_due_decimal' => $invoice->amount_due_decimal ?? null,
        'hosted_invoice_url' => $invoice->hosted_invoice_url ?? null,
        'invoice_pdf' => $invoice->invoice_pdf ?? null,
        'issued_at' => $invoice->issued_at ?? null,
        'paid_at' => $invoice->paid_at ?? null,
    ];
}

protected function productSubscriptionHealthHints(array $subscription, ?array $latestInvoice): array
{
    $hints = [];

    if (($subscription['gateway'] ?? null) === 'stripe' && empty($subscription['gateway_subscription_id'])) {
        $hints[] = [
            'severity' => 'warning',
            'message' => 'Stripe gateway is set, but the gateway subscription ID is still missing.',
        ];
    }

    if (($subscription['status'] ?? null) === 'past_due' && (int) ($subscription['payment_failures_count'] ?? 0) === 0) {
        $hints[] = [
            'severity' => 'warning',
            'message' => 'The record is marked past due, but payment failures count is still zero.',
        ];
    }

    if (($subscription['status'] ?? null) === 'active' && ! empty($subscription['ends_at'])) {
        $hints[] = [
            'severity' => 'info',
            'message' => 'The record is active and already has an end date. Verify whether this is a scheduled cancellation.',
        ];
    }

    if (! $latestInvoice) {
        $hints[] = [
            'severity' => 'info',
            'message' => 'No local billing invoice is linked to this product subscription yet.',
        ];
    } elseif (($latestInvoice['status'] ?? null) === 'paid') {
        $hints[] = [
            'severity' => 'success',
            'message' => 'Latest local invoice is paid.',
        ];
    } elseif (in_array((string) ($latestInvoice['status'] ?? ''), ['open', 'uncollectible', 'void'], true)) {
        $hints[] = [
            'severity' => 'warning',
            'message' => 'Latest local invoice is not settled. Review invoice status and payment collection path.',
        ];
    }

    return $hints;
}

protected function productSubscriptionStatusCounts(): array
{
    if (! Schema::connection($this->centralConnectionName())->hasTable('tenant_product_subscriptions')) {
        return [
            'total' => 0,
            'active' => 0,
            'trialing' => 0,
            'past_due' => 0,
            'suspended' => 0,
            'cancelled' => 0,
            'expired' => 0,
        ];
    }

    $rows = DB::connection($this->centralConnectionName())
        ->table('tenant_product_subscriptions')
        ->select('status', DB::raw('count(*) as aggregate'))
        ->groupBy('status')
        ->get();

    return [
        'total' => (int) $rows->sum('aggregate'),
        'active' => (int) optional($rows->firstWhere('status', 'active'))->aggregate,
        'trialing' => (int) optional($rows->firstWhere('status', 'trialing'))->aggregate,
        'past_due' => (int) optional($rows->firstWhere('status', 'past_due'))->aggregate,
        'suspended' => (int) optional($rows->firstWhere('status', 'suspended'))->aggregate,
        'cancelled' => (int) optional($rows->firstWhere('status', 'cancelled'))->aggregate,
        'expired' => (int) optional($rows->firstWhere('status', 'expired'))->aggregate,
    ];
}

protected function normalizedTenantData(Model $tenant): array
{
    $data = $tenant->getAttribute('data');

    if (is_string($data)) {
        $decoded = json_decode($data, true);
        $data = is_array($decoded) ? $decoded : [];
    } elseif (is_object($data)) {
        $data = (array) $data;
    } elseif (! is_array($data)) {
        $data = [];
    }

    $attributes = Arr::except($tenant->getAttributes(), [
        'data',
    ]);

    return [
        'attributes' => $attributes,
        'data' => $data,
    ];
}

protected function ownerSnapshot(array $tenantData): array
{
    return [
        'company_name' => $this->firstFilledValue($tenantData, ['company_name', 'business_name', 'company', 'name']),
        'owner_name' => $this->firstFilledValue($tenantData, ['owner_name', 'admin_name', 'contact_name', 'name']),
        'owner_email' => $this->firstFilledValue($tenantData, ['owner_email', 'admin_email', 'email']),
        'phone' => $this->firstFilledValue($tenantData, ['phone', 'mobile', 'owner_phone']),
        'country' => $this->firstFilledValue($tenantData, ['country_name', 'country']),
        'state' => $this->firstFilledValue($tenantData, ['state_name', 'state']),
        'city' => $this->firstFilledValue($tenantData, ['city_name', 'city']),
        'address' => $this->firstFilledValue($tenantData, ['address', 'street_address']),
    ];
}

protected function tenantDiagnostics(
    Model $tenant,
    ?array $row,
    Collection $domains,
    ?array $subscription,
    Collection $productSubscriptions,
    array $tenantData
): array {
    $attributes = $tenantData['attributes'] ?? [];
    $data = $tenantData['data'] ?? [];

    $databaseName = $attributes['tenancy_db_name']
        ?? $attributes['database']
        ?? $data['database']
        ?? $data['db_name']
        ?? null;

    return [
        'tenant_model_class' => $this->tenantModelClass(),
        'tenant_connection' => $this->tenantConnectionName(),
        'central_connection' => $this->centralConnectionName(),
        'tenant_table' => $this->tenantTableName(),
        'tenant_exists' => true,
        'domains_count' => $domains->count(),
        'has_primary_domain' => ! empty($row['primary_domain']),
        'has_subscription' => ! empty($subscription),
        'product_subscriptions_count' => $productSubscriptions->count(),
        'has_product_subscriptions' => $productSubscriptions->isNotEmpty(),
        'has_plan' => ! empty($subscription['plan_name']),
        'has_gateway' => ! empty($subscription['gateway']),
        'has_gateway_customer_id' => ! empty($subscription['gateway_customer_id']),
        'has_gateway_subscription_id' => ! empty($subscription['gateway_subscription_id']),
        'has_owner_email' => filled($this->firstFilledValue($tenantData, ['owner_email', 'admin_email', 'email'])),
        'database_name_hint' => $databaseName,
        'admin_login_url' => $row['admin_login_url'] ?? null,
    ];
}

protected function firstFilledValue(array $tenantData, array $keys): ?string
{
    foreach ($keys as $key) {
        $value = data_get($tenantData, 'data.' . $key);

        if (filled($value)) {
            return (string) $value;
        }
    }

    return null;
}

protected function domainToUrl(?string $domain): ?string
{
    if (blank($domain)) {
        return null;
    }

    if (str_starts_with($domain, 'http://') || str_starts_with($domain, 'https://')) {
        return $domain;
    }

    return 'https://' . $domain;
}

protected function tenantAdminLoginUrl(?string $domain): ?string
{
    $baseUrl = $this->domainToUrl($domain);

    if (! $baseUrl) {
        return null;
    }

    return rtrim($baseUrl, '/') . '/automotive/admin/login';
}

protected function findTenantOrFail(string $tenantId): Model
{
    $tenantModelClass = $this->tenantModelClass();

    /** @var Model|null $tenant */
    $tenant = $tenantModelClass::query()->find($tenantId);

    if (! $tenant) {
        throw new NotFoundHttpException();
    }

    return $tenant;
}

protected function indexStats(): array
{
    $tenantModelClass = $this->tenantModelClass();

    $totalTenants = $tenantModelClass::query()->count();

    $tenantsWithDomains = 0;
    if ($this->domainsTableExists()) {
        $tenantsWithDomains = (int) DB::connection($this->centralConnectionName())
            ->table('domains')
            ->distinct('tenant_id')
            ->count('tenant_id');
    }

    $latestSubscriptions = collect();
    if ($this->subscriptionsTableExists()) {
        $latestSubscriptions = DB::connection($this->centralConnectionName())
            ->table('subscriptions')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn ($row) => (string) $row->tenant_id)
            ->map(fn (Collection $group) => $group->first());
    }

    return [
        'total_tenants' => $totalTenants,
        'tenants_with_domains' => $tenantsWithDomains,
        'active_subscriptions' => $latestSubscriptions->filter(fn ($row) => ($row->status ?? null) === 'active')->count(),
        'trialing_subscriptions' => $latestSubscriptions->filter(fn ($row) => ($row->status ?? null) === 'trialing')->count(),
        'past_due_subscriptions' => $latestSubscriptions->filter(fn ($row) => ($row->status ?? null) === 'past_due')->count(),
        'suspended_subscriptions' => $latestSubscriptions->filter(fn ($row) => ($row->status ?? null) === 'suspended')->count(),
    ];
}

protected function filterPlans(): Collection
{
    return $this->lifecycleService->availablePlans()
        ->map(function ($plan): array {
            return [
                'id' => (int) $plan->id,
                'label' => (string) ($plan->name ?: $plan->slug ?: ('Plan #' . $plan->id)),
            ];
        });
}

protected function gatewayOptions(): array
{
    if (! $this->subscriptionsTableExists()) {
        return [];
    }

    return DB::connection($this->centralConnectionName())
        ->table('subscriptions')
        ->whereNotNull('gateway')
        ->where('gateway', '!=', '')
        ->distinct()
        ->orderBy('gateway')
        ->pluck('gateway')
        ->map(fn ($gateway) => (string) $gateway)
        ->values()
        ->all();
}

protected function productsTableExists(): bool
{
    return Schema::connection($this->centralConnectionName())->hasTable('products');
}
}
