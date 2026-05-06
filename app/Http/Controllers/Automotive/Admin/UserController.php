<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Automotive\Maintenance\MaintenancePermissionService;
use App\Services\Tenancy\TenantLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(
        protected TenantLimitService $tenantLimitService,
        protected MaintenancePermissionService $maintenancePermissionService
    ) {
    }

    public function index()
    {
        $users = User::query()
            ->orderBy('id')
            ->get();

        $tenant = tenant();
        $limitInfo = null;

        if ($tenant) {
            $limitInfo = $this->tenantLimitService->getDecision(
                $tenant->id,
                'max_users',
                $users->count()
            );
        }

        return view('automotive.admin.users.index', [
            'users' => $users,
            'limitInfo' => $limitInfo,
            'currentUserId' => auth('automotive_admin')->id(),
            'roleLabels' => $this->translatedMaintenanceRoles(),
            'permissionSummaries' => $users
                ->mapWithKeys(fn (User $user): array => [$user->id => $this->maintenancePermissionService->summary($user)])
                ->all(),
        ]);
    }

    public function create()
    {
        return view('automotive.admin.users.create', [
            'user' => new User(),
            'roleLabels' => $this->translatedMaintenanceRoles(),
            'supportsMaintenanceAccess' => $this->supportsMaintenanceAccess(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ];

        if ($this->supportsMaintenanceAccess()) {
            $payload['maintenance_role'] = $data['maintenance_role'] ?? null;
            $payload['maintenance_permissions'] = null;
        }

        User::query()->create($payload);

        return redirect()
            ->route('automotive.admin.users.index')
            ->with('success', __('tenant.user_created_successfully'));
    }

    public function edit(User $user)
    {
        return view('automotive.admin.users.edit', [
            'user' => $user,
            'roleLabels' => $this->translatedMaintenanceRoles(),
            'supportsMaintenanceAccess' => $this->supportsMaintenanceAccess(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate($this->rules($user));

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        if ($this->supportsMaintenanceAccess()) {
            $payload['maintenance_role'] = $data['maintenance_role'] ?? null;
        }

        $user->update($payload);

        return redirect()
            ->route('automotive.admin.users.index')
            ->with('success', __('tenant.user_updated_successfully'));
    }

    public function destroy(User $user)
    {
        $currentUserId = auth('automotive_admin')->id();

        if ($currentUserId && (int) $user->id === (int) $currentUserId) {
            return redirect()
                ->route('automotive.admin.users.index')
                ->withErrors([
                    'delete' => __('tenant.cannot_delete_current_user'),
                ]);
        }

        if ((int) $user->id === 1) {
            return redirect()
                ->route('automotive.admin.users.index')
                ->withErrors([
                    'delete' => __('tenant.cannot_delete_workspace_owner'),
                ]);
        }

        $user->delete();

        return redirect()
            ->route('automotive.admin.users.index')
            ->with('success', __('tenant.user_deleted_successfully'));
    }

    protected function rules(?User $user = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:6', 'confirmed'],
        ];

        if ($this->supportsMaintenanceAccess()) {
            $rules['maintenance_role'] = ['nullable', 'string', Rule::in(array_keys($this->maintenancePermissionService->roles()))];
        }

        return $rules;
    }

    protected function translatedMaintenanceRoles(): array
    {
        return collect($this->maintenancePermissionService->roles())
            ->mapWithKeys(fn (string $label, string $role): array => [
                $role => __("tenant.maintenance_roles.{$role}"),
            ])
            ->all();
    }

    protected function supportsMaintenanceAccess(): bool
    {
        return Schema::hasColumn('users', 'maintenance_role');
    }
}
