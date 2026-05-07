<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ProductPermission;
use App\Models\ProductRole;
use App\Models\TenantProductBranch;
use App\Models\TenantProductSubscription;
use App\Models\TenantUserProductAccess;
use App\Models\TenantUserProductBranch;
use App\Models\User;
use App\Services\Tenancy\ProductBranchAccessService;
use App\Services\Tenancy\ProductEntitlementService;
use App\Services\Tenancy\TenantUserProductAccessService;
use App\Services\Tenancy\WorkspaceOwnerAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class AccessControlController extends Controller
{
    public function __construct(
        protected ProductEntitlementService $entitlements,
        protected TenantUserProductAccessService $productAccess,
        protected ProductBranchAccessService $branchAccess,
        protected WorkspaceOwnerAccessService $ownerAccess
    ) {
    }

    public function index(): View
    {
        return $this->dashboard('overview');
    }

    public function users(): View
    {
        $tenant = tenant();
        $tenantId = (string) $tenant->id;
        $subscriptions = $this->subscriptions($tenantId);

        return view('automotive.admin.access.users.index', [
            'page' => 'access-control',
            'activePanel' => 'users',
            'tenant' => $tenant,
            'users' => User::query()->orderBy('id')->get(),
            'subscriptions' => $subscriptions,
            'seatUsageRows' => $this->seatUsageRows($subscriptions, $tenantId),
            'userAccessSummary' => $this->userAccessSummary($tenantId),
            'userBranchAccessSummary' => $this->userBranchAccessSummary($tenantId),
            'ownerUserIds' => [1],
            'quickLinks' => $this->quickLinks(),
            'currentUserId' => auth('automotive_admin')->id(),
        ]);
    }

    public function roles(): View
    {
        return $this->dashboard('roles');
    }

    public function branches(): View
    {
        return $this->dashboard('branches');
    }

    public function products(): View
    {
        return $this->dashboard('products');
    }

    public function diagnostics(): View
    {
        return $this->dashboard('diagnostics');
    }

    public function editUserProducts(User $user): View
    {
        $tenant = tenant();
        $tenantId = (string) $tenant->id;
        $subscriptions = $this->subscriptions($tenantId);

        return view('automotive.admin.access.users.products', [
            'page' => 'access-control',
            'tenant' => $tenant,
            'user' => $user,
            'productRows' => $this->userProductRows($user, $subscriptions, $tenantId),
            'isPrimaryOwner' => $this->isPrimaryWorkspaceOwner($user),
            'ownerConsumesSeat' => $this->ownerAccess->ownerConsumesSeat(),
        ]);
    }

    public function updateUserProducts(Request $request, User $user): RedirectResponse
    {
        $tenantId = (string) tenant()->id;
        $subscriptions = $this->subscriptions($tenantId);
        $allowedProductKeys = $subscriptions
            ->map(fn (TenantProductSubscription $subscription): string => (string) ($subscription->product_key ?: $subscription->product?->code))
            ->filter()
            ->values();
        $requestedProducts = collect($request->input('products', []))
            ->map(fn (string $productKey): string => trim($productKey))
            ->filter()
            ->intersect($allowedProductKeys)
            ->values();

        if ($this->isPrimaryWorkspaceOwner($user) && ! $requestedProducts->contains('automotive_service')) {
            return back()
                ->withErrors(['products' => __('access.cannot_remove_owner_product_access')])
                ->withInput();
        }

        try {
            DB::transaction(function () use ($user, $tenantId, $allowedProductKeys, $requestedProducts): void {
                foreach ($requestedProducts as $productKey) {
                    $this->productAccess->assertCanGrantAccess($user, $productKey, $tenantId);
                }

                foreach ($allowedProductKeys as $productKey) {
                    if ($requestedProducts->contains($productKey)) {
                        $this->productAccess->grantAccess($user, $productKey, auth('automotive_admin')->user(), [
                            'source' => 'access_control_ui',
                        ]);

                        continue;
                    }

                    $this->productAccess->revokeAccess($user, $productKey);
                }
            });
        } catch (RuntimeException $exception) {
            $message = str_contains($exception->getMessage(), 'No available seats')
                ? __('access.seat_limit_reached')
                : $exception->getMessage();

            return back()
                ->withErrors(['products' => $message])
                ->withInput();
        }

        return redirect()
            ->route('automotive.admin.access.users.products.edit', $user)
            ->with('success', __('access.product_access_updated'));
    }

    public function syncOwnerAccess(User $user): RedirectResponse
    {
        abort_unless($this->isPrimaryWorkspaceOwner($user), 404);
        abort_unless($this->isPrimaryWorkspaceOwner(auth('automotive_admin')->user()), 403);

        $summary = $this->ownerAccess->syncOwnerAccess($user);

        return back()->with('success', __('access.owner_access_synced', $summary));
    }

    public function productBranches(string $productKey): View
    {
        $tenant = tenant();
        $tenantId = (string) $tenant->id;
        $subscription = $this->subscriptions($tenantId)
            ->first(fn (TenantProductSubscription $subscription): bool => (string) ($subscription->product_key ?: $subscription->product?->code) === $productKey);

        abort_if(! $subscription, 404);

        return view('automotive.admin.access.products.branches', [
            'page' => 'access-control',
            'tenant' => $tenant,
            'productKey' => $productKey,
            'subscription' => $subscription,
            'branchRows' => $this->productBranchRows($productKey, $tenantId),
            'usage' => collect($this->branchUsageRows(collect([$subscription]), $tenantId))->first(),
        ]);
    }

    public function updateProductBranches(Request $request, string $productKey): RedirectResponse
    {
        $tenantId = (string) tenant()->id;
        $branchIds = Branch::query()->pluck('id')->map(fn ($id): int => (int) $id);
        $requestedBranchIds = collect($request->input('branches', []))
            ->map(fn ($id): int => (int) $id)
            ->intersect($branchIds)
            ->values();

        $currentEnabledIds = TenantProductBranch::query()
            ->where('tenant_id', $tenantId)
            ->where('product_key', $productKey)
            ->enabled()
            ->pluck('branch_id')
            ->map(fn ($id): int => (int) $id);

        $activeUserBranchAssignments = TenantUserProductBranch::query()
            ->where('tenant_id', $tenantId)
            ->where('product_key', $productKey)
            ->enabled()
            ->exists();

        if ($activeUserBranchAssignments && $requestedBranchIds->isEmpty()) {
            return back()
                ->withErrors(['branches' => __('access.cannot_disable_last_product_branch')])
                ->withInput();
        }

        try {
            DB::transaction(function () use ($productKey, $requestedBranchIds, $currentEnabledIds): void {
                foreach ($requestedBranchIds as $branchId) {
                    $this->branchAccess->enableBranch((int) $branchId, $productKey, [
                        'source' => 'access_control_ui',
                    ]);
                }

                foreach ($currentEnabledIds->diff($requestedBranchIds) as $branchId) {
                    $this->branchAccess->disableBranch((int) $branchId, $productKey);
                }
            });
        } catch (RuntimeException $exception) {
            $message = str_contains($exception->getMessage(), 'No available branches')
                ? __('access.branch_limit_reached')
                : $exception->getMessage();

            return back()
                ->withErrors(['branches' => $message])
                ->withInput();
        }

        return redirect()
            ->route('automotive.admin.access.products.branches.index', $productKey)
            ->with('success', __('access.product_branches_updated'));
    }

    public function editUserBranches(User $user): View
    {
        $tenantId = (string) tenant()->id;

        return view('automotive.admin.access.users.branches', [
            'page' => 'access-control',
            'user' => $user,
            'productRows' => $this->userBranchRows($user, $tenantId),
            'isPrimaryOwner' => $this->isPrimaryWorkspaceOwner($user),
        ]);
    }

    public function updateUserBranches(Request $request, User $user): RedirectResponse
    {
        $tenantId = (string) tenant()->id;
        $activeProductKeys = TenantUserProductAccess::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->active()
            ->pluck('product_key')
            ->values();

        $requested = collect($request->input('branches', []));

        if ($requested->keys()->diff($activeProductKeys)->isNotEmpty()) {
            return back()
                ->withErrors(['branches' => __('access.invalid_branch_assignment')])
                ->withInput();
        }

        try {
            DB::transaction(function () use ($user, $tenantId, $activeProductKeys, $requested): void {
                foreach ($activeProductKeys as $productKey) {
                    $enabledBranchIds = $this->branchAccess
                        ->enabledBranchesForProduct($productKey, $tenantId)
                        ->pluck('id')
                        ->map(fn ($id): int => (int) $id);

                    $requestedBranchIds = collect($requested->get($productKey, []))
                        ->map(fn ($id): int => (int) $id)
                        ->values();

                    if ($requestedBranchIds->diff($enabledBranchIds)->isNotEmpty()) {
                        throw new RuntimeException('Branch is not enabled for product.');
                    }

                    foreach ($requestedBranchIds as $branchId) {
                        $this->branchAccess->grantUserBranchAccess($user, (int) $branchId, $productKey, 'member', [
                            'source' => 'access_control_ui',
                        ]);
                    }

                    $currentAssignedIds = TenantUserProductBranch::query()
                        ->where('tenant_id', $tenantId)
                        ->where('user_id', $user->id)
                        ->where('product_key', $productKey)
                        ->enabled()
                        ->pluck('branch_id')
                        ->map(fn ($id): int => (int) $id);

                    foreach ($currentAssignedIds->diff($requestedBranchIds) as $branchId) {
                        $this->branchAccess->revokeUserBranchAccess($user, (int) $branchId, $productKey);
                    }
                }
            });
        } catch (RuntimeException) {
            return back()
                ->withErrors(['branches' => __('access.invalid_branch_assignment')])
                ->withInput();
        }

        return redirect()
            ->route('automotive.admin.access.users.branches.edit', $user)
            ->with('success', __('access.user_branches_updated'));
    }

    private function dashboard(string $activePanel): View
    {
        $tenant = tenant();
        $tenantId = (string) $tenant->id;
        $subscriptions = $this->subscriptions($tenantId);
        $primaryProductKey = $this->primaryProductKey($subscriptions);

        return view('automotive.admin.access.index', [
            'page' => 'access-control',
            'activePanel' => $activePanel,
            'tenant' => $tenant,
            'usersCount' => User::query()->count(),
            'branchesCount' => Schema::hasTable('branches') ? Branch::query()->count() : 0,
            'subscriptions' => $subscriptions,
            'rolesCount' => $this->tableCount(ProductRole::class, 'product_roles', $tenantId),
            'permissionsCount' => $this->tableCount(ProductPermission::class, 'product_permissions', $tenantId),
            'productAccessCount' => $this->tenantTableCount(TenantUserProductAccess::class, 'tenant_user_product_access', $tenantId),
            'branchAccessCount' => $this->tenantTableCount(TenantUserProductBranch::class, 'tenant_user_product_branches', $tenantId),
            'productBranchCount' => $this->tenantTableCount(TenantProductBranch::class, 'tenant_product_branches', $tenantId),
            'seatUsageRows' => $this->seatUsageRows($subscriptions, $tenantId),
            'branchUsageRows' => $this->branchUsageRows($subscriptions, $tenantId),
            'usersWithoutBranchAccessCount' => $this->usersWithoutBranchAccessCount($tenantId),
            'branchLimitReachedRows' => collect($this->branchUsageRows($subscriptions, $tenantId))
                ->filter(fn (array $row): bool => $row['available'] !== null && (int) $row['available'] <= 0)
                ->values()
                ->all(),
            'quickLinks' => $this->quickLinks(),
            'primaryProductKey' => $primaryProductKey,
        ]);
    }

    private function subscriptions(string $tenantId): Collection
    {
        return TenantProductSubscription::query()
            ->with(['product', 'plan'])
            ->where('tenant_id', $tenantId)
            ->orderBy('product_key')
            ->get();
    }

    private function primaryProductKey(Collection $subscriptions): string
    {
        return (string) ($subscriptions->firstWhere('product_key', 'automotive_service')?->product_key
            ?? $subscriptions->first()?->product_key
            ?? 'automotive_service');
    }

    private function seatUsageRows(Collection $subscriptions, string $tenantId): array
    {
        return $subscriptions
            ->map(function (TenantProductSubscription $subscription) use ($tenantId): array {
                $productKey = (string) ($subscription->product_key ?: $subscription->product?->code);
                $limit = $this->entitlements->seatLimit($tenantId, $productKey);
                $used = $this->productAccess->countUsedSeats($productKey, $tenantId);

                return [
                    'product_key' => $productKey,
                    'product_name' => $subscription->product?->name ?? $productKey,
                    'plan_name' => $subscription->plan?->name,
                    'status' => $subscription->status,
                    'included' => (int) ($subscription->included_seats ?? 0),
                    'extra' => (int) ($subscription->extra_seats ?? 0),
                    'limit' => $limit,
                    'used' => $used,
                    'available' => $limit === null ? null : max(0, $limit - $used),
                ];
            })
            ->values()
            ->all();
    }

    private function branchUsageRows(Collection $subscriptions, string $tenantId): array
    {
        return $subscriptions
            ->map(function (TenantProductSubscription $subscription) use ($tenantId): array {
                $productKey = (string) ($subscription->product_key ?: $subscription->product?->code);
                $limit = $this->entitlements->branchLimit($tenantId, $productKey);
                $enabled = $this->branchAccess->countEnabledBranches($productKey, $tenantId);

                return [
                    'product_key' => $productKey,
                    'product_name' => $subscription->product?->name ?? $productKey,
                    'limit' => $limit,
                    'enabled' => $enabled,
                    'available' => $limit === null ? null : max(0, $limit - $enabled),
                ];
            })
            ->values()
            ->all();
    }

    private function userAccessSummary(string $tenantId): array
    {
        if (! Schema::hasTable('tenant_user_product_access')) {
            return [];
        }

        return TenantUserProductAccess::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->get()
            ->groupBy('user_id')
            ->map(fn (Collection $accessRows): array => $accessRows->pluck('product_key')->values()->all())
            ->all();
    }

    private function userBranchAccessSummary(string $tenantId): array
    {
        if (! Schema::hasTable('tenant_user_product_branches')) {
            return [];
        }

        return TenantUserProductBranch::query()
            ->where('tenant_id', $tenantId)
            ->enabled()
            ->get()
            ->groupBy('user_id')
            ->map(fn (Collection $rows): array => [
                'count' => $rows->count(),
                'product_keys' => $rows->pluck('product_key')->unique()->values()->all(),
            ])
            ->all();
    }

    private function usersWithoutBranchAccessCount(string $tenantId): int
    {
        $productAccessUserIds = TenantUserProductAccess::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->pluck('user_id')
            ->unique();

        if ($productAccessUserIds->isEmpty()) {
            return 0;
        }

        $branchAccessUserIds = TenantUserProductBranch::query()
            ->where('tenant_id', $tenantId)
            ->enabled()
            ->pluck('user_id')
            ->unique();

        return $productAccessUserIds->diff($branchAccessUserIds)->count();
    }

    private function userProductRows(User $user, Collection $subscriptions, string $tenantId): array
    {
        $activeAccess = Schema::hasTable('tenant_user_product_access')
            ? TenantUserProductAccess::query()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $user->id)
                ->active()
                ->pluck('product_key')
            : collect();

        return collect($this->seatUsageRows($subscriptions, $tenantId))
            ->map(function (array $row) use ($activeAccess): array {
                $row['has_access'] = $activeAccess->contains($row['product_key']);
                $row['seat_blocked'] = ! $row['has_access']
                    && $row['available'] !== null
                    && (int) $row['available'] <= 0;

                return $row;
            })
            ->values()
            ->all();
    }

    private function productBranchRows(string $productKey, string $tenantId): array
    {
        $enabledBranchIds = TenantProductBranch::query()
            ->where('tenant_id', $tenantId)
            ->where('product_key', $productKey)
            ->enabled()
            ->pluck('branch_id')
            ->map(fn ($id): int => (int) $id);

        return Branch::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $branch): array => [
                'branch' => $branch,
                'is_enabled' => $enabledBranchIds->contains((int) $branch->id),
            ])
            ->values()
            ->all();
    }

    private function userBranchRows(User $user, string $tenantId): array
    {
        if ($this->isPrimaryWorkspaceOwner($user)) {
            return $this->subscriptions($tenantId)
                ->filter(fn (TenantProductSubscription $subscription): bool => in_array((string) $subscription->status, ['active', 'trialing'], true))
                ->map(function (TenantProductSubscription $subscription) use ($tenantId): array {
                    $productKey = (string) ($subscription->product_key ?: $subscription->product?->code);

                    return [
                        'product_key' => $productKey,
                        'enabled_branches' => $this->branchAccess->enabledBranchesForProduct($productKey, $tenantId),
                        'assigned_branch_ids' => collect(),
                        'owner_implicit' => true,
                    ];
                })
                ->values()
                ->all();
        }

        return TenantUserProductAccess::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->active()
            ->orderBy('product_key')
            ->get()
            ->map(function (TenantUserProductAccess $access) use ($user, $tenantId): array {
                $productKey = (string) $access->product_key;
                $assignedBranchIds = TenantUserProductBranch::query()
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $user->id)
                    ->where('product_key', $productKey)
                    ->enabled()
                    ->pluck('branch_id')
                    ->map(fn ($id): int => (int) $id);

                return [
                    'product_key' => $productKey,
                    'enabled_branches' => $this->branchAccess->enabledBranchesForProduct($productKey, $tenantId),
                    'assigned_branch_ids' => $assignedBranchIds,
                ];
            })
            ->values()
            ->all();
    }

    private function isPrimaryWorkspaceOwner(?User $user): bool
    {
        return $this->ownerAccess->isWorkspaceOwner($user);
    }

    private function tableCount(string $modelClass, string $table, string $tenantId): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return (int) $modelClass::query()
            ->where('tenant_id', $tenantId)
            ->count();
    }

    private function tenantTableCount(string $modelClass, string $table, string $tenantId): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return (int) $modelClass::query()
            ->where('tenant_id', $tenantId)
            ->count();
    }

    private function quickLinks(): array
    {
        return [
            [
                'key' => 'users',
                'label' => __('access.users'),
                'description' => __('access.users_card_hint'),
                'icon' => 'isax-profile-2user',
                'route' => 'automotive.admin.access.users.index',
            ],
            [
                'key' => 'products',
                'label' => __('access.products'),
                'description' => __('access.products_card_hint'),
                'icon' => 'isax-layer',
                'route' => 'automotive.admin.access.products.index',
            ],
            [
                'key' => 'branches',
                'label' => __('access.branches'),
                'description' => __('access.branches_card_hint'),
                'icon' => 'isax-buildings',
                'route' => 'automotive.admin.access.branches.index',
            ],
            [
                'key' => 'roles',
                'label' => __('access.roles'),
                'description' => __('access.roles_card_hint'),
                'icon' => 'isax-shield-tick',
                'route' => 'automotive.admin.access.roles.index',
            ],
            [
                'key' => 'diagnostics',
                'label' => __('access.diagnostics'),
                'description' => __('access.diagnostics_card_hint'),
                'icon' => 'isax-search-status',
                'route' => 'automotive.admin.access.diagnostics.index',
            ],
        ];
    }
}
