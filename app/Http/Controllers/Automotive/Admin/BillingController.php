<?php

namespace App\Http\Controllers\Automotive\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\TenantProductSubscription;
use App\Services\Billing\BillingPlanCatalogService;
use App\Services\Billing\CheckoutStripePlanRecoveryService;
use App\Services\Billing\PaymentGatewayManager;
use App\Services\Billing\StripeCustomerPortalService;
use App\Services\Billing\StripeInvoiceHistoryService;
use App\Services\Billing\StripePaymentMethodManagementService;
use App\Services\Billing\StripePriceInspectorService;
use App\Services\Billing\StripeSetupIntentService;
use App\Services\Billing\StripeSubscriptionManagementService;
use App\Services\Billing\StripeSubscriptionPlanChangeService;
use App\Services\Billing\StripeSubscriptionPlanPreviewService;
use App\Services\Billing\StripeTenantProductSubscriptionPlanChangeService;
use App\Services\Billing\TenantBillingLifecycleService;
use App\Services\Tenancy\TenantPlanService;
use App\Services\Tenancy\TenantWorkspaceProductService;
use App\Services\Tenancy\WorkspaceManifestService;
use App\Support\Billing\BillingActionResolver;
use App\Support\Billing\SubscriptionStatuses;
use Illuminate\Http\JsonResponse;
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
        protected CheckoutStripePlanRecoveryService $checkoutStripePlanRecoveryService,
        protected StripeCustomerPortalService $stripeCustomerPortalService,
        protected StripePriceInspectorService $stripePriceInspectorService,
        protected StripeSubscriptionManagementService $stripeSubscriptionManagementService,
        protected StripeSubscriptionPlanChangeService $stripeSubscriptionPlanChangeService,
        protected StripeTenantProductSubscriptionPlanChangeService $stripeTenantProductSubscriptionPlanChangeService,
        protected StripeSubscriptionPlanPreviewService $stripeSubscriptionPlanPreviewService,
        protected StripeInvoiceHistoryService $stripeInvoiceHistoryService,
        protected StripeSetupIntentService $stripeSetupIntentService,
        protected StripePaymentMethodManagementService $stripePaymentMethodManagementService,
        protected TenantWorkspaceProductService $tenantWorkspaceProductService,
        protected WorkspaceManifestService $workspaceManifestService
    ) {
    }

public function status(Request $request): View
{
    $tenant = tenant();
    $tenantId = $tenant->id;
    $workspaceProducts = $this->tenantWorkspaceProductService->getWorkspaceProducts($tenantId);
    $focusedWorkspaceProduct = $this->tenantWorkspaceProductService->resolveFocusedProduct(
        $workspaceProducts,
        $request->query('workspace_product')
    );
    $billingContext = $this->resolveBillingContext($tenantId, $focusedWorkspaceProduct);

    $subscription = $billingContext['subscription'];
    $plan = $billingContext['plan'];
    $billingProductCode = $billingContext['product_code'];
    $billingProductName = $billingContext['product_name'];
    $isPrimaryBillingProduct = $billingContext['is_primary'];

    $billingState = $this->tenantBillingLifecycleService->resolveState($subscription);
    $billingActions = BillingActionResolver::resolve($billingState);
    $availablePlans = $this->billingPlanCatalogService->getPaidPlans($billingProductCode);

    $selectedPlanId = old('target_plan_id')
        ?: $request->input('target_plan_id')
            ?: ($plan && ($plan->billing_period ?? null) !== 'trial' ? $plan->id : null)
                ?: optional($availablePlans->first())->id;

    $selectedPlan = $selectedPlanId
        ? $this->billingPlanCatalogService->findPaidPlanById($selectedPlanId, $billingProductCode)
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
    $isReadOnlyBillingContext = false;
    $isAttachedBillingContext = ! $isPrimaryBillingProduct;

    $planChangePreview = null;

    if (
        $canChangeCurrentSubscriptionPlan
        && $isPrimaryBillingProduct
        && $selectedPlan
        && ! $isSameCurrentPaidPlan
        && ($selectedPlanAudit['checks']['is_aligned'] ?? false)
        && ! empty($subscription->id)
    ) {
        $subscriptionModel = Subscription::query()->find($subscription->id);
        $selectedPlanModel = Plan::query()->find($selectedPlan->id);

        if ($subscriptionModel && $selectedPlanModel) {
            $planChangePreview = $this->stripeSubscriptionPlanPreviewService->previewPlanChange(
                $subscriptionModel,
                $selectedPlanModel
            );
        }
    }

    $invoiceHistory = [
        'ok' => true,
        'invoices' => [],
        'message' => null,
    ];

    if (($subscription->gateway ?? null) === 'stripe' && ! empty($subscription->gateway_customer_id)) {
        $invoiceHistory = $this->stripeInvoiceHistoryService->listCustomerInvoices(
            (string) $subscription->gateway_customer_id
        );
    }

    $stripePublishableKey = trim((string) config('billing.gateways.stripe.key'));
    $canUpdatePaymentMethodInline =
        ($subscription->gateway ?? null) === 'stripe'
        && ! empty($subscription->gateway_customer_id)
        && ! empty($subscription->gateway_subscription_id)
        && $stripePublishableKey !== '';

    $portalBillingUrl = route('automotive.portal.billing.status', array_filter([
        'workspace_product' => $focusedWorkspaceProduct['product_code'] ?? null,
    ]));
    $portalOverviewUrl = route('automotive.portal');
    $decommissionMessage = 'Billing and account control now live in the customer portal. Tenant admin remains runtime-only.';

    return view('automotive.admin.billing.status', compact(
        'tenant',
        'subscription',
        'plan',
        'workspaceProducts',
        'focusedWorkspaceProduct',
        'billingProductCode',
        'billingProductName',
        'isPrimaryBillingProduct',
        'isAttachedBillingContext',
        'isReadOnlyBillingContext',
        'billingState',
        'billingActions',
        'availablePlans',
        'selectedPlanId',
        'selectedPlan',
        'selectedPlanAudit',
        'isSameCurrentPaidPlan',
        'canChangeCurrentSubscriptionPlan',
        'planChangePreview',
        'invoiceHistory',
        'stripePublishableKey',
        'canUpdatePaymentMethodInline',
        'portalBillingUrl',
        'portalOverviewUrl',
        'decommissionMessage'
    ));
}

