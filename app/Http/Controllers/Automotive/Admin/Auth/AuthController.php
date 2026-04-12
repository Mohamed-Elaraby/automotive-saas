<?php

namespace App\Http\Controllers\Automotive\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\TenantImpersonationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class AuthController extends Controller
{
    public function __construct(
        protected TenantImpersonationService $tenantImpersonationService
    ) {
    }

    public function landing()
    {
        if (Auth::guard('automotive_admin')->check()) {
            return redirect()->route('automotive.admin.dashboard');
        }

        return $this->showLogin();
    }

    public function showLogin()
    {
        return view('automotive.admin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::guard('automotive_admin')->attempt($credentials, $remember)) {
            return back()
                ->withErrors(['email' => 'Invalid email or password.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->route('automotive.admin.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('automotive_admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('automotive.admin.home');
    }

    public function impersonate(Request $request, string $token)
    {
        try {
            $tenantId = (string) tenant('id');
            $payload = $this->tenantImpersonationService->consume($token, $tenantId);

            $tenantUser = User::query()
                ->where('email', (string) ($payload['target_user_email'] ?? ''))
                ->first();

            if (! $tenantUser) {
                throw new RuntimeException('The tenant user for impersonation was not found in the tenant workspace.');
            }

            Auth::guard('automotive_admin')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->regenerate();

            Auth::guard('automotive_admin')->login($tenantUser);

            $request->session()->put('tenant_admin_impersonation', [
                'active' => true,
                'tenant_id' => $tenantId,
                'tenant_user_email' => (string) ($payload['target_user_email'] ?? ''),
                'tenant_user_name' => (string) ($payload['target_user_name'] ?? ''),
                'central_admin_id' => $payload['central_admin_id'] ?? null,
                'central_admin_email' => $payload['central_admin_email'] ?? null,
                'return_url' => (string) ($payload['return_url'] ?? $this->tenantImpersonationService->centralTenantShowUrl($tenantId)),
                'started_at' => $payload['created_at'] ?? now()->toIso8601String(),
            ]);

            return redirect()
                ->route('automotive.admin.dashboard')
                ->with('success', 'Tenant impersonation started successfully.');
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('automotive.admin.home')
                ->withErrors(['email' => $exception->getMessage()]);
        }
    }

    public function stopImpersonation(Request $request)
    {
        $impersonation = $request->session()->get('tenant_admin_impersonation', []);
        $returnUrl = is_array($impersonation) && ! empty($impersonation['return_url'])
            ? (string) $impersonation['return_url']
            : $this->tenantImpersonationService->centralTenantShowUrl((string) tenant('id'));

        Auth::guard('automotive_admin')->logout();

        $request->session()->forget('tenant_admin_impersonation');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->away($returnUrl);
    }
}
