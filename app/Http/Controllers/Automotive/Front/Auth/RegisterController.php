<?php

namespace App\Http\Controllers\Automotive\Front\Auth;

use App\Http\Controllers\Controller;
use App\Models\CustomerOnboardingProfile;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Admin\AppSettingsService;
use App\Services\Billing\TrialSignupCouponService;
use App\Services\Tenancy\WorkspaceHostResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Stancl\Tenancy\Database\Models\Domain;

class RegisterController extends Controller
{
    public function __construct(
        protected AppSettingsService $settingsService,
        protected WorkspaceHostResolver $workspaceHostResolver
    ) {
    }

public function show()
{
    return view('automotive.portal.auth.register');
}

public function previewCoupon(
    Request $request,
    TrialSignupCouponService $trialSignupCouponService
) {
    if (! $this->settingsService->freeTrialEnabled()) {
        return response()->json([
            'ok' => false,
            'message' => 'Free trial registration is currently unavailable.',
            'errors' => [
                'coupon_code' => ['Free trial registration is currently unavailable.'],
            ],
        ], 422);
    }

    $validator = Validator::make($request->all(), [
        'subdomain' => ['required', 'string', 'alpha_dash', 'min:3', 'max:50'],
        'coupon_code' => ['required', 'string', 'max:100'],
    ]);

    $validator->after(function ($validator) use ($request) {
        $subdomain = strtolower(trim((string) $request->input('subdomain')));
        $baseHost = $this->workspaceHostResolver->canonicalBaseHost($request->getHost());

        if (! $this->subdomainIsAvailable($subdomain, $baseHost)) {
            $validator->errors()->add('subdomain', 'This subdomain is not available.');
        }
    });

    if ($validator->fails()) {
        return response()->json([
            'ok' => false,
            'message' => $validator->errors()->first('subdomain') ?: 'Please enter a valid subdomain and coupon code first.',
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

public function submit(Request $request): RedirectResponse
{
    $validator = Validator::make($request->all(), [
        'name' => ['required', 'string', 'max:255'],
        'email' => [
            'required',
            'email',
            'max:255',
            Rule::unique('users', 'email'),
        ],
        'password' => ['required', 'string', 'min:6', 'confirmed'],
        'company_name' => ['required', 'string', 'max:255'],
        'subdomain' => [
            'required',
            'string',
            'alpha_dash',
            'min:3',
            'max:50',
            Rule::unique('customer_onboarding_profiles', 'subdomain'),
        ],
        'coupon_code' => ['nullable', 'string', 'max:100'],
    ]);

    $validator->after(function ($validator) use ($request) {
        $subdomain = strtolower(trim((string) $request->input('subdomain')));
        $baseHost = $this->workspaceHostResolver->canonicalBaseHost($request->getHost());

        if (! $this->subdomainIsAvailable($subdomain, $baseHost)) {
            $validator->errors()->add('subdomain', 'This subdomain is not available.');
        }
    });

    if ($validator->fails()) {
        return back()->withErrors($validator)->withInput();
    }

    $data = $validator->validated();

    $user = User::query()->create([
        'name' => $data['name'],
        'email' => strtolower(trim((string) $data['email'])),
        'password' => Hash::make((string) $data['password']),
    ]);

    CustomerOnboardingProfile::query()->updateOrCreate(
        ['user_id' => $user->id],
        [
            'company_name' => $data['company_name'],
            'subdomain' => strtolower(trim((string) $data['subdomain'])),
            'coupon_code' => strtoupper(trim((string) ($data['coupon_code'] ?? ''))),
            'base_host' => $this->workspaceHostResolver->canonicalBaseHost($request->getHost()),
            'password_payload' => Crypt::encryptString((string) $data['password']),
        ]
    );

    Auth::guard('web')->login($user);

    return redirect()
        ->route('automotive.portal')
        ->with('success', 'Your account was created successfully. Choose how you want to continue from your customer portal.');
}

protected function subdomainIsAvailable(string $subdomain, string $baseHost): bool
{
    if ($subdomain === '') {
        return false;
    }

    $fullDomain = $this->workspaceHostResolver->tenantDomain($subdomain, $baseHost);

    if (Tenant::query()->where('id', $subdomain)->exists()) {
        return false;
    }

    if ($fullDomain !== '' && Domain::query()->where('domain', $fullDomain)->exists()) {
        return false;
    }

    return true;
}
}
