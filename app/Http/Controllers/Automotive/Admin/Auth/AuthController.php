<?php

namespace App\Http\Controllers\automotive\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login()
    {
        return view('automotive.auth.login');
    }

    public function doLogin(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (Auth::guard('automotive_admin')->attempt($credentials, $remember)) {
            $request->session()->regenerate();
            return redirect()->intended(route('automotive.admin.dashboard'));
        }

        throw ValidationException::withMessages([
            'email' => ['Email or password is incorrect.'],
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('automotive_admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('automotive.admin.login');
    }

    public function register()
    {
        return view('automotive.auth.register');
    }

    public function forgotPassword()
    {
        return view('automotive.auth.forgot-password');
    }

    public function resetPassword()
    {
        return view('automotive.auth.reset-password');
    }
}
