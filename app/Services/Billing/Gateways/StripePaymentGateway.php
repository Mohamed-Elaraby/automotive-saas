<?php

namespace App\Services\Billing\Gateways;

use App\Contracts\Billing\PaymentGatewayInterface;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Throwable;

class StripePaymentGateway implements PaymentGatewayInterface
{
    protected StripeClient $stripe;

    public function __construct()
    {
        $secret = (string) config('billing.gateways.stripe.secret');

        if ($secret === '') {
            throw new \RuntimeException('Stripe secret key is not configured.');
        }

        $this->stripe = new StripeClient($secret);
    }

    public function createRenewalSession(array $payload): array
    {
        $priceId = $payload['stripe_price_id'] ?? null;

        if (! $priceId) {
            return [
                'success' => false,
                'gateway' => 'stripe',
                'checkout_url' => null,
                'message' => 'The selected paid plan is not linked to a Stripe price yet.',
            ];
        }

        try {
            $session = $this->stripe->checkout->sessions->create([
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
        } catch (ApiErrorException $e) {
            Log::error('Stripe API error while creating renewal session', [
                'message' => $e->getMessage(),
                'tenant_id' => $payload['tenant_id'] ?? null,
                'plan_id' => $payload['plan_id'] ?? null,
                'stripe_price_id' => $priceId,
            ]);

            return [
                'success' => false,
                'gateway' => 'stripe',
                'checkout_url' => null,
                'message' => 'Stripe rejected the renewal request: ' . $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected Stripe billing error while creating renewal session', [
                'message' => $e->getMessage(),
                'tenant_id' => $payload['tenant_id'] ?? null,
                'plan_id' => $payload['plan_id'] ?? null,
                'stripe_price_id' => $priceId,
            ]);

            return [
                'success' => false,
                'gateway' => 'stripe',
                'checkout_url' => null,
                'message' => 'Unable to start Stripe checkout right now. Please verify billing configuration.',
            ];
        }
    }
}
