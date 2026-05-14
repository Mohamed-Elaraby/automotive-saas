<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Automotive\Admin\UpdateUserProductRolesRequest;
use App\Models\User;
use App\Services\Tenancy\EffectiveUserAccessService;
use App\Services\Tenancy\ProductPermissionService;
use App\Services\Tenancy\UserRoleAssignmentService;
use App\Services\Tenancy\WorkspaceOwnerAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class UserAccessProfileController extends Controller
{
    public function __construct(
        protected EffectiveUserAccessService $effectiveAccess,
        protected UserRoleAssignmentService $roleAssignments,
        protected WorkspaceOwnerAccessService $ownerAccess,
        protected ProductPermissionService $permissions
    ) {
    }

    public function show(User $user): View
    {
        return view('automotive.admin.access.users.show', [
            'page' => 'access-control',
            'user' => $user,
            'profile' => $this->effectiveAccess->profile($user),
            'currentUserId' => auth('automotive_admin')->id(),
            'isOwner' => $this->ownerAccess->isWorkspaceOwner($user),
        ]);
    }

    public function editRoles(User $user): View
    {
        return view('automotive.admin.access.users.roles', [
            'page' => 'access-control',
            'user' => $user,
            'roleRows' => $this->effectiveAccess->roleAssignmentRows($user),
            'currentUserId' => auth('automotive_admin')->id(),
            'isOwner' => $this->ownerAccess->isWorkspaceOwner($user),
        ]);
    }

    public function updateRoles(UpdateUserProductRolesRequest $request, User $user): RedirectResponse
    {
        $tenantId = (string) tenant()->id;
        $actor = auth('automotive_admin')->user();

        abort_unless($actor && $this->permissions->can($actor, 'automotive_service', 'automotive_service.access.roles.manage', null, $tenantId), 403);

        try {
            $this->roleAssignments->syncUserProductRoles($user, $request->validated('roles') ?? []);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['roles' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('automotive.admin.access.users.show', $user)
            ->with('success', __('access.roles_updated_successfully'));
    }
}
