<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Automotive\Admin\StoreProductRoleRequest;
use App\Http\Requests\Automotive\Admin\UpdateProductRoleRequest;
use App\Http\Requests\Automotive\Admin\UpdateRolePermissionsRequest;
use App\Models\ProductRole;
use App\Services\Tenancy\ProductPermissionService;
use App\Services\Tenancy\ProductRoleManagementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ProductRoleController extends Controller
{
    public function __construct(
        protected ProductRoleManagementService $roles,
        protected ProductPermissionService $permissions
    ) {
    }

    public function index(Request $request): View
    {
        return view('automotive.admin.access.roles.index', [
            'page' => 'access-control',
            'roles' => $this->roles->listRoles($request->only(['search', 'product_key', 'status'])),
            'productOptions' => $this->roles->productOptions(),
            'filters' => $request->only(['search', 'product_key', 'status']),
        ]);
    }

    public function create(): View
    {
        return view('automotive.admin.access.roles.create', [
            'page' => 'access-control',
            'role' => new ProductRole([
                'product_key' => $this->roles->productOptions()->first()['key'] ?? 'automotive_service',
                'is_active' => true,
                'metadata' => ['is_template' => false, 'sort_order' => 0],
            ]),
            'productOptions' => $this->roles->productOptions(),
        ]);
    }

    public function store(StoreProductRoleRequest $request): RedirectResponse
    {
        $this->authorizeRolesManage();

        try {
            $role = $this->roles->createRole($request->validated());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['name' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('automotive.admin.access.roles.permissions.edit', $role)
            ->with('success', __('access.role_created'));
    }

    public function edit(ProductRole $role): View
    {
        $this->authorizeTenantRole($role);

        return view('automotive.admin.access.roles.edit', [
            'page' => 'access-control',
            'role' => $role,
            'productOptions' => $this->roles->productOptions(),
        ]);
    }

    public function update(UpdateProductRoleRequest $request, ProductRole $role): RedirectResponse
    {
        $this->authorizeRolesManage();

        try {
            $this->roles->updateRole($role, $request->validated());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['name' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('automotive.admin.access.roles.index')
            ->with('success', __('access.role_updated'));
    }

    public function destroy(ProductRole $role): RedirectResponse
    {
        $this->authorizeRolesManage();

        try {
            $this->roles->deleteRole($role);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['role' => $exception->getMessage()]);
        }

        return redirect()
            ->route('automotive.admin.access.roles.index')
            ->with('success', __('access.role_deleted'));
    }

    public function duplicate(ProductRole $role): RedirectResponse
    {
        $this->authorizeRolesManage();

        $copy = $this->roles->duplicateRole($role);

        return redirect()
            ->route('automotive.admin.access.roles.edit', $copy)
            ->with('success', __('access.role_duplicated'));
    }

    public function editPermissions(ProductRole $role): View
    {
        $this->authorizeTenantRole($role);
        $role->load('permissions');
        $groupedPermissions = $this->roles->groupedPermissionsForProduct($role->product_key);

        return view('automotive.admin.access.roles.permissions', [
            'page' => 'access-control',
            'role' => $role,
            'selectedPermissionKeys' => $role->permissions->pluck('permission_key')->all(),
            'groupedPermissions' => $groupedPermissions,
            'totalPermissions' => $groupedPermissions->sum(fn (array $group): int => $group['permissions']->count()),
            'assignedUsersCount' => $this->roles->activeAssignmentCount($role),
        ]);
    }

    public function updatePermissions(UpdateRolePermissionsRequest $request, ProductRole $role): RedirectResponse
    {
        $this->authorizeRolesManage();

        try {
            $this->roles->syncRolePermissions($role, $request->validated('permissions') ?? []);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['permissions' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('automotive.admin.access.roles.permissions.edit', $role)
            ->with('success', __('access.permissions_updated'));
    }

    protected function authorizeTenantRole(ProductRole $role): void
    {
        abort_unless((string) $role->tenant_id === (string) tenant()->id, 404);
    }

    protected function authorizeRolesManage(): void
    {
        $tenantId = (string) tenant()->id;
        $user = auth('automotive_admin')->user();

        abort_unless($user && $this->permissions->can($user, 'automotive_service', 'automotive_service.access.roles.manage', null, $tenantId), 403);
    }
}
