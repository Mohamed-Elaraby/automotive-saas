<?php

namespace App\Http\Controllers\automotive\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Central register page
     */
    public function register()
    {
        return view('automotive.auth.register');
    }

    /**
     * Central register submit -> calls start-trial API
     */
    public function doRegister(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
//            'email' => ['required', 'email', 'max:255'],
//            'password' => ['required', 'string', 'min:8', 'confirmed'],
//            'company_name' => ['required', 'string', 'max:255'],
//            'subdomain' => ['required', 'string', 'alpha_dash', 'min:3', 'max:50'],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        /**
         * نداء داخلي إلى نفس التطبيق عبر الـ API endpoint الموجود بالفعل
         * مهم: استخدم APP_URL المركزي
         */
        $apiUrl = rtrim(config('app.url'), '/') . '/api/automotive/start-trial';

        try {

            $response = Http::asForm()->post($apiUrl, [
                'name' => $request->name,
//                'email' => $request->email,
//                'password' => $request->password,
//                'password_confirmation' => $request->password_confirmation,
//                'company_name' => $request->company_name,
//                'subdomain' => $request->subdomain,
            ]);

//            if (! $response->successful()) {
//                dd($response->successful());
//                $message = $response->json('message')
//                    ?? 'Unable to create your trial account right now.';
//
//                return back()
//                    ->withErrors(['register' => $message])
//                    ->withInput();
//            }

            $data = $response->json();

            $loginUrl = $data['login_url']
                ?? ('https://' . $request->subdomain . '.automotive.seven-scapital.com/automotive/admin/login');

            return redirect()->away($loginUrl)
                ->with('success', 'Your trial account has been created successfully.');
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withErrors([
                    'register' => 'A server error occurred while creating your trial account.'
                ])
                ->withInput();
        }
    }

    /**
     * Tenant login page
     */
    public function login()
    {
        return view('automotive.admin.auth.login');
    }

    /**
     * Tenant login submit
     */
    public function doLogin(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::guard('automotive_admin')->attempt($credentials, $remember)) {
            return back()
                ->withErrors([
                    'email' => 'Invalid email or password.',
                ])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->route('automotive.admin.dashboard');
    }

    /**
     * Tenant logout
     */
    public function logout(Request $request)
    {
        Auth::guard('automotive_admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('automotive.admin.login');
    }
}
