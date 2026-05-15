<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Tenancy\AccessAuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AccessAuditController extends Controller
{
    public function __construct(
        protected AccessAuditService $audit
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $request->only([
            'actor_user_id',
            'target_user_id',
            'product_key',
            'branch_id',
            'event_key',
            'date_from',
            'date_to',
        ]);

        return view('automotive.admin.access.audit.index', [
            'page' => 'access-control',
            'logs' => $this->audit->paginate($filters),
            'summary' => $this->audit->summarize($filters),
            'filters' => $filters,
            'users' => User::query()->orderBy('name')->get(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'products' => TenantProductSubscription::query()
                ->where('tenant_id', (string) tenant()->id)
                ->orderBy('product_key')
                ->pluck('product_key')
                ->filter()
                ->values(),
            'eventOptions' => $this->eventOptions(),
        ]);
    }

    protected function eventOptions(): array
    {
        return [
            'product_access.granted',
            'product_access.revoked',
            'branch_access.granted',
            'branch_access.revoked',
            'product_branch.enabled',
            'product_branch.disabled',
            'role.assigned',
            'role.removed',
            'role.created',
            'role.updated',
            'role.deleted',
            'role.duplicated',
            'role_permissions.updated',
            'owner_access.synced',
            'forbidden_action.blocked',
            'permission.denied',
        ];
    }
}
