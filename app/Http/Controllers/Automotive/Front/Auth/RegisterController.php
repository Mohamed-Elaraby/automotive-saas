<?php

namespace App\Http\Controllers\Automotive\Front\Auth;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\Automotive\StartTrialService;
use App\Services\Automotive\TenantUrlBuilder;
use App\Services\Billing\TrialSignupCouponService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    public function show()
    {
        return view('automotive.front.auth.register');
    }

    public function previewCoupon(
        Request $request,
        TrialSignupCouponService $trialSignupCouponService
    ) {
        $validator = Validator::make($request->all(), [
            'subdomain' => ['required', 'string', 'alpha_dash', 'min:3', 'max:50'],
            'coupon_code' => ['required', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Please enter a valid subdomain and coupon code first.',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $data = $validator->validated();

        $trialPlan = Plan::query()
            ->where('slug', 'trial')
            ->where('is_active', true)
            ->first();

        $result = $trialSignupCouponService->validateForTrialSignup(
            couponCode: $data['coupon_code'],
            tenantId: strtolower(trim($data['subdomain'])),
            planId: $trialPlan?->id
        );

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'message' => $result['message'] ?? 'This coupon cannot be used right now.',
                'errors' => $result['errors'] ?? [],
                'eligibility' => $result['eligibility'] ?? null,
            ], 422);
        }

        $coupon = $result['coupon'];
        $eligibility = $result['eligibility'] ?? [];

        return response()->json([
            'ok' => true,
            'message' => $eligibility['summary'] ?? 'Coupon is valid for reservation during trial signup.',
            'coupon' => [
                'code' => $coupon?->code,
                'name' => $coupon?->name,
                'discount_type' => $coupon?->discount_type,
                'discount_value' => $coupon?->discount_value,
                'currency_code' => $coupon?->currency_code,
                'first_billing_cycle_only' => (bool) ($coupon?->first_billing_cycle_only ?? false),
                'applies_to_all_plans' => (bool) ($coupon?->applies_to_all_plans ?? false),
            ],
            'eligibility' => $eligibility,
        ]);
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
