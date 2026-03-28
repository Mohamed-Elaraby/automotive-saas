<?php

namespace App\Http\Controllers\Automotive\Front\Auth;

use App\Http\Controllers\Controller;
use App\Services\Automotive\StartTrialService;
use App\Services\Automotive\TenantUrlBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    public function show()
    {
        return view('automotive.front.auth.register');
    }

    public function submit(
        Request $request,
        StartTrialService $service,
        TenantUrlBuilder $tenantUrlBuilder
    ) {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'company_name' => ['required', 'string', 'max:255'],
            'subdomain' => ['required', 'string', 'alpha_dash', 'min:3', 'max:50'],
            'coupon_code' => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $data['base_host'] = $request->getHost();
        $data['coupon_code'] = strtoupper(trim((string) ($data['coupon_code'] ?? '')));

        $result = $service->start($data);

        if (! ($result['ok'] ?? false)) {
            if (($result['status'] ?? 500) === 422) {
                return back()
                    ->withErrors($result['errors'] ?? ['register' => $result['message'] ?? 'Validation error'])
                    ->withInput();
            }

            return back()
                ->withErrors(['register' => $result['message'] ?? 'Provisioning failed.'])
                ->withInput();
        }

        $loginUrl = $tenantUrlBuilder->tenantLoginUrl($request, $data['subdomain']);

        return redirect()->away($loginUrl)
            ->with('success', 'Your trial account has been created successfully.');
    }
}
