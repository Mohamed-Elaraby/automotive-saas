<?php

namespace App\Services\Billing\Gateways;

use App\Contracts\Billing\PaymentGatewayInterface;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

class StripePaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        protected ?StripeClient $stripe = null
    ) {
        $secret = (string) config('billing.gateways.stripe.secret');

        $this->stripe = $this->stripe ?: new StripeClient($secret);
    }

public function createRenewalSession(array $payload): array
{
    $priceId = $payload['stripe_price_id'] ?? null;

    if (! $priceId) {
        return [
            'success' => false,
            'gateway' => 'stripe',
            'checkout_url' => null,
            'message' => 'The current plan is not linked to a Stripe price yet.',
        ];
    }

    $session = Session::create([
        'mode' => 'subscription',
        'line_items' => [
            [
                'price' => $priceId,
                'quantity' => 1,
            ],
        ],
        'success_url' => $payload['success_url'] . '?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $payload['cancel_url'],
        'client_reference_id' => (string) ($payload['tenant_id'] ?? ''),
        'customer_email' => $payload['customer_email'] ?? null,
        'metadata' => [
            'tenant_id' => (string) ($payload['tenant_id'] ?? ''),
            'subscription_row_id' => (string) ($payload['subscription_row_id'] ?? ''),
            'plan_id' => (string) ($payload['plan_id'] ?? ''),
            'product_scope' => 'automotive',
        ],
        'subscription_data' => [
            'metadata' => [
                'tenant_id' => (string) ($payload['tenant_id'] ?? ''),
                'subscription_row_id' => (string) ($payload['subscription_row_id'] ?? ''),
                'plan_id' => (string) ($payload['plan_id'] ?? ''),
                'product_scope' => 'automotive',
            ],
        ],
    ]);

    return [
        'success' => true,
        'gateway' => 'stripe',
        'checkout_url' => $session->url,
        'session_id' => $session->id,
        'message' => 'Stripe checkout session created successfully.',
    ];
}
}
