<?php

namespace App\Http\Controllers\Automotive\Front;

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
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class PortalBillingController extends Controller
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

    public function status(Request $request): View|RedirectResponse
    {
        $portal = $this->portalWorkspaceContext($request);

        if ($portal instanceof RedirectResponse) {
            return $portal;
        }

        $billingContext = $this->resolveBillingContext($portal['tenant_id'], $portal['focused_workspace_product']);
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

        return view('automotive.portal.billing.status', [
            'user' => $portal['user'],
            'tenantId' => $portal['tenant_id'],
            'workspaceProducts' => $portal['workspace_products'],
            'focusedWorkspaceProduct' => $portal['focused_workspace_product'],
            'subscription' => $subscription,
            'plan' => $plan,
            'billingProductCode' => $billingProductCode,
            'billingProductName' => $billingProductName,
            'isPrimaryBillingProduct' => $isPrimaryBillingProduct,
            'isAttachedBillingContext' => ! $isPrimaryBillingProduct,
            'billingState' => $billingState,
            'billingActions' => $billingActions,
            'availablePlans' => $availablePlans,
            'selectedPlanId' => $selectedPlanId,
            'selectedPlan' => $selectedPlan,
            'selectedPlanAudit' => $selectedPlanAudit,
            'isSameCurrentPaidPlan' => $isSameCurrentPaidPlan,
            'canChangeCurrentSubscriptionPlan' => $canChangeCurrentSubscriptionPlan,
            'planChangePreview' => $planChangePreview,
            'invoiceHistory' => $invoiceHistory,
            'stripePublishableKey' => $stripePublishableKey,
            'canUpdatePaymentMethodInline' => $canUpdatePaymentMethodInline,
            'systemUrl' => $portal['system_url'],
            'allowSystemAccess' => $portal['allow_system_access'],
        ]);
    }

    public function renew(Request $request): RedirectResponse
    {
        $portal = $this->portalWorkspaceContext($request);
        if ($portal instanceof RedirectResponse) {
            return $portal;
        }

        $billingContext = $this->resolveBillingContext($portal['tenant_id'], $portal['focused_workspace_product']);
        $subscription = $billingContext['subscription'];
        $currentPlan = $billingContext['plan'];
        $billingState = $this->tenantBillingLifecycleService->resolveState($subscription);
        $workspaceCode = $portal['focused_workspace_product']['product_code'] ?? null;

        if ($this->canChangePlanOnExistingStripeSubscription($subscription, $billingState)) {
            return redirect()
                ->route('automotive.portal.billing.status', ['workspace_product' => $workspaceCode])
                ->with('error', 'This workspace product already has a live Stripe subscription eligible for in-place plan change. Use Change Plan instead.');
        }

        $validated = $request->validate([
            'target_plan_id' => ['required', 'integer'],
        ]);

        $targetPlan = $this->checkoutStripePlanRecoveryService->recoverPaidPlan(
            $validated['target_plan_id'],
            $billingContext['product_code']
        );

        if (! $targetPlan) {
            return redirect()
                ->route('automotive.portal.billing.status', ['workspace_product' => $workspaceCode])
                ->with('error', 'The selected paid plan was not found or is not active.');
        }

        if (
            $currentPlan
            && (int) $currentPlan->id === (int) $targetPlan->id
            && ($billingState['status'] ?? null) === SubscriptionStatuses::ACTIVE
        ) {
            return redirect()
                ->route('automotive.portal.billing.status', ['target_plan_id' => $targetPlan->id, 'workspace_product' => $workspaceCode])
                ->with('error', 'You are already subscribed to this active plan.');
        }

        if (empty($targetPlan->stripe_price_id)) {
            return redirect()
                ->route('automotive.portal.billing.status', ['target_plan_id' => $targetPlan->id, 'workspace_product' => $workspaceCode])
                ->with('error', 'The selected paid plan is not linked to a Stripe price yet.');
        }

        $targetPlanAudit = $this->stripePriceInspectorService->auditPlan($targetPlan);

        if (! ($targetPlanAudit['checks']['is_aligned'] ?? false)) {
            return redirect()
                ->route('automotive.portal.billing.status', ['target_plan_id' => $targetPlan->id, 'workspace_product' => $workspaceCode])
                ->with('error', 'The selected plan price in Stripe does not match the local catalog. Fix the Stripe price mapping before checkout.');
        }

        try {
            $session = $this->checkoutStripePlanRecoveryService->retryIfStripePriceNeedsRepair(
                $targetPlan,
                $billingContext['product_code'],
                fn (object $checkoutPlan) => $this->paymentGatewayManager
                    ->driver('stripe')
                    ->createRenewalSession([
                        'tenant_id' => $portal['tenant_id'],
                        'subscription_row_id' => $billingContext['is_primary'] ? ($subscription->id ?? null) : null,
                        'tenant_product_subscription_id' => $billingContext['is_primary'] ? null : ($subscription->id ?? null),
                        'plan_id' => $checkoutPlan->id ?? null,
                        'stripe_price_id' => $checkoutPlan->stripe_price_id ?? null,
                        'billing_state' => $billingState['status'] ?? null,
                        'customer_email' => $portal['user']->email ?? null,
                        'success_url' => route('automotive.portal.billing.success', ['workspace_product' => $workspaceCode]),
                        'cancel_url' => route('automotive.portal.billing.cancel', ['workspace_product' => $workspaceCode]),
                        'product_scope' => $billingContext['product_code'],
                        'plan_for_audit' => (array) $checkoutPlan,
                    ])
            );
        } catch (Throwable $e) {
            Log::error('Portal billing renew controller fatal error', [
                'message' => $e->getMessage(),
                'tenant_id' => $portal['tenant_id'],
                'target_plan_id' => $targetPlan->id ?? null,
            ]);

            return redirect()
                ->route('automotive.portal.billing.status', ['target_plan_id' => $targetPlan->id, 'workspace_product' => $workspaceCode])
                ->with('error', 'Billing configuration error. Please check Stripe settings.');
        }

        if (! empty($session['success']) && ! empty($session['checkout_url']) && ! empty($session['session_id'])) {
            if (! $billingContext['is_primary']) {
                $productSubscription = TenantProductSubscription::query()->find($subscription->id ?? null);

                if (! $productSubscription) {
                    return redirect()
                        ->route('automotive.portal.billing.status', ['target_plan_id' => $targetPlan->id, 'workspace_product' => $workspaceCode])
                        ->with('error', 'The product subscription record could not be loaded before redirecting to Stripe checkout.');
                }

                $productSubscription->fill([
                    'plan_id' => (int) $targetPlan->id,
                    'status' => SubscriptionStatuses::PAST_DUE,
                    'gateway' => 'stripe',
                    'gateway_checkout_session_id' => (string) $session['session_id'],
                    'gateway_price_id' => (string) $targetPlan->stripe_price_id,
                    'gateway_subscription_id' => null,
                ])->save();

                return redirect()->away($session['checkout_url']);
            }

            $subscriptionModel = ! empty($subscription->id)
                ? Subscription::query()->find($subscription->id)
                : null;

            if (! $subscriptionModel) {
                return redirect()
                    ->route('automotive.portal.billing.status', ['target_plan_id' => $targetPlan->id, 'workspace_product' => $workspaceCode])
                    ->with('error', 'The local subscription record could not be loaded before redirecting to Stripe checkout.');
            }

            $subscriptionModel->fill([
                'gateway' => 'stripe',
                'gateway_checkout_session_id' => (string) $session['session_id'],
            ]);
            $subscriptionModel->gateway_subscription_id = null;
            $subscriptionModel->save();

            return redirect()->away($session['checkout_url']);
        }

        return redirect()
            ->route('automotive.portal.billing.status', ['target_plan_id' => $targetPlan->id, 'workspace_product' => $workspaceCode])
            ->with('error', $session['message'] ?? 'Unable to start the renewal session.');
    }

    public function changePlan(Request $request): RedirectResponse
    {
        $portal = $this->portalWorkspaceContext($request);
        if ($portal instanceof RedirectResponse) {
            return $portal;
        }

        $billingContext = $this->resolveBillingContext($portal['tenant_id'], $portal['focused_workspace_product']);
        $subscriptionRow = $billingContext['subscription'];
        $currentPlan = $billingContext['plan'];
        $billingState = $this->tenantBillingLifecycleService->resolveState($subscriptionRow);
        $workspaceCode = $portal['focused_workspace_product']['product_code'] ?? null;

        if (! $this->canChangePlanOnExistingStripeSubscription($subscriptionRow, $billingState)) {
            return redirect()
                ->route('automotive.portal.billing.status', ['workspace_product' => $workspaceCode])
                ->with('error', 'This workspace product is not eligible for in-place Stripe plan change right now.');
        }

        $validated = $request->validate([
            'target_plan_id' => ['required', 'integer'],
            'preview_proration_date' => ['nullable', 'integer'],
        ]);

        $targetPlanCatalogRow = $this->billingPlanCatalogService->findPaidPlanById($validated['target_plan_id'], $billingContext['product_code']);

        if (! $targetPlanCatalogRow) {
            return redirect()
                ->route('automotive.portal.billing.status', ['workspace_product' => $workspaceCode])
                ->with('error', 'The selected paid plan was not found or is not active.');
        }

        if ((string) ($targetPlanCatalogRow->billing_period ?? '') === 'trial') {
            return redirect()
                ->route('automotive.portal.billing.status', ['workspace_product' => $workspaceCode])
                ->with('error', 'Trial plans cannot replace a live Stripe subscription.');
        }

        if (empty($targetPlanCatalogRow->stripe_price_id)) {
            return redirect()
                ->route('automotive.portal.billing.status', ['target_plan_id' => $targetPlanCatalogRow->id, 'workspace_product' => $workspaceCode])
                ->with('error', 'The selected paid plan is not linked to a Stripe price yet.');
        }

        $targetPlanAudit = $this->stripePriceInspectorService->auditPlan($targetPlanCatalogRow);

        if (! ($targetPlanAudit['checks']['is_aligned'] ?? false)) {
            return redirect()
                ->route('automotive.portal.billing.status', ['target_plan_id' => $targetPlanCatalogRow->id, 'workspace_product' => $workspaceCode])
                ->with('error', 'The selected plan price in Stripe does not match the local catalog.');
        }

        if (
            $currentPlan
            && (int) $currentPlan->id === (int) $targetPlanCatalogRow->id
            && (string) ($subscriptionRow->gateway_price_id ?? '') === (string) $targetPlanCatalogRow->stripe_price_id
        ) {
            return redirect()
                ->route('automotive.portal.billing.status', ['target_plan_id' => $targetPlanCatalogRow->id, 'workspace_product' => $workspaceCode])
                ->with('error', 'The subscription is already on this plan.');
        }

        $targetPlan = Plan::query()->find($targetPlanCatalogRow->id);

        if (! $targetPlan) {
            return redirect()
                ->route('automotive.portal.billing.status', ['workspace_product' => $workspaceCode])
                ->with('error', 'The selected plan model could not be loaded.');
        }

        if ($billingContext['is_primary']) {
            $subscription = Subscription::query()->find($subscriptionRow->id ?? null);

            if (! $subscription) {
                return redirect()
                    ->route('automotive.portal.billing.status', ['workspace_product' => $workspaceCode])
                    ->with('error', 'The local subscription record could not be loaded for plan change.');
            }

            $result = $this->stripeSubscriptionPlanChangeService->changePlan(
                $subscription,
                $targetPlan,
                ! empty($validated['preview_proration_date']) ? (int) $validated['preview_proration_date'] : null
            );
        } else {
            $productSubscription = TenantProductSubscription::query()->find($subscriptionRow->id ?? null);

            if (! $productSubscription) {
                return redirect()
                    ->route('automotive.portal.billing.status', ['workspace_product' => $workspaceCode])
                    ->with('error', 'The local product subscription record could not be loaded for plan change.');
            }

            $result = $this->stripeTenantProductSubscriptionPlanChangeService->changePlan(
                $productSubscription,
                $targetPlan,
                null
            );
        }

        return redirect()
            ->route('automotive.portal.billing.status', ['target_plan_id' => $targetPlan->id, 'workspace_product' => $workspaceCode])
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function createSetupIntent(Request $request): JsonResponse
    {
        $portal = $this->portalWorkspaceContext($request);
        if ($portal instanceof RedirectResponse) {
            return response()->json(['ok' => false, 'message' => 'Workspace billing is not available yet.'], 422);
        }

        $subscriptionRow = $this->resolveBillingContext($portal['tenant_id'], $portal['focused_workspace_product'])['subscription'];

        if (! $subscriptionRow || ($subscriptionRow->gateway ?? null) !== 'stripe') {
            return response()->json([
                'ok' => false,
                'message' => 'This workspace product does not have a live Stripe billing record.',
            ], 422);
        }

        $result = $this->stripeSetupIntentService->createForCustomer(
            (string) ($subscriptionRow->gateway_customer_id ?? '')
        );

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function saveDefaultPaymentMethod(Request $request): JsonResponse
    {
        $portal = $this->portalWorkspaceContext($request);
        if ($portal instanceof RedirectResponse) {
            return response()->json(['ok' => false, 'message' => 'Workspace billing is not available yet.'], 422);
        }

        $billingContext = $this->resolveBillingContext($portal['tenant_id'], $portal['focused_workspace_product']);
        $subscriptionRow = $billingContext['subscription'];

        $validated = $request->validate([
            'payment_method_id' => ['required', 'string'],
        ]);

        if (! $subscriptionRow || empty($subscriptionRow->id)) {
            return response()->json([
                'ok' => false,
                'message' => 'No local subscription record could be loaded.',
            ], 422);
        }

        $subscription = $billingContext['is_primary']
            ? Subscription::query()->find($subscriptionRow->id)
            : TenantProductSubscription::query()->find($subscriptionRow->id);

        if (! $subscription) {
            return response()->json([
                'ok' => false,
                'message' => 'No local subscription model could be loaded.',
            ], 422);
        }

        $result = $this->stripePaymentMethodManagementService->setDefaultPaymentMethod(
            $subscription,
            $validated['payment_method_id']
        );

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function portal(Request $request): RedirectResponse
    {
        $portal = $this->portalWorkspaceContext($request);
        if ($portal instanceof RedirectResponse) {
            return $portal;
        }

        $billingContext = $this->resolveBillingContext($portal['tenant_id'], $portal['focused_workspace_product']);
        $subscription = $billingContext['subscription'];
        $workspaceCode = $portal['focused_workspace_product']['product_code'] ?? null;
        $customerId = (string) ($subscription->gateway_customer_id ?? '');

        $portalSession = $this->stripeCustomerPortalService->createSession(
            $customerId,
            route('automotive.portal.billing.status', ['workspace_product' => $workspaceCode])
        );

        if (! empty($portalSession['success']) && ! empty($portalSession['url'])) {
            return redirect()->away($portalSession['url']);
        }

        return redirect()
            ->route('automotive.portal.billing.status', ['workspace_product' => $workspaceCode])
            ->with('error', $portalSession['message'] ?? 'Unable to open the billing portal.');
    }

    public function cancelSubscription(Request $request): RedirectResponse
    {
        $portal = $this->portalWorkspaceContext($request);
        if ($portal instanceof RedirectResponse) {
            return $portal;
        }

        $workspaceCode = $portal['focused_workspace_product']['product_code'] ?? null;
        $subscription = $this->resolveBillingContext($portal['tenant_id'], $portal['focused_workspace_product'])['subscription'];
        $result = $this->stripeSubscriptionManagementService->cancelAtPeriodEnd($subscription);

        return redirect()
            ->route('automotive.portal.billing.status', ['workspace_product' => $workspaceCode])
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function resumeSubscription(Request $request): RedirectResponse
    {
        $portal = $this->portalWorkspaceContext($request);
        if ($portal instanceof RedirectResponse) {
            return $portal;
        }

        $workspaceCode = $portal['focused_workspace_product']['product_code'] ?? null;
        $subscription = $this->resolveBillingContext($portal['tenant_id'], $portal['focused_workspace_product'])['subscription'];
        $result = $this->stripeSubscriptionManagementService->resume($subscription);

        return redirect()
            ->route('automotive.portal.billing.status', ['workspace_product' => $workspaceCode])
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function success(Request $request): RedirectResponse
    {
        return redirect()
            ->route('automotive.portal.billing.status', ['workspace_product' => $request->query('workspace_product')])
            ->with('success', 'Your checkout session was completed successfully. Subscription sync will finalize via webhook.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        return redirect()
            ->route('automotive.portal.billing.status', ['workspace_product' => $request->query('workspace_product')])
            ->with('error', 'Checkout was cancelled before completion.');
    }

    protected function portalWorkspaceContext(Request $request): array|RedirectResponse
    {
        $user = Auth::guard('web')->user();
        abort_unless($user, 403);

        $tenantIds = $this->tenantIdsForUser($user);
        if ($tenantIds->isEmpty()) {
            return redirect()
                ->route('automotive.portal')
                ->withErrors(['portal' => 'Workspace billing becomes available after the first workspace is provisioned.']);
        }

        $subscription = $this->latestSubscriptionForUser($user);
        $tenantId = (string) ($subscription->tenant_id ?? ($tenantIds->first() ?? ''));

        $workspaceProducts = $this->tenantWorkspaceProductService->getWorkspaceProducts($tenantId);
        if ($workspaceProducts->isEmpty()) {
            $fallbackProduct = $this->fallbackWorkspaceProduct($subscription);
            if ($fallbackProduct) {
                $workspaceProducts = collect([$fallbackProduct]);
            }
        }

        $focusedWorkspaceProduct = $this->tenantWorkspaceProductService->resolveFocusedProduct(
            $workspaceProducts,
            $request->input('workspace_product', $request->query('workspace_product', $request->query('product')))
        );

        if (! $focusedWorkspaceProduct) {
            return redirect()
                ->route('automotive.portal')
                ->withErrors(['portal' => 'No active workspace product could be resolved for billing.']);
        }

        $domains = $this->domainsForTenant($tenantId);
        $primaryDomain = $domains->first();
        $systemUrl = $primaryDomain['admin_login_url'] ?? null;
        $accessSubscription = $subscription ?: $this->tenantPlanService->getCurrentSubscription($tenantId);
        $allowSystemAccess = $this->hasSystemAccess($accessSubscription) && filled($systemUrl);

        return [
            'user' => $user,
            'tenant_id' => $tenantId,
            'workspace_products' => $workspaceProducts,
            'focused_workspace_product' => $focusedWorkspaceProduct,
            'system_url' => $systemUrl,
            'allow_system_access' => $allowSystemAccess,
        ];
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

        $plan = $subscription?->plan_id ? Plan::query()->find($subscription->plan_id) : null;

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
        if (! $subscription || ($subscription->gateway ?? null) !== 'stripe' || empty($subscription->gateway_subscription_id)) {
            return false;
        }

        $status = (string) ($billingState['status'] ?? '');

        if ($status === SubscriptionStatuses::ACTIVE) {
            return true;
        }

        return $status === SubscriptionStatuses::CANCELLED
            && ! empty($billingState['period_ends_at'])
            && $billingState['period_ends_at']->isFuture();
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

    protected function fallbackWorkspaceProduct(?object $subscription): ?array
    {
        if (! $subscription) {
            return null;
        }

        $plan = ! empty($subscription->plan_id)
            ? Plan::query()->find((int) $subscription->plan_id)
            : null;
        $product = $plan?->product_id
            ? Product::query()->find((int) $plan->product_id)
            : null;

        if (! $product) {
            return null;
        }

        $family = $this->workspaceManifestService->resolveFamilyFromText(strtolower(implode(' ', array_filter([
            (string) $product->code,
            (string) $product->slug,
            (string) $product->name,
        ])))) ?: $this->workspaceManifestService->defaultFamily();

        return [
            'subscription_id' => (int) ($subscription->id ?? 0),
            'tenant_id' => (string) ($subscription->tenant_id ?? ''),
            'product_id' => (int) $product->id,
            'product_code' => (string) $product->code,
            'product_name' => (string) $product->name,
            'product_slug' => (string) $product->slug,
            'product_family' => $family,
            'plan_name' => (string) ($plan->name ?? ''),
            'capabilities' => [],
            'status' => (string) ($subscription->status ?? ''),
            'status_label' => strtoupper(str_replace('_', ' ', (string) ($subscription->status ?? 'unknown'))),
            'is_accessible' => in_array((string) ($subscription->status ?? ''), SubscriptionStatuses::accessAllowedStatuses(), true),
            'is_primary_workspace_product' => $family === $this->workspaceManifestService->defaultFamily(),
        ];
    }

    protected function centralConnectionName(): string
    {
        return (string) (Config::get('tenancy.database.central_connection') ?: Config::get('database.default'));
    }

    protected function latestSubscriptionForUser(object $user): ?object
    {
        $connection = $this->centralConnectionName();

        if (! Schema::connection($connection)->hasTable('tenant_users')) {
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
                ->where('products.code', $this->workspaceManifestService->defaultFamily())
                ->orderByDesc('tenant_product_subscriptions.id')
                ->select('tenant_product_subscriptions.*')
                ->first();

            if ($productSubscription) {
                return $productSubscription;
            }
        }

        return Schema::connection($connection)->hasTable('subscriptions')
            ? DB::connection($connection)
                ->table('subscriptions')
                ->whereIn('tenant_id', $tenantIds->all())
                ->orderByDesc('id')
                ->first()
            : null;
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
                    'admin_login_url' => rtrim($baseUrl, '/') . '/workspace',
                ];
            })
            ->values();
    }

    protected function hasSystemAccess(?object $subscription): bool
    {
        if (! $subscription || ! filled($subscription->gateway_subscription_id ?? null)) {
            return false;
        }

        $status = (string) ($subscription->status ?? '');
        if ($status === SubscriptionStatuses::ACTIVE) {
            return true;
        }

        return $status === SubscriptionStatuses::CANCELLED
            && filled($subscription->ends_at ?? null)
            && Carbon::parse((string) $subscription->ends_at)->isFuture();
    }
}
