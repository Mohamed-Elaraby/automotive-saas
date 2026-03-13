<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Services\Billing\PaymentGatewayManager;
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
        protected PaymentGatewayManager $paymentGatewayManager
    ) {
    }

public function status(): View
{
    $tenant = tenant();
    $tenantId = $tenant->id;

    $subscription = $this->tenantPlanService->getCurrentSubscription($tenantId);
    $plan = $this->tenantPlanService->getCurrentPlan($tenantId);

    $billingState = $this->tenantBillingLifecycleService->resolveState($subscription);
    $billingActions = BillingActionResolver::resolve($billingState);

    return view('automotive.admin.billing.status', compact(
        'tenant',
        'subscription',
        'plan',
        'billingState',
        'billingActions'
    ));
}

public function renew(Request $request): RedirectResponse
{
    $tenant = tenant();
    $tenantId = $tenant->id;

    $subscription = $this->tenantPlanService->getCurrentSubscription($tenantId);
    $plan = $this->tenantPlanService->getCurrentPlan($tenantId);
    $billingState = $this->tenantBillingLifecycleService->resolveState($subscription);

    if (! $plan) {
        return redirect()
            ->route('automotive.admin.billing.status')
            ->with('error', 'No billing plan is linked to this tenant.');
    }

    if (empty($plan->stripe_price_id)) {
        return redirect()
            ->route('automotive.admin.billing.status')
            ->with('error', 'The current plan is not linked to a Stripe price yet.');
    }

    try {
        $session = $this->paymentGatewayManager
            ->driver('stripe')
            ->createRenewalSession([
                'tenant_id' => $tenantId,
                'subscription_row_id' => $subscription->id ?? null,
                'plan_id' => $plan->id ?? null,
                'stripe_price_id' => $plan->stripe_price_id ?? null,
                'billing_state' => $billingState['status'] ?? null,
                'customer_email' => auth('automotive_admin')->user()?->email,
                    'success_url' => route('automotive.admin.billing.success'),
                    'cancel_url' => route('automotive.admin.billing.cancel'),
                ]);
        } catch (Throwable $e) {
        Log::error('Billing renew controller fatal error', [
            'message' => $e->getMessage(),
            'tenant_id' => $tenantId,
            'plan_id' => $plan->id ?? null,
            'stripe_price_id' => $plan->stripe_price_id ?? null,
        ]);

        return redirect()
            ->route('automotive.admin.billing.status')
            ->with('error', 'Billing configuration error. Please check Stripe settings.');
    }

    if (! empty($session['success']) && ! empty($session['checkout_url'])) {
        return redirect()->away($session['checkout_url']);
    }

    return redirect()
        ->route('automotive.admin.billing.status')
        ->with('error', $session['message'] ?? 'Unable to start the renewal session.');
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
