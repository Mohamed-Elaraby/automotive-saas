<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Services\Billing\BillingPlanCatalogService;
use App\Services\Billing\PaymentGatewayManager;
use App\Services\Billing\StripeCustomerPortalService;
use App\Services\Billing\StripePriceInspectorService;
use App\Services\Billing\TenantBillingLifecycleService;
use App\Services\Tenancy\TenantPlanService;
use App\Support\Billing\BillingActionResolver;
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
        protected StripePriceInspectorService $stripePriceInspectorService
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

    return view('automotive.admin.billing.status', compact(
        'tenant',
        'subscription',
        'plan',
        'billingState',
        'billingActions',
        'availablePlans',
        'selectedPlanId',
        'selectedPlan',
        'selectedPlanAudit'
    ));
}

public function renew(Request $request): RedirectResponse
{
    $tenant = tenant();
    $tenantId = $tenant->id;

    $subscription = $this->tenantPlanService->getCurrentSubscription($tenantId);
    $currentPlan = $this->tenantPlanService->getCurrentPlan($tenantId);
    $billingState = $this->tenantBillingLifecycleService->resolveState($subscription);

    $validated = $request->validate([
        'target_plan_id' => ['required', 'integer'],
    ]);

    $targetPlan = $this->billingPlanCatalogService->findPaidPlanById($validated['target_plan_id']);

    if (! $targetPlan) {
        return redirect()
            ->route('automotive.admin.billing.status')
            ->with('error', 'The selected paid plan was not found or is not active.');
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
}
