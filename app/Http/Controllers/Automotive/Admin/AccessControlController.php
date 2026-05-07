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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

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
        return $this->dashboard('users');
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
