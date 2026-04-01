<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminActivityLogger;
use App\Services\Admin\AdminTenantLifecycleService;
use App\Services\Admin\TenantImpersonationService;
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
    $tenantData = $this->normalizedTenantData($tenant);
    $ownerSnapshot = $this->ownerSnapshot($tenantData);
    $diagnostics = $this->tenantDiagnostics($tenant, $row, $domains, $subscription, $tenantData);
    $availablePlans = $this->lifecycleService->availablePlans();

    return view('admin.tenants.show', [
        'tenant' => $tenant,
        'row' => $row,
        'domains' => $domains,
        'subscription' => $subscription,
        'tenantData' => $tenantData,
        'ownerSnapshot' => $ownerSnapshot,
        'diagnostics' => $diagnostics,
        'availablePlans' => $availablePlans,
    ]);
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
}
