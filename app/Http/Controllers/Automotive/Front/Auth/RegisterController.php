<?php

namespace App\Http\Controllers\Automotive\Front\Auth;

use App\Http\Controllers\Controller;
use App\Services\Automotive\StartTrialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    public function show()
    {
        return view('automotive.front.auth.register');
    }

    public function submit(Request $request, StartTrialService $service)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'company_name' => ['required', 'string', 'max:255'],
            'subdomain' => ['required', 'string', 'alpha_dash', 'min:3', 'max:50'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $result = $service->start($validator->validated());

        if (!($result['ok'] ?? false)) {
            // نخلي نفس errors تظهر في UI
            if (($result['status'] ?? 500) === 422) {
                return back()
                    ->withErrors($result['errors'] ?? ['register' => $result['message'] ?? 'Validation error'])
                    ->withInput();
            }

            return back()
                ->withErrors(['register' => $result['message'] ?? 'Provisioning failed.'])
                ->withInput();
        }

        return redirect()->away($result['login_url'])
            ->with('success', 'Your trial account has been created successfully.');
    }
}
