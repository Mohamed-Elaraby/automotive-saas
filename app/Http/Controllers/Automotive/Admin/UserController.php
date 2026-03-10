<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Tenancy\TenantPlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(
        protected TenantPlanService $tenantPlanService
    ) {
    }

public function index()
{
    $users = User::query()
        ->orderBy('id')
        ->get();

    $tenant = tenant();
    $limitInfo = $tenant
        ? $this->tenantPlanService->getUserLimitDecision($tenant->id)
        : null;

    return view('automotive.admin.users.index', compact('users', 'limitInfo'));
}

public function create()
{
    return view('automotive.admin.users.create', [
        'user' => new User(),
    ]);
}

public function store(Request $request)
{
    $data = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        'password' => ['required', 'string', 'min:6', 'confirmed'],
    ]);

    User::query()->create([
        'name' => $data['name'],
        'email' => $data['email'],
        'password' => Hash::make($data['password']),
    ]);

    return redirect()
        ->route('automotive.admin.users.index')
        ->with('success', 'User created successfully.');
}

public function edit(User $user)
{
    return view('automotive.admin.users.edit', compact('user'));
}

public function update(Request $request, User $user)
{
    $data = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        'password' => ['nullable', 'string', 'min:6', 'confirmed'],
    ]);

    $payload = [
        'name' => $data['name'],
        'email' => $data['email'],
    ];

    if (! empty($data['password'])) {
        $payload['password'] = Hash::make($data['password']);
    }

    $user->update($payload);

    return redirect()
        ->route('automotive.admin.users.index')
        ->with('success', 'User updated successfully.');
}

public function destroy(User $user)
{
    $currentUserId = auth('automotive_admin')->id();

    if ($currentUserId && (int) $user->id === (int) $currentUserId) {
        return redirect()
            ->route('automotive.admin.users.index')
            ->withErrors([
                'delete' => 'You cannot delete your own currently logged-in account.',
            ]);
    }

    $user->delete();

    return redirect()
        ->route('automotive.admin.users.index')
        ->with('success', 'User deleted successfully.');
}
}
