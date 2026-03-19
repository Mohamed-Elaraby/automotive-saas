<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\BillingPlanCatalogService;
use App\Services\Billing\PaymentGatewayManager;
use App\Services\Billing\StripeCustomerPortalService;
use App\Services\Billing\StripePriceInspectorService;
use App\Services\Billing\StripeSubscriptionManagementService;
use App\Services\Billing\StripeSubscriptionPlanChangeService;
use App\Services\Billing\TenantBillingLifecycleService;
use App\Services\Tenancy\TenantPlanService;
use App\Support\Billing\BillingActionResolver;
use App\Support\Billing\SubscriptionStatuses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class BillingController extends Controller
{
    public function __construct(
        protected TenantPlanService $tenantPlanService,
        protected TenantBillingLifecycleService $tenantBillingLifecycleService,
        protected PaymentGatewayManager $paymentGatewayManager,
        protected BillingPlanCatalogService $billingPlanCatalogService,
        protected StripeCustomerPortalService $stripeCustomerPortalService,
        protected StripePriceInspectorService $stripePriceInspectorService,
        protected StripeSubscriptionManagementService $stripeSubscriptionManagementService,
        protected StripeSubscriptionPlanChangeService $stripeSubscriptionPlanChangeService
    ) {
    }

public function status(Request $request): View
{
    $tenant = tenant();
    $tenantId = $tenant->id;

    $subscription = $this->tenantPlanService->getCurrentSubscription($tenantId);
    $plan = $this->tenantPlanService->getCurrentPlan($tenantId);

    $billingState = $this->tenantBillingLifecycleService->resolveState($subscription);
    $billingActions = BillingActionResolver::resolve($billingState);
    $availablePlans = $this->billingPlanCatalogService->getPaidPlans();

    $selectedPlanId = old('target_plan_id')
        ?: $request->input('target_plan_id')
            ?: ($plan && ($plan->billing_period ?? null) !== 'trial' ? $plan->id : null)
                ?: optional($availablePlans->first())->id;

    $selectedPlan = $selectedPlanId
        ? $this->billingPlanCatalogService->findPaidPlanById($selectedPlanId)
        : null;

    $selectedPlanAudit = $selectedPlan
        ? $this->stripePriceInspectorService->auditPlan($selectedPlan)
        : null;

    $isSameCurrentPaidPlan = $plan
        && $selectedPlan
        && (int) $plan->id === (int) $selectedPlan->id
        && in_array(($billingState['status'] ?? null), [
            SubscriptionStatuses::ACTIVE,
            SubscriptionStatuses::CANCELLED,
        ], true);

    $canChangeCurrentSubscriptionPlan = $this->canChangePlanOnExistingStripeSubscription(
        $subscription,
        $billingState
    );

    return view('automotive.admin.billing.status', compact(
        'tenant',
        'subscription',
        'plan',
        'billingState',
        'billingActions',
        'availablePlans',
        'selectedPlanId',
        'selectedPlan',
        'selectedPlanAudit',
        'isSameCurrentPaidPlan',
        'canChangeCurrentSubscriptionPlan'
    ));
}

public function renew(Request $request): RedirectResponse
{
    $tenant = tenant();
    $tenantId = $tenant->id;

    $subscription = $this->tenantPlanService->getCurrentSubscription($tenantId);
    $currentPlan = $this->tenantPlanService->getCurrentPlan($tenantId);
    $billingState = $this->tenantBillingLifecycleService->resolveState($subscription);

    if ($this->canChangePlanOnExistingStripeSubscription($subscription, $billingState)) {
        return redirect()
            ->route('automotive.admin.billing.status')
            ->with('error', 'This tenant already has a live Stripe subscription eligible for in-place plan change. Use Change Plan instead of starting a new checkout.');
    }

    $validated = $request->validate([
        'target_plan_id' => ['required', 'integer'],
    ]);

    $targetPlan = $this->billingPlanCatalogService->findPaidPlanById($validated['target_plan_id']);

    if (! $targetPlan) {
        return redirect()
            ->route('automotive.admin.billing.status')
            ->with('error', 'The selected paid plan was not found or is not active.');
    }

    if (
        $currentPlan
        && (int) $currentPlan->id === (int) $targetPlan->id
        && ($billingState['status'] ?? null) === SubscriptionStatuses::ACTIVE
    ) {
        return redirect()
            ->route('automotive.admin.billing.status', ['target_plan_id' => $targetPlan->id])
            ->with('error', 'You are already subscribed to this active plan. Choose another plan or use Manage Billing.');
    }

    if (empty($targetPlan->stripe_price_id)) {
        return redirect()
            ->route('automotive.admin.billing.status', ['target_plan_id' => $targetPlan->id])
            ->with('error', 'The selected paid plan is not linked to a Stripe price yet.');
    }

    $targetPlanAudit = $this->stripePriceInspectorService->auditPlan($targetPlan);

    if (! ($targetPlanAudit['checks']['is_aligned'] ?? false)) {
        return redirect()
            ->route('automotive.admin.billing.status', ['target_plan_id' => $targetPlan->id])
            ->with('error', 'The selected plan price in Stripe does not match the local catalog. Fix the Stripe price mapping before checkout.');
    }

    try {
        $session = $this->paymentGatewayManager
            ->driver('stripe')
            ->createRenewalSession([
                'tenant_id' => $tenantId,
                'subscription_row_id' => $subscription->id ?? null,
                'plan_id' => $targetPlan->id ?? null,
                'stripe_price_id' => $targetPlan->stripe_price_id ?? null,
                'billing_state' => $billingState['status'] ?? null,
                'customer_email' => auth('automotive_admin')->user()?->email,
                    'success_url' => route('automotive.admin.billing.success'),
                    'cancel_url' => route('automotive.admin.billing.cancel'),
                    'plan_for_audit' => (array) $targetPlan,
                ]);
        } catch (Throwable $e) {
        Log::error('Billing renew controller fatal error', [
            'message' => $e->getMessage(),
            'tenant_id' => $tenantId,
            'current_plan_id' => $currentPlan->id ?? null,
            'target_plan_id' => $targetPlan->id ?? null,
            'stripe_price_id' => $targetPlan->stripe_price_id ?? null,
        ]);

        return redirect()
            ->route('automotive.admin.billing.status', ['target_plan_id' => $targetPlan->id])
            ->with('error', 'Billing configuration error. Please check Stripe settings.');
    }

    if (! empty($session['success']) && ! empty($session['checkout_url'])) {
        return redirect()->away($session['checkout_url']);
    }

    return redirect()
        ->route('automotive.admin.billing.status', ['target_plan_id' => $targetPlan->id])
        ->with('error', $session['message'] ?? 'Unable to start the renewal session.');
}

public function changePlan(Request $request): RedirectResponse
{
    $tenant = tenant();
    $tenantId = $tenant->id;

    $subscriptionRow = $this->tenantPlanService->getCurrentSubscription($tenantId);
    $currentPlan = $this->tenantPlanService->getCurrentPlan($tenantId);
    $billingState = $this->tenantBillingLifecycleService->resolveState($subscriptionRow);

    if (! $this->canChangePlanOnExistingStripeSubscription($subscriptionRow, $billingState)) {
        return redirect()
            ->route('automotive.admin.billing.status')
            ->with('error', 'This tenant is not eligible for in-place Stripe plan change right now. Start a checkout instead if billing setup is still needed.');
    }

    $validated = $request->validate([
        'target_plan_id' => ['required', 'integer'],
    ]);

    $targetPlanCatalogRow = $this->billingPlanCatalogService->findPaidPlanById($validated['target_plan_id']);

    if (! $targetPlanCatalogRow) {
        return redirect()
            ->route('automotive.admin.billing.status')
            ->with('error', 'The selected paid plan was not found or is not active.');
    }

    if ((string) ($targetPlanCatalogRow->billing_period ?? '') === 'trial') {
        return redirect()
            ->route('automotive.admin.billing.status')
            ->with('error', 'Trial plans cannot replace a live Stripe subscription.');
    }

    if (empty($targetPlanCatalogRow->stripe_price_id)) {
        return redirect()
            ->route('automotive.admin.billing.status', ['target_plan_id' => $targetPlanCatalogRow->id])
            ->with('error', 'The selected paid plan is not linked to a Stripe price yet.');
    }

    $targetPlanAudit = $this->stripePriceInspectorService->auditPlan($targetPlanCatalogRow);

    if (! ($targetPlanAudit['checks']['is_aligned'] ?? false)) {
        return redirect()
            ->route('automotive.admin.billing.status', ['target_plan_id' => $targetPlanCatalogRow->id])
            ->with('error', 'The selected plan price in Stripe does not match the local catalog. Fix the Stripe price mapping before changing the live subscription.');
    }

    if (
        $currentPlan
        && (int) $currentPlan->id === (int) $targetPlanCatalogRow->id
        && (string) ($subscriptionRow->gateway_price_id ?? '') === (string) $targetPlanCatalogRow->stripe_price_id
    ) {
        return redirect()
            ->route('automotive.admin.billing.status', ['target_plan_id' => $targetPlanCatalogRow->id])
            ->with('error', 'The subscription is already on this plan.');
    }

    $subscription = Subscription::query()->find($subscriptionRow->id ?? null);

    if (! $subscription) {
        return redirect()
            ->route('automotive.admin.billing.status')
            ->with('error', 'The local subscription record could not be loaded for plan change.');
    }

    $targetPlan = Plan::query()->find($targetPlanCatalogRow->id);

    if (! $targetPlan) {
        return redirect()
            ->route('automotive.admin.billing.status')
            ->with('error', 'The selected plan model could not be loaded.');
    }

    $result = $this->stripeSubscriptionPlanChangeService->changePlan($subscription, $targetPlan);

    return redirect()
        ->route('automotive.admin.billing.status', ['target_plan_id' => $targetPlan->id])
        ->with($result['ok'] ? 'success' : 'error', $result['message']);
}

public function portal(Request $request): RedirectResponse
{
    $tenant = tenant();
    $tenantId = $tenant->id;

    $subscription = $this->tenantPlanService->getCurrentSubscription($tenantId);
    $customerId = (string) ($subscription->gateway_customer_id ?? '');

    $portal = $this->stripeCustomerPortalService->createSession(
        $customerId,
        route('automotive.admin.billing.status')
    );

    if (! empty($portal['success']) && ! empty($portal['url'])) {
        return redirect()->away($portal['url']);
    }

    return redirect()
        ->route('automotive.admin.billing.status')
        ->with('error', $portal['message'] ?? 'Unable to open the billing portal.');
}

public function cancelSubscription(Request $request): RedirectResponse
{
    $tenant = tenant();
    $subscription = $this->tenantPlanService->getCurrentSubscription($tenant->id);

    $result = $this->stripeSubscriptionManagementService->cancelAtPeriodEnd($subscription);

    return redirect()
        ->route('automotive.admin.billing.status')
        ->with($result['success'] ? 'success' : 'error', $result['message']);
}

public function resumeSubscription(Request $request): RedirectResponse
{
    $tenant = tenant();
    $subscription = $this->tenantPlanService->getCurrentSubscription($tenant->id);

    $result = $this->stripeSubscriptionManagementService->resume($subscription);

    return redirect()
        ->route('automotive.admin.billing.status')
        ->with($result['success'] ? 'success' : 'error', $result['message']);
}

public function success(Request $request): RedirectResponse
{
    return redirect()
        ->route('automotive.admin.billing.status')
        ->with('success', 'Your checkout session was completed successfully. Subscription sync will finalize via webhook.');
}

public function cancel(Request $request): RedirectResponse
{
    return redirect()
        ->route('automotive.admin.billing.status')
        ->with('error', 'Checkout was cancelled before completion.');
}

protected function canChangePlanOnExistingStripeSubscription(?object $subscription, array $billingState): bool
{
    if (! $subscription) {
        return false;
    }

    if (($subscription->gateway ?? null) !== 'stripe') {
        return false;
    }

    if (empty($subscription->gateway_subscription_id)) {
        return false;
    }

    $status = (string) ($billingState['status'] ?? '');

    if ($status === SubscriptionStatuses::ACTIVE) {
        return true;
    }

    if ($status === SubscriptionStatuses::CANCELLED) {
        return ! empty($billingState['period_ends_at'])
            && $billingState['period_ends_at']->isFuture();
    }

    return false;
}
}