public function renew(Request $request): RedirectResponse
{
    return $this->redirectBillingDecommission($request);
}

public function changePlan(Request $request): RedirectResponse
{
    return $this->redirectBillingDecommission($request);
}

public function createSetupIntent(Request $request): JsonResponse
{
    return response()->json([
        'ok' => false,
        'message' => 'Billing and payment method changes moved to the customer portal.',
    ], 410);
}

public function saveDefaultPaymentMethod(Request $request): JsonResponse
{
    return response()->json([
        'ok' => false,
        'message' => 'Billing and payment method changes moved to the customer portal.',
    ], 410);
}

public function portal(Request $request): RedirectResponse
{
    return $this->redirectBillingDecommission($request);
}

public function cancelSubscription(Request $request): RedirectResponse
{
    return $this->redirectBillingDecommission($request);
}

public function resumeSubscription(Request $request): RedirectResponse
{
    return $this->redirectBillingDecommission($request);
}

public function success(Request $request): RedirectResponse
{
    return redirect()
        ->route('automotive.admin.billing.status', ['workspace_product' => $request->query('workspace_product')])
        ->with('success', 'Billing is now managed in the customer portal. Continue there for subscription updates and invoices.');
}

public function cancel(Request $request): RedirectResponse
{
    return redirect()
        ->route('automotive.admin.billing.status', ['workspace_product' => $request->query('workspace_product')])
        ->with('error', 'Checkout and subscription changes now continue from the customer portal.');
}

protected function redirectBillingDecommission(Request $request): RedirectResponse
{
    return redirect()
        ->route('automotive.admin.billing.status', ['workspace_product' => $request->input('workspace_product', $request->query('workspace_product'))])
        ->with('error', 'Billing actions moved to the customer portal. Tenant admin is now runtime-only.');
}

protected function resolveBillingContext(string $tenantId, ?array $focusedWorkspaceProduct): array
{
    $defaultFamily = $this->workspaceManifestService->defaultFamily();
    $focusedProductCode = (string) ($focusedWorkspaceProduct['product_code'] ?? '');
    $focusedFamily = (string) ($focusedWorkspaceProduct['product_family'] ?? '');
    $isPrimary = $focusedFamily === $defaultFamily || $focusedProductCode === '';

    if ($isPrimary) {
        $subscription = $this->tenantPlanService->getCurrentSubscription($tenantId);
        $plan = $this->tenantPlanService->getCurrentPlan($tenantId);
        [$resolvedProductCode, $resolvedProductName] = $this->resolvePrimaryBillingProductIdentity(
            $focusedWorkspaceProduct,
            $plan,
            $subscription
        );

        return [
            'subscription' => $subscription,
            'plan' => $plan,
            'product_code' => $resolvedProductCode,
            'product_name' => $resolvedProductName,
            'is_primary' => true,
        ];
    }

    $subscription = TenantProductSubscription::query()
        ->where('tenant_id', $tenantId)
        ->where('product_id', (int) ($focusedWorkspaceProduct['product_id'] ?? 0))
        ->orderByDesc('id')
        ->first();

    $plan = null;
    if ($subscription?->plan_id) {
        $plan = Plan::query()->find($subscription->plan_id);
    }

    return [
        'subscription' => $subscription,
        'plan' => $plan,
        'product_code' => $focusedProductCode,
        'product_name' => (string) ($focusedWorkspaceProduct['product_name'] ?? 'Workspace Product'),
        'is_primary' => false,
    ];
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

protected function resolvePrimaryBillingProductIdentity(?array $focusedWorkspaceProduct, ?Plan $plan, ?object $subscription): array
{
    if (! empty($focusedWorkspaceProduct['product_code'])) {
        return [
            (string) $focusedWorkspaceProduct['product_code'],
            (string) ($focusedWorkspaceProduct['product_name'] ?? 'Primary Workspace Product'),
        ];
    }

    if ($plan?->product_id) {
        $product = Product::query()->find($plan->product_id);

        if ($product) {
            return [(string) $product->code, (string) $product->name];
        }
    }

    if (! empty($subscription->product_id)) {
        $product = Product::query()->find((int) $subscription->product_id);

        if ($product) {
            return [(string) $product->code, (string) $product->name];
        }
    }

    $defaultFamily = $this->workspaceManifestService->defaultFamily();
    $defaultExperience = $this->workspaceManifestService->experience($defaultFamily);

    return [
        $defaultFamily,
        (string) ($defaultExperience['title'] ?? 'Primary Workspace Product'),
    ];
}
}
