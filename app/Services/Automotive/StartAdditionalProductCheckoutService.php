<?php

namespace App\Services\Automotive;

use App\Models\CustomerOnboardingProfile;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductEnablementRequest;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Billing\BillingPlanCatalogService;
use App\Services\Billing\PaymentGatewayManager;
use App\Support\Billing\SubscriptionStatuses;
use Illuminate\Support\Facades\DB;

class StartAdditionalProductCheckoutService
{
    protected const PRIMARY_PRODUCT_CODE = 'automotive_service';

    public function __construct(
        protected BillingPlanCatalogService $billingPlanCatalogService,
        protected PaymentGatewayManager $paymentGatewayManager
    ) {
    }

    public function start(User $user, CustomerOnboardingProfile $profile, int $planId, int $productId): array
    {
        $product = Product::query()->find($productId);
        $plan = Plan::query()->find($planId);

        if (! $product || ! $product->is_active || ! $plan || (int) $plan->product_id !== (int) $product->id) {
            return $this->validationError('The selected product plan was not found or is not active.');
        }

        if ((string) $product->code === self::PRIMARY_PRODUCT_CODE) {
            return $this->validationError('Automotive should use the primary checkout flow.');
        }

        $paidPlan = $this->billingPlanCatalogService->findPaidPlanById($planId, (string) $product->code);
        if (! $paidPlan) {
            return $this->validationError('The selected product plan was not found or is not active.');
        }

        $tenantId = (string) ($this->tenantIdForUser($user) ?: strtolower(trim((string) $profile->subdomain)));
        if ($tenantId === '') {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Primary workspace is required before starting additional product billing.',
                'errors' => [
                    'portal' => ['Primary workspace is required before starting additional product billing.'],
                ],
            ];
        }

        $enablementRequest = ProductEnablementRequest::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $product->id)
            ->orderByDesc('id')
            ->first();

        $productSubscription = TenantProductSubscription::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $product->id)
            ->orderByDesc('id')
            ->first();

        if (($enablementRequest?->status ?? '') !== 'approved' && ! $productSubscription) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'This product must be approved before billing can start.',
                'errors' => [
                    'portal' => ['This product must be approved before billing can start.'],
                ],
            ];
        }

        if (
            $productSubscription
            && filled($productSubscription->gateway_subscription_id)
            && in_array((string) $productSubscription->status, SubscriptionStatuses::accessAllowedStatuses(), true)
        ) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'A live paid subscription already exists for this product.',
                'errors' => [
                    'portal' => ['A live paid subscription already exists for this product.'],
                ],
            ];
        }

        if (! $productSubscription) {
            $productSubscription = TenantProductSubscription::query()->create([
                'tenant_id' => $tenantId,
                'product_id' => $product->id,
                'status' => SubscriptionStatuses::PAST_DUE,
                'payment_failures_count' => 0,
            ]);
        }

        try {
            $session = $this->paymentGatewayManager
                ->driver('stripe')
                ->createRenewalSession([
                    'tenant_id' => $tenantId,
                    'tenant_product_subscription_id' => $productSubscription->id,
                    'plan_id' => $paidPlan->id,
                    'stripe_price_id' => $paidPlan->stripe_price_id,
                    'customer_email' => $user->email,
                    'success_url' => route('automotive.portal.checkout.success', ['product' => $product->slug]),
                    'cancel_url' => route('automotive.portal.checkout.cancel', ['product' => $product->slug]),
                    'product_scope' => (string) $product->code,
                    'plan_for_audit' => [
                        'id' => $paidPlan->id,
                        'name' => $paidPlan->name,
                        'price' => isset($paidPlan->price) ? (float) $paidPlan->price : null,
                        'currency' => $paidPlan->currency ?? null,
                        'billing_period' => $paidPlan->billing_period ?? null,
                        'stripe_price_id' => $paidPlan->stripe_price_id ?? null,
                    ],
                ]);
        } catch (\Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'status' => 500,
                'message' => 'Unable to start paid checkout right now.',
                'errors' => [
                    'portal' => ['Unable to start paid checkout right now.'],
                ],
            ];
        }

        if (! empty($session['success']) && ! empty($session['checkout_url']) && ! empty($session['session_id'])) {
            $productSubscription->fill([
                'plan_id' => (int) $paidPlan->id,
                'status' => SubscriptionStatuses::PAST_DUE,
                'gateway' => 'stripe',
                'gateway_checkout_session_id' => (string) $session['session_id'],
                'gateway_price_id' => (string) $paidPlan->stripe_price_id,
                'gateway_subscription_id' => null,
            ])->save();

            return [
                'ok' => true,
                'status' => 201,
                'checkout_url' => (string) $session['checkout_url'],
                'tenant_id' => $tenantId,
                'tenant_product_subscription_id' => (int) $productSubscription->id,
            ];
        }

        return [
            'ok' => false,
            'status' => 422,
            'message' => $session['message'] ?? 'Unable to start the paid checkout session.',
            'errors' => [
                'portal' => [$session['message'] ?? 'Unable to start the paid checkout session.'],
            ],
        ];
    }

    protected function validationError(string $message): array
    {
        return [
            'ok' => false,
            'status' => 422,
            'message' => $message,
            'errors' => [
                'plan_id' => [$message],
            ],
        ];
    }

    protected function tenantIdForUser(User $user): ?string
    {
        $connection = (string) (config('tenancy.database.central_connection') ?? config('database.default'));

        $tenantId = DB::connection($connection)
            ->table('tenant_users')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->value('tenant_id');

        return filled($tenantId) ? (string) $tenantId : null;
    }
}
