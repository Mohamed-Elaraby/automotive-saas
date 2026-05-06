<?php

namespace App\Http\Controllers\Automotive\Admin\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\MaintenanceApprovalRequest;
use App\Models\User;
use App\Services\Automotive\Maintenance\MaintenanceAuditService;
use App\Services\Automotive\Maintenance\MaintenancePermissionService;
use App\Services\Automotive\Maintenance\MaintenanceSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceSettingsController extends Controller
{
    public function __construct(
        protected MaintenanceSettingsService $settings,
        protected MaintenancePermissionService $permissions,
        protected MaintenanceAuditService $audit
    ) {
    }

    public function index(): View
    {
        $this->authorizeSettings();

        return view('automotive.admin.maintenance.settings.index', [
            'settings' => $this->settings->grouped(),
            'permissionDefinitions' => $this->permissions->definitions(),
            'roles' => $this->permissions->roles(),
            'users' => User::query()->orderBy('name')->limit(150)->get(),
            'auditEntries' => $this->audit->recent(),
            'approvalRequests' => MaintenanceApprovalRequest::query()
                ->with(['branch', 'requester', 'decider'])
                ->latest('id')
                ->limit(50)
                ->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeSettings();

        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable'],
        ]);

        $changes = $this->settings->updateMany($validated['settings'], auth('automotive_admin')->id());

        $this->audit->record('settings.updated', 'settings', [
            'user_id' => auth('automotive_admin')->id(),
            'old_values' => $changes['before'],
            'new_values' => $changes['after'],
        ]);

        return back()->with('success', __('maintenance.messages.settings_saved'));
    }

    public function updateUserPermissions(Request $request, User $user): RedirectResponse
    {
        abort_unless($this->permissions->can(auth('automotive_admin')->user(), 'maintenance.users.manage'), 403);

        $validated = $request->validate([
            'maintenance_role' => ['nullable', 'string', 'max:80'],
            'maintenance_permissions' => ['nullable', 'array'],
            'maintenance_permissions.*' => ['string', 'max:120'],
        ]);

        $old = [
            'maintenance_role' => $user->maintenance_role,
            'maintenance_permissions' => $user->maintenance_permissions,
        ];

        $allowed = array_keys($this->permissions->definitions());
        $selected = collect($validated['maintenance_permissions'] ?? [])
            ->filter(fn (string $permission): bool => in_array($permission, $allowed, true) || $permission === 'maintenance.*')
            ->values()
            ->all();

        $user->forceFill([
            'maintenance_role' => $validated['maintenance_role'] ?? null,
            'maintenance_permissions' => $selected === [] ? null : $selected,
        ])->save();

        $this->audit->record('permissions.updated', 'users', [
            'user_id' => auth('automotive_admin')->id(),
            'auditable' => $user,
            'old_values' => $old,
            'new_values' => [
                'maintenance_role' => $user->maintenance_role,
                'maintenance_permissions' => $user->maintenance_permissions,
            ],
        ]);

        return back()->with('success', __('maintenance.messages.permissions_saved'));
    }

    protected function authorizeSettings(): void
    {
        abort_unless($this->permissions->can(auth('automotive_admin')->user(), 'maintenance.settings.manage'), 403);
    }
}
