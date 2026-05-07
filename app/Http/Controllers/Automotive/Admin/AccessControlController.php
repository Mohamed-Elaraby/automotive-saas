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
        protected ProductBranchAccessService $branchAccess
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

    private function isPrimaryWorkspaceOwner(User $user): bool
    {
        return (int) $user->id === 1;
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
