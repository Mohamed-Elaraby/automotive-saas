<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Tenancy\AccessDiagnosticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AccessDiagnosticsController extends Controller
{
    public function __construct(
        protected AccessDiagnosticsService $diagnostics
    ) {
    }

    public function index(Request $request): View
    {
        $users = User::query()->orderBy('name')->get();
        $products = TenantProductSubscription::query()
            ->where('tenant_id', (string) tenant()->id)
            ->orderBy('product_key')
            ->pluck('product_key')
            ->filter()
            ->values();
        $branches = Branch::query()->where('is_active', true)->orderBy('name')->get();
        $result = null;

        if ($request->filled('user_id')) {
            $user = User::query()->findOrFail((int) $request->input('user_id'));
            $productKey = (string) ($request->input('product_key') ?: $products->first() ?: 'automotive_service');
            $branchId = $request->input('branch_id') ? (int) $request->input('branch_id') : null;
            $permissionKey = trim((string) $request->input('permission_key'));
            $routeName = trim((string) $request->input('route_name'));

            $result = match (true) {
                $permissionKey !== '' => $this->diagnostics->diagnosePermission($user, $productKey, $permissionKey, $branchId),
                $routeName !== '' => $this->diagnostics->diagnoseRoute($user, $routeName),
                $branchId !== null => $this->diagnostics->diagnoseBranchAccess($user, $productKey, $branchId),
                default => $this->diagnostics->diagnoseProductAccess($user, $productKey),
            };
        }

        return view('automotive.admin.access.diagnostics.index', [
            'page' => 'access-control',
            'users' => $users,
            'products' => $products,
            'branches' => $branches,
            'result' => $result,
            'filters' => $request->only(['user_id', 'product_key', 'branch_id', 'permission_key', 'route_name']),
        ]);
    }

    public function user(User $user): View
    {
        return view('automotive.admin.access.diagnostics.user', [
            'page' => 'access-control',
            'user' => $user,
            'result' => $this->diagnostics->diagnoseUserAccess($user),
        ]);
    }

    public function permission(Request $request): View
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'product_key' => ['required', 'string'],
            'permission_key' => ['required', 'string'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $user = User::query()->findOrFail((int) $validated['user_id']);

        return view('automotive.admin.access.diagnostics.permission', [
            'page' => 'access-control',
            'user' => $user,
            'result' => $this->diagnostics->diagnosePermission(
                $user,
                $validated['product_key'],
                $validated['permission_key'],
                $validated['branch_id'] ?? null
            ),
        ]);
    }

    public function route(Request $request): View
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'route_name' => ['required', 'string'],
        ]);

        $user = User::query()->findOrFail((int) $validated['user_id']);

        return view('automotive.admin.access.diagnostics.permission', [
            'page' => 'access-control',
            'user' => $user,
            'result' => $this->diagnostics->diagnoseRoute($user, $validated['route_name']),
        ]);
    }
}
