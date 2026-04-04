<?php

namespace App\Http\Controllers\Automotive\Front;

use App\Http\Controllers\Controller;
use App\Models\CustomerOnboardingProfile;
use App\Models\Product;
use App\Services\Admin\AppSettingsService;
use App\Services\Automotive\StartPaidCheckoutService;
use App\Services\Automotive\StartTrialService;
use App\Services\Billing\BillingPlanCatalogService;
use App\Support\Billing\SubscriptionStatuses;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerPortalController extends Controller
{
    protected const PRODUCT_CODE = 'automotive_service';

    public function __construct(
        protected AppSettingsService $settingsService,
        protected BillingPlanCatalogService $billingPlanCatalogService
    ) {
    }

    public function index(): View
    {
        $user = Auth::guard('web')->user();

        abort_unless($user, 403);

        $profile = CustomerOnboardingProfile::query()
            ->where('user_id', $user->id)
            ->first();

        $tenantIds = $this->tenantIdsForUser($user);
        $subscription = $this->latestSubscriptionForUser($user);
        $plan = $subscription ? $this->planById((int) ($subscription->plan_id ?? 0)) : null;
        $domains = $subscription && ! empty($subscription->tenant_id)
            ? $this->domainsForTenant((string) $subscription->tenant_id)
            : collect();
        $paidPlans = $this->billingPlanCatalogService->getPaidPlans(self::PRODUCT_CODE);
        $productCatalog = $this->productCatalogForTenantIds($tenantIds, (string) ($subscription->tenant_id ?? ''));

        $primaryDomain = $domains->first();
        $primaryDomainValue = $primaryDomain['domain'] ?? null;
        $systemUrl = $primaryDomain['admin_login_url'] ?? null;

        $status = (string) ($subscription->status ?? '');
        $trialEndsAt = $this->nullableCarbon($subscription->trial_ends_at ?? null);
        $isTrialWorkspace = $this->subscriptionRepresentsTrialWorkspace($subscription, $plan);

        $trialDaysRemaining = null;
        if ($trialEndsAt && $trialEndsAt->isFuture()) {
            $trialDaysRemaining = now()->diffInDays($trialEndsAt, false);
        }

        $allowSystemAccess = in_array($status, SubscriptionStatuses::accessAllowedStatuses(), true) && filled($systemUrl);
        $hasLiveStripeSubscription = $this->hasLiveStripeSubscription($subscription);
        $hasPendingPaidCheckout = $subscription
            && filled($subscription->gateway_checkout_session_id)
            && ! filled($subscription->gateway_subscription_id);
        $canStartPaidCheckout = ! $hasLiveStripeSubscription
            && (
                (string) ($subscription->status ?? '') !== SubscriptionStatuses::ACTIVE
                || $isTrialWorkspace
            );

        return view('automotive.portal.index', [
            'user' => $user,
            'profile' => $profile,
            'subscription' => $subscription,
            'plan' => $plan,
            'status' => $status,
            'trialEndsAt' => $trialEndsAt,
            'trialDaysRemaining' => $trialDaysRemaining,
            'domains' => $domains,
            'primaryDomainValue' => $primaryDomainValue,
            'systemUrl' => $systemUrl,
            'allowSystemAccess' => $allowSystemAccess,
            'freeTrialEnabled' => $this->settingsService->freeTrialEnabled(),
            'paidPlans' => $paidPlans,
            'canStartPaidCheckout' => $canStartPaidCheckout,
            'hasLiveStripeSubscription' => $hasLiveStripeSubscription,
            'hasPendingPaidCheckout' => $hasPendingPaidCheckout,
            'isTrialWorkspace' => $isTrialWorkspace,
            'productCatalog' => $productCatalog,
        ]);
    }

    public function startTrial(StartTrialService $service): RedirectResponse
    {
        $user = Auth::guard('web')->user();

        abort_unless($user, 403);

        if (! $this->settingsService->freeTrialEnabled()) {
            return redirect()
                ->route('automotive.portal')
                ->withErrors([
                    'portal' => 'Free trial is currently unavailable.',
                ]);
        }

        $profile = CustomerOnboardingProfile::query()
            ->where('user_id', $user->id)
            ->first();

        if (! $profile) {
            return redirect()
                ->route('automotive.portal')
                ->withErrors([
                    'portal' => 'Your onboarding profile is missing. Please register again or contact support.',
                ]);
        }

        $existingSubscription = $this->latestSubscriptionForUser($user);
        if ($existingSubscription) {
            return redirect()
                ->route('automotive.portal')
                ->withErrors([
                    'portal' => 'A subscription already exists for your account.',
                ]);
        }

        try {
            $password = filled($profile->password_payload)
                ? Crypt::decryptString((string) $profile->password_payload)
                : null;
        } catch (\Throwable) {
            $password = null;
        }

        if (! filled($password)) {
            return redirect()
                ->route('automotive.portal')
                ->withErrors([
                    'portal' => 'Your original setup password is no longer available. Please contact support to restart onboarding safely.',
                ]);
        }

        $data = [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $password,
            'company_name' => $profile->company_name,
            'subdomain' => strtolower(trim((string) $profile->subdomain)),
            'coupon_code' => strtoupper(trim((string) ($profile->coupon_code ?? ''))),
            'base_host' => $profile->base_host ?: request()->getHost(),
        ];

        $result = $service->start($data);

        if (! ($result['ok'] ?? false)) {
            if (($result['status'] ?? 500) === 422) {
                return redirect()
                    ->route('automotive.portal')
                    ->withErrors($result['errors'] ?? ['portal' => $result['message'] ?? 'Trial setup validation failed.']);
            }

            return redirect()
                ->route('automotive.portal')
                ->withErrors([
                    'portal' => $result['message'] ?? 'Trial provisioning failed.',
                ]);
        }

        $profile->update([
            'password_payload' => null,
        ]);

        return redirect()
            ->route('automotive.portal')
            ->with('success', 'Your free trial system is ready now.');
    }

    public function startPaidCheckout(
        StartPaidCheckoutService $service,
        Request $request
    ): RedirectResponse {
        $user = Auth::guard('web')->user();

        abort_unless($user, 403);

        $validated = $request->validate([
            'plan_id' => ['required', 'integer'],
        ]);

        $profile = CustomerOnboardingProfile::query()
            ->where('user_id', $user->id)
            ->first();

        if (! $profile) {
            return redirect()
                ->route('automotive.portal')
                ->withErrors([
                    'portal' => 'Your onboarding profile is missing. Please register again or contact support.',
                ]);
        }

        $result = $service->start($user, $profile, (int) $validated['plan_id']);

        if (! ($result['ok'] ?? false)) {
            return redirect()
                ->route('automotive.portal')
                ->withErrors($result['errors'] ?? ['portal' => $result['message'] ?? 'Unable to start paid checkout.']);
        }

        return redirect()->away((string) $result['checkout_url']);
    }

    public function checkoutSuccess(): RedirectResponse
    {
        return redirect()
            ->route('automotive.portal')
            ->with('success', 'Your checkout session was completed. Subscription sync will finalize via Stripe webhook.');
    }

    public function checkoutCancel(): RedirectResponse
    {
        return redirect()
            ->route('automotive.portal')
            ->withErrors([
                'portal' => 'Checkout was cancelled before completion.',
            ]);
    }

    protected function centralConnectionName(): string
    {
        return (string) (Config::get('tenancy.database.central_connection') ?: Config::get('database.default'));
    }

    protected function latestSubscriptionForUser(object $user): ?object
    {
        $connection = $this->centralConnectionName();

        if (
            ! Schema::connection($connection)->hasTable('tenant_users')
        ) {
            return null;
        }

        $tenantIds = $this->tenantIdsForUser($user);

        if ($tenantIds->isEmpty()) {
            return null;
        }

        if (
            Schema::connection($connection)->hasTable('tenant_product_subscriptions')
            && Schema::connection($connection)->hasTable('products')
        ) {
            $productSubscription = DB::connection($connection)
                ->table('tenant_product_subscriptions')
                ->join('products', 'products.id', '=', 'tenant_product_subscriptions.product_id')
                ->whereIn('tenant_product_subscriptions.tenant_id', $tenantIds->all())
                ->where('products.code', self::PRODUCT_CODE)
                ->orderByDesc('tenant_product_subscriptions.id')
                ->select('tenant_product_subscriptions.*')
                ->first();

            if ($productSubscription) {
                return $productSubscription;
            }
        }

        if (! Schema::connection($connection)->hasTable('subscriptions')) {
            return null;
        }

        return DB::connection($connection)
            ->table('subscriptions')
            ->whereIn('tenant_id', $tenantIds->all())
            ->orderByDesc('id')
            ->first();
    }

    protected function tenantIdsForUser(object $user): Collection
    {
        $connection = $this->centralConnectionName();

        if (! Schema::connection($connection)->hasTable('tenant_users')) {
            return collect();
        }

        return DB::connection($connection)
            ->table('tenant_users')
            ->where('user_id', $user->id)
            ->pluck('tenant_id')
            ->filter()
            ->values();
    }

    protected function productCatalogForTenantIds(Collection $tenantIds, string $currentTenantId = ''): Collection
    {
        $connection = $this->centralConnectionName();

        if (! Schema::connection($connection)->hasTable('products')) {
            return collect();
        }

        $productSubscriptions = collect();

        if (
            $tenantIds->isNotEmpty()
            && Schema::connection($connection)->hasTable('tenant_product_subscriptions')
        ) {
            $productSubscriptions = DB::connection($connection)
                ->table('tenant_product_subscriptions')
                ->whereIn('tenant_id', $tenantIds->all())
                ->orderByDesc('id')
                ->get()
                ->groupBy('product_id')
                ->map(fn (Collection $rows) => $rows->first());
        }

        return Product::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (Product $product) use ($productSubscriptions, $currentTenantId): array {
                $subscription = $productSubscriptions->get($product->id);
                $status = (string) ($subscription->status ?? '');
                $isAutomotive = (string) $product->code === self::PRODUCT_CODE;
                $isSubscribed = $subscription !== null
                    && ! in_array($status, ['expired', 'canceled'], true);

                return [
                    'id' => $product->id,
                    'code' => (string) $product->code,
                    'name' => (string) $product->name,
                    'slug' => (string) $product->slug,
                    'description' => (string) ($product->description ?? ''),
                    'is_active' => (bool) $product->is_active,
                    'is_automotive' => $isAutomotive,
                    'is_subscribed' => $isSubscribed,
                    'tenant_id' => (string) ($subscription->tenant_id ?? $currentTenantId),
                    'subscription_status' => $status,
                    'status_label' => $this->productStatusLabel($product, $subscription),
                    'action_label' => $isAutomotive
                        ? ($isSubscribed ? 'Manage Automotive' : 'Browse Automotive Plans')
                        : ((bool) $product->is_active ? 'Catalog Ready' : 'Coming Soon'),
                    'action_anchor' => $isAutomotive ? '#paid-plans' : '#products-catalog',
                ];
            })
            ->values();
    }

    protected function productStatusLabel(Product $product, ?object $subscription): string
    {
        if ($subscription) {
            return strtoupper(str_replace('_', ' ', (string) ($subscription->status ?? 'subscribed')));
        }

        if ((string) $product->code === self::PRODUCT_CODE) {
            return 'AVAILABLE NOW';
        }

        return (bool) $product->is_active ? 'READY FOR ENABLEMENT' : 'COMING SOON';
    }

    protected function planById(int $planId): ?object
    {
        if ($planId <= 0) {
            return null;
        }

        $connection = $this->centralConnectionName();

        if (! Schema::connection($connection)->hasTable('plans')) {
            return null;
        }

        return DB::connection($connection)
            ->table('plans')
            ->where('id', $planId)
            ->first();
    }

    protected function domainsForTenant(string $tenantId): Collection
    {
        $connection = $this->centralConnectionName();

        if (! Schema::connection($connection)->hasTable('domains')) {
            return collect();
        }

        return DB::connection($connection)
            ->table('domains')
            ->where('tenant_id', $tenantId)
            ->orderBy('domain')
            ->get(['tenant_id', 'domain'])
            ->map(function ($row): array {
                $domain = (string) $row->domain;
                $baseUrl = str_starts_with($domain, 'http://') || str_starts_with($domain, 'https://')
                    ? $domain
                    : 'https://' . $domain;

                return [
                    'tenant_id' => (string) $row->tenant_id,
                    'domain' => $domain,
                    'url' => $baseUrl,
                    'admin_login_url' => rtrim($baseUrl, '/') . '/automotive/admin/login',
                ];
            })
            ->values();
    }

    protected function nullableCarbon(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value);
    }

    protected function subscriptionRepresentsTrialWorkspace(?object $subscription, ?object $plan): bool
    {
        if (! $subscription) {
            return false;
        }

        if (filled($subscription->gateway_subscription_id ?? null)) {
            return false;
        }

        if ((string) ($subscription->status ?? '') === SubscriptionStatuses::TRIALING) {
            return true;
        }

        if (filled($subscription->trial_ends_at ?? null)) {
            return true;
        }

        if (! $plan) {
            return false;
        }

        return (string) ($plan->billing_period ?? '') === 'trial'
            || (float) ($plan->price ?? 0) <= 0;
    }

    protected function hasLiveStripeSubscription(?object $subscription): bool
    {
        if (! $subscription || ! filled($subscription->gateway_subscription_id ?? null)) {
            return false;
        }

        $status = (string) ($subscription->status ?? '');

        if ($status === SubscriptionStatuses::ACTIVE) {
            return true;
        }

        if ($status !== SubscriptionStatuses::CANCELLED) {
            return false;
        }

        if (blank($subscription->ends_at ?? null)) {
            return false;
        }

        return Carbon::parse((string) $subscription->ends_at)->isFuture();
    }
}
