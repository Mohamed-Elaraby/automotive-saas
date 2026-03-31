<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function showLinkRequestForm(): View
    {
        return view('admin.auth.forgot-password');
    }

    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc'],
        ]);

        $normalizedEmail = strtolower(trim((string) $validated['email']));

        $admin = Admin::query()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->first();

        if (! $admin) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => "We can't find a user with that email address.",
                ]);
        }

        $status = Password::broker('admins')->sendResetLink([
            'email' => (string) $admin->email,
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => __($status),
                ]);
        }

        return back()->with('status', __($status));
    }
}
