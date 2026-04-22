<?php

namespace App\Services\Billing\Gateways;

use App\Contracts\Billing\PaymentGatewayInterface;
use App\Services\Billing\StripePriceInspectorService;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Throwable;

class StripePaymentGateway implements PaymentGatewayInterface
{
    protected StripeClient $stripe;

    public function __construct(
        protected StripePriceInspectorService $stripePriceInspectorService
    ) {
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

        if (! empty($payload['plan_for_audit'])) {
            $audit = $this->stripePriceInspectorService->auditPlan((object) $payload['plan_for_audit']);

            if (! ($audit['checks']['is_aligned'] ?? false)) {
                $stripePriceExists = (bool) ($audit['stripe']['exists'] ?? false);
                $stripePriceActive = (bool) ($audit['stripe']['active'] ?? false);

                if ($stripePriceExists && ! $stripePriceActive) {
                    return [
                        'success' => false,
                        'gateway' => 'stripe',
                        'checkout_url' => null,
                        'message' => 'The selected paid plan is linked to an inactive Stripe price. Sync the plan prices in admin before checkout.',
                    ];
                }

                return [
                    'success' => false,
                    'gateway' => 'stripe',
                'checkout_url' => null,
                'message' => 'The selected plan pricing does not match the linked Stripe price. Please fix the Stripe price mapping before checkout.',
            ];
        }
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
            'success_url' => $this->appendSessionPlaceholder((string) $payload['success_url']),
            'cancel_url' => $payload['cancel_url'],
            'client_reference_id' => (string) ($payload['tenant_id'] ?? ''),
            'customer_email' => $payload['customer_email'] ?? null,
            'metadata' => [
                'tenant_id' => (string) ($payload['tenant_id'] ?? ''),
                'subscription_row_id' => (string) ($payload['subscription_row_id'] ?? ''),
                'tenant_product_subscription_id' => (string) ($payload['tenant_product_subscription_id'] ?? ''),
                'plan_id' => (string) ($payload['plan_id'] ?? ''),
                'product_scope' => (string) ($payload['product_scope'] ?? 'automotive'),
            ],
            'subscription_data' => [
                'metadata' => [
                    'tenant_id' => (string) ($payload['tenant_id'] ?? ''),
                    'subscription_row_id' => (string) ($payload['subscription_row_id'] ?? ''),
                    'tenant_product_subscription_id' => (string) ($payload['tenant_product_subscription_id'] ?? ''),
                    'plan_id' => (string) ($payload['plan_id'] ?? ''),
                    'product_scope' => (string) ($payload['product_scope'] ?? 'automotive'),
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

public function retrieveCheckoutSession(string $sessionId): array
{
    if ($sessionId === '') {
        return [
            'success' => false,
            'gateway' => 'stripe',
            'message' => 'Checkout session id is missing.',
        ];
    }

    try {
        $session = $this->stripe->checkout->sessions->retrieve($sessionId, []);

        return [
            'success' => true,
            'gateway' => 'stripe',
            'session' => method_exists($session, 'toArray') ? $session->toArray() : (array) $session,
        ];
    } catch (ApiErrorException $e) {
        Log::warning('Stripe API error while retrieving checkout session', [
            'message' => $e->getMessage(),
            'checkout_session_id' => $sessionId,
        ]);

        return [
            'success' => false,
            'gateway' => 'stripe',
            'message' => 'Stripe checkout session could not be retrieved.',
        ];
    } catch (Throwable $e) {
        Log::warning('Unexpected Stripe billing error while retrieving checkout session', [
            'message' => $e->getMessage(),
            'checkout_session_id' => $sessionId,
        ]);

        return [
            'success' => false,
            'gateway' => 'stripe',
            'message' => 'Unable to retrieve Stripe checkout session right now.',
        ];
    }
}

protected function appendSessionPlaceholder(string $successUrl): string
{
    $separator = str_contains($successUrl, '?') ? '&' : '?';

    return $successUrl . $separator . 'session_id={CHECKOUT_SESSION_ID}';
}
}
