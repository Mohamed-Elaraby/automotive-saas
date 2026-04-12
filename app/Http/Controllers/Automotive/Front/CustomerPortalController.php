<?php

namespace App\Http\Controllers\Automotive\Front;

use App\Data\AdminNotificationData;
use App\Http\Controllers\Controller;
use App\Models\CustomerOnboardingProfile;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductEnablementRequest;
use App\Models\Tenant;
use App\Services\Admin\AppSettingsService;
use App\Services\Automotive\StartAdditionalProductCheckoutService;
use App\Services\Automotive\StartPaidCheckoutService;
use App\Services\Automotive\StartTrialService;
use App\Services\Billing\BillingPlanCatalogService;
use App\Services\Notifications\AdminNotificationService;
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomerPortalController extends Controller
{
    protected const PRODUCT_CODE = 'automotive_service';

    public function __construct(
        protected AppSettingsService $settingsService,
        protected BillingPlanCatalogService $billingPlanCatalogService,
        protected AdminNotificationService $adminNotificationService
    ) {
    }

    public function index(Request $request): View
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
        $productCatalog = $this->productCatalogForTenantIds(
            $tenantIds,
            (string) ($subscription->tenant_id ?? ($tenantIds->first() ?? ''))
        );
        $selectedProduct = $this->resolveSelectedProduct($request, $productCatalog, $subscription);
        $selectedProductWasExplicit = trim((string) $request->query('product')) !== '';
        $selectedProductCode = (string) ($selectedProduct['code'] ?? '');
        $selectedProductCapabilities = collect($selectedProduct['capabilities'] ?? []);
        $paidPlans = $selectedProductCode !== ''
            ? $this->billingPlanCatalogService->getPaidPlans($selectedProductCode)
            : collect();
        $selectedProductTrialPlan = $this->selectedProductTrialPlan((int) ($selectedProduct['id'] ?? 0));
        $selectedProductHasTrialPlan = $selectedProductTrialPlan !== null;
        $selectedProductEnablementRequest = $this->productEnablementRequestForUser(
            $user->id,
            (string) ($selectedProduct['tenant_id'] ?? ''),
            (int) ($selectedProduct['id'] ?? 0)
        );
        $selectedProductSubscription = $this->selectedProductSubscriptionForTenant(
            (string) ($selectedProduct['tenant_id'] ?? ''),
            (int) ($selectedProduct['id'] ?? 0)
        );
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
        $hasAnyWorkspace = $tenantIds->isNotEmpty();
        $canStartPaidCheckout = ! $hasLiveStripeSubscription
            && (
                (string) ($subscription->status ?? '') !== SubscriptionStatuses::ACTIVE
                || $isTrialWorkspace
            );
        $selectedProductSupportsCheckout = $selectedProduct !== null && (! $hasAnyWorkspace
            ? ((bool) ($selectedProduct['is_active'] ?? false) && ((bool) ($selectedProduct['has_paid_plans'] ?? false) || $selectedProductHasTrialPlan))
            : $selectedProductCode === self::PRODUCT_CODE
            || (
                (int) ($selectedProduct['id'] ?? 0) > 0
                && (string) $selectedProductCode !== self::PRODUCT_CODE
                && (
                    (string) ($selectedProductEnablementRequest->status ?? '') === 'approved'
                    || $selectedProductSubscription !== null
                )
            ));
        $selectedProductHasLiveBilling = $selectedProduct !== null && ($selectedProductCode === self::PRODUCT_CODE
            ? $hasLiveStripeSubscription
            : $this->hasLiveTenantProductBilling($selectedProductSubscription));
        $selectedProductStatus = (string) ($selectedProductSubscription->status ?? ($subscription->status ?? ''));
        $selectedProductHasPendingCheckout = $selectedProductCode === self::PRODUCT_CODE
            ? $hasPendingPaidCheckout
            : (
                $selectedProductSubscription !== null
                && filled($selectedProductSubscription->gateway_checkout_session_id ?? null)
                && ! filled($selectedProductSubscription->gateway_subscription_id ?? null)
            );
        $selectedPortalBillingUrl = $hasAnyWorkspace && $selectedProductCode !== ''
            ? route('automotive.portal.billing.status', ['workspace_product' => $selectedProductCode])
            : null;

        return view('automotive.portal.index', [
            'user' => $user,
            'profile' => $profile,
            'visibleCouponCode' => $this->displayableCouponCode($profile, $user),
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
            'selectedProductHasTrialPlan' => $selectedProductHasTrialPlan,
            'selectedProductTrialDays' => (int) ($selectedProductTrialPlan->trial_days ?? 14),
            'canStartPaidCheckout' => $canStartPaidCheckout,
            'hasLiveStripeSubscription' => $hasLiveStripeSubscription,
            'hasPendingPaidCheckout' => $hasPendingPaidCheckout,
            'isTrialWorkspace' => $isTrialWorkspace,
            'productCatalog' => $productCatalog,
            'selectedProduct' => $selectedProduct,
            'selectedProductWasExplicit' => $selectedProductWasExplicit,
            'selectedProductCapabilities' => $selectedProductCapabilities,
            'selectedProductSupportsCheckout' => $selectedProductSupportsCheckout,
            'selectedProductEnablementRequest' => $selectedProductEnablementRequest,
            'selectedProductSubscription' => $selectedProductSubscription,
            'selectedProductHasLiveBilling' => $selectedProductHasLiveBilling,
            'selectedProductStatus' => $selectedProductStatus,
            'selectedProductHasPendingCheckout' => $selectedProductHasPendingCheckout,
            'hasAnyWorkspace' => $hasAnyWorkspace,
            'selectedPortalBillingUrl' => $selectedPortalBillingUrl,
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

        $validated = request()->validate([
            'product_id' => ['nullable', 'integer'],
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
            'coupon_code' => $this->displayableCouponCode($profile, $user) ?? '',
            'base_host' => $profile->base_host ?: request()->getHost(),
            'product_id' => ! empty($validated['product_id']) ? (int) $validated['product_id'] : null,
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
            ->with('success', 'Your workspace trial is ready now.');
    }

    public function startPaidCheckout(
        StartPaidCheckoutService $service,
        StartAdditionalProductCheckoutService $startAdditionalProductCheckoutService,
        Request $request
    ): RedirectResponse {
        $user = Auth::guard('web')->user();

        abort_unless($user, 403);

        $validated = $request->validate([
            'plan_id' => ['required', 'integer'],
            'product_id' => ['nullable', 'integer'],
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

        $productId = (int) ($validated['product_id'] ?? 0);
        $result = $productId > 0 && $this->isAdditionalProductCheckout($productId)
            ? $startAdditionalProductCheckoutService->start($user, $profile, (int) $validated['plan_id'], $productId)
            : $service->start($user, $profile, (int) $validated['plan_id'], $productId > 0 ? $productId : null);

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
            ->route('automotive.portal', ['product' => request()->query('product')])
            ->with('success', 'Your checkout session was completed. Subscription sync will finalize via Stripe webhook.');
    }

    public function checkoutCancel(): RedirectResponse
    {
        return redirect()
            ->route('automotive.portal', ['product' => request()->query('product')])
            ->withErrors([
                'portal' => 'Checkout was cancelled before completion.',
            ]);
    }

    public function settings(): View
    {
        $user = Auth::guard('web')->user();

        abort_unless($user, 403);

        $profile = CustomerOnboardingProfile::query()
            ->where('user_id', $user->id)
            ->first();

        $tenantIds = $this->tenantIdsForUser($user);
        $subscription = $this->latestSubscriptionForUser($user);
        $plan = $subscription ? $this->planById((int) ($subscription->plan_id ?? 0)) : null;
        $workspaceTenantId = (string) ($subscription->tenant_id ?? ($tenantIds->first() ?? ''));
        $domains = $workspaceTenantId !== '' ? $this->domainsForTenant($workspaceTenantId) : collect();
        $primaryDomain = $domains->first();
        $primaryDomainValue = $primaryDomain['domain'] ?? null;
        $systemUrl = $primaryDomain['admin_login_url'] ?? null;
        $status = (string) ($subscription->status ?? '');
        $allowSystemAccess = in_array($status, SubscriptionStatuses::accessAllowedStatuses(), true) && filled($systemUrl);

        $workspaceSnapshots = $tenantIds->isEmpty()
            ? collect()
            : Tenant::query()
                ->whereIn('id', $tenantIds->all())
                ->get()
                ->map(function (Tenant $tenant): array {
                    return [
                        'tenant_id' => (string) $tenant->id,
                        'company_name' => (string) ($tenant->company_name ?? $tenant->business_name ?? $tenant->id),
                        'owner_email' => (string) ($tenant->owner_email ?? ''),
                    ];
                })
                ->values();

        return view('automotive.portal.settings', [
            'user' => $user,
            'profile' => $profile,
            'subscription' => $subscription,
            'plan' => $plan,
            'status' => $status,
            'domains' => $domains,
            'primaryDomainValue' => $primaryDomainValue,
            'systemUrl' => $systemUrl,
            'allowSystemAccess' => $allowSystemAccess,
            'workspaceSnapshots' => $workspaceSnapshots,
            'workspaceTenantId' => $workspaceTenantId,
            'portalBreadcrumb' => 'Account & Settings',
            'portalActiveNav' => 'settings',
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = Auth::guard('web')->user();

        abort_unless($user, 403);

        $profile = CustomerOnboardingProfile::query()
            ->where('user_id', $user->id)
            ->first();

        if (! $profile) {
            return redirect()
                ->route('automotive.portal.settings')
                ->withErrors([
                    'portal' => 'Your portal onboarding profile is missing. Contact support before editing workspace settings.',
                ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'company_name' => ['required', 'string', 'max:255'],
        ]);

        $emailChanged = strcasecmp((string) $user->email, (string) $validated['email']) !== 0;

        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
        ])->save();

        $profile->update([
            'company_name' => $validated['company_name'],
        ]);

        $this->syncTenantAccountSnapshot(
            $this->tenantIdsForUser($user),
            (string) $validated['company_name'],
            (string) $validated['email']
        );

        return redirect()
            ->route('automotive.portal.settings')
            ->with('success', 'Your portal profile and workspace information were updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = Auth::guard('web')->user();

        abort_unless($user, 403);

        $validated = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);

        $user->forceFill([
            'password' => Hash::make((string) $validated['password']),
            'remember_token' => Str::random(60),
        ])->save();

        return redirect()
            ->route('automotive.portal.settings')
            ->with('success', 'Your portal password was updated.');
    }

    public function requestProductEnablement(Request $request): RedirectResponse
    {
        $user = Auth::guard('web')->user();

        abort_unless($user, 403);

        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
        ]);

        $product = Product::query()->find((int) $validated['product_id']);

        if (! $product || ! $product->is_active) {
            return redirect()
                ->route('automotive.portal', ['product' => $product?->slug])
                ->withErrors([
                    'portal' => 'The selected product is not available for enablement.',
                ]);
        }

        if ((string) $product->code === self::PRODUCT_CODE) {
            return redirect()
                ->route('automotive.portal', ['product' => $product->slug])
                ->withErrors([
                    'portal' => 'This product uses the direct checkout flow instead of enablement request.',
                ]);
        }

        $tenantId = (string) ($this->tenantIdsForUser($user)->first() ?? '');

        if ($tenantId === '') {
            return redirect()
                ->route('automotive.portal', ['product' => $product->slug])
                ->withErrors([
                    'portal' => 'Start your first workspace product before requesting additional product enablement.',
                ]);
        }

        $requestRow = ProductEnablementRequest::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'product_id' => $product->id,
            ],
            [
                'user_id' => $user->id,
                'status' => 'pending',
                'requested_at' => now(),
                'approved_at' => null,
                'rejected_at' => null,
            ]
        );

        $this->adminNotificationService->create(new AdminNotificationData(
            type: 'product_enablement_request',
            title: 'New product enablement request submitted',
            message: "{$user->name} requested {$product->name} for tenant {$tenantId}.",
            severity: 'info',
            sourceType: ProductEnablementRequest::class,
            sourceId: $requestRow->id,
            routeName: 'admin.product-enablement-requests.index',
            routeParams: [
                'status' => 'pending',
                'product_id' => $product->id,
                'q' => $tenantId,
            ],
            targetUrl: null,
            tenantId: $tenantId,
            userId: $user->id,
            userEmail: $user->email,
            contextPayload: [
                'event' => 'product_enablement_request_submitted',
                'product_id' => $product->id,
                'product_name' => $product->name,
            ],
        ));

        return redirect()
            ->route('automotive.portal', ['product' => $product->slug])
            ->with('success', 'Your product enablement request was submitted successfully.');
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

    protected function syncTenantAccountSnapshot(Collection $tenantIds, string $companyName, string $ownerEmail): void
    {
        if ($tenantIds->isEmpty()) {
            return;
        }

        Tenant::query()
            ->whereIn('id', $tenantIds->all())
            ->get()
            ->each(function (Tenant $tenant) use ($companyName, $ownerEmail): void {
                $tenant->company_name = $companyName;
                $tenant->owner_email = $ownerEmail;
                $tenant->save();
            });
    }

    protected function productCatalogForTenantIds(Collection $tenantIds, string $currentTenantId = ''): Collection
    {
        $connection = $this->centralConnectionName();
        $hasWorkspace = $tenantIds->isNotEmpty();

        if (! Schema::connection($connection)->hasTable('products')) {
            return collect();
        }

        $productSubscriptions = collect();
        $capabilitiesByProductId = collect();
        $paidPlanCounts = Plan::query()
            ->where('is_active', true)
            ->where('billing_period', '!=', 'trial')
            ->selectRaw('product_id, COUNT(*) as aggregate')
            ->groupBy('product_id')
            ->pluck('aggregate', 'product_id');

        if (Schema::connection($connection)->hasTable('product_capabilities')) {
            $capabilitiesByProductId = DB::connection($connection)
                ->table('product_capabilities')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['product_id', 'name'])
                ->groupBy('product_id')
                ->map(fn (Collection $items) => $items->pluck('name')->values()->all());
        }

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
            ->map(function (Product $product) use ($productSubscriptions, $currentTenantId, $paidPlanCounts, $capabilitiesByProductId, $hasWorkspace): array {
                $subscription = $productSubscriptions->get($product->id);
                $status = (string) ($subscription->status ?? '');
                $isAutomotive = (string) $product->code === self::PRODUCT_CODE;
                $hasPaidPlans = (int) ($paidPlanCounts->get($product->id) ?? 0) > 0;
                $isSubscribed = $subscription !== null
                    && ! in_array($status, ['expired', 'canceled'], true);

                return [
                    'id' => $product->id,
                    'code' => (string) $product->code,
                    'name' => (string) $product->name,
                    'slug' => (string) $product->slug,
                    'description' => (string) ($product->description ?? ''),
                    'is_active' => (bool) $product->is_active,
                    'has_paid_plans' => $hasPaidPlans,
                    'capabilities' => $capabilitiesByProductId->get($product->id, []),
                    'is_automotive' => $isAutomotive,
                    'is_subscribed' => $isSubscribed,
                    'tenant_id' => (string) ($subscription->tenant_id ?? $currentTenantId),
                    'subscription_status' => $status,
                    'status_label' => $this->productStatusLabel($product, $subscription, $hasPaidPlans),
                    'action_label' => $this->productActionLabel($product, $isSubscribed, $hasPaidPlans, $hasWorkspace),
                    'action_url' => route('automotive.portal', ['product' => $product->slug]) . '#paid-plans',
                ];
            })
            ->values();
    }

    protected function resolveSelectedProduct(Request $request, Collection $productCatalog, ?object $subscription = null): ?array
    {
        $selected = trim((string) $request->query('product'));

        if ($selected !== '') {
            $matched = $productCatalog->first(function (array $productRow) use ($selected) {
                return (string) $productRow['slug'] === $selected
                    || (string) $productRow['code'] === $selected;
            });

            if ($matched) {
                $request->session()->put('portal_selected_product', (string) ($matched['code'] ?? $selected));

                return $matched;
            }
        }

        $remembered = trim((string) $request->session()->get('portal_selected_product', ''));

        if ($remembered !== '') {
            $matched = $productCatalog->first(function (array $productRow) use ($remembered) {
                return (string) $productRow['code'] === $remembered
                    || (string) $productRow['slug'] === $remembered;
            });

            if ($matched) {
                return $matched;
            }

            $request->session()->forget('portal_selected_product');
        }

        if (! empty($subscription?->product_id)) {
            $matched = $productCatalog->firstWhere('id', (int) $subscription->product_id);

            if ($matched) {
                return $matched;
            }
        }

        if (! empty($subscription?->product_code)) {
            $matched = $productCatalog->firstWhere('code', (string) $subscription->product_code);

            if ($matched) {
                return $matched;
            }
        }

        $subscribedProducts = $productCatalog->where('is_subscribed', true)->values();

        if ($subscribedProducts->count() === 1) {
            return $subscribedProducts->first();
        }

        return null;
    }

    protected function productEnablementRequestForUser(int $userId, string $tenantId, int $productId): ?ProductEnablementRequest
    {
        if ($userId <= 0 || $tenantId === '' || $productId <= 0) {
            return null;
        }

        $connection = $this->centralConnectionName();

        if (! Schema::connection($connection)->hasTable('product_enablement_requests')) {
            return null;
        }

        return ProductEnablementRequest::query()
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->first();
    }

    protected function selectedProductSubscriptionForTenant(string $tenantId, int $productId): ?object
    {
        if ($tenantId === '' || $productId <= 0) {
            return null;
        }

        $connection = $this->centralConnectionName();

        if (! Schema::connection($connection)->hasTable('tenant_product_subscriptions')) {
            return null;
        }

        return DB::connection($connection)
            ->table('tenant_product_subscriptions')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->orderByDesc('id')
            ->first();
    }

    protected function hasLiveTenantProductBilling(?object $subscription): bool
    {
        if (! $subscription || ! filled($subscription->gateway_subscription_id ?? null)) {
            return false;
        }

        return in_array((string) ($subscription->status ?? ''), SubscriptionStatuses::accessAllowedStatuses(), true);
    }

    protected function isAdditionalProductCheckout(int $productId): bool
    {
        $user = Auth::guard('web')->user();

        if (! $user) {
            return false;
        }

        $tenantIds = $this->tenantIdsForUser($user);

        if ($tenantIds->isEmpty()) {
            return false;
        }

        $product = Product::query()->find($productId);

        return $product && (string) $product->code !== self::PRODUCT_CODE;
    }

    protected function selectedProductHasTrialPlan(int $productId): bool
    {
        if ($productId <= 0) {
            return false;
        }

        return Plan::query()
            ->where('product_id', $productId)
            ->where('billing_period', 'trial')
            ->where('is_active', true)
            ->exists();
    }

    protected function selectedProductTrialPlan(int $productId): ?Plan
    {
        if ($productId <= 0) {
            return null;
        }

        return Plan::query()
            ->where('product_id', $productId)
            ->where('billing_period', 'trial')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    protected function productStatusLabel(Product $product, ?object $subscription, bool $hasPaidPlans = false): string
    {
        if ($subscription) {
            return strtoupper(str_replace('_', ' ', (string) ($subscription->status ?? 'subscribed')));
        }

        if (! (bool) $product->is_active) {
            return 'COMING SOON';
        }

        if ($hasPaidPlans) {
            return 'AVAILABLE NOW';
        }

        return 'READY FOR SETUP';
    }

    protected function productActionLabel(Product $product, bool $isSubscribed, bool $hasPaidPlans, bool $hasWorkspace): string
    {
        if (! (bool) $product->is_active) {
            return 'Coming Soon';
        }

        if ($isSubscribed) {
            return (string) $product->code === self::PRODUCT_CODE
                ? 'Open Product Workspace'
                : 'Manage Product';
        }

        if ($hasWorkspace && (string) $product->code !== self::PRODUCT_CODE) {
            return 'Explore Enablement';
        }

        if ($hasPaidPlans) {
            return 'Browse Product Plans';
        }

        return 'Explore Enablement';
    }

    protected function displayableCouponCode(?CustomerOnboardingProfile $profile, ?object $user): ?string
    {
        $coupon = trim((string) ($profile->coupon_code ?? ''));

        if ($coupon === '') {
            return null;
        }

        $userEmail = strtolower(trim((string) ($user->email ?? '')));
        $couponLower = strtolower($coupon);

        if ($couponLower === $userEmail || str_contains($coupon, '@')) {
            return null;
        }

        return strtoupper($coupon);
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
