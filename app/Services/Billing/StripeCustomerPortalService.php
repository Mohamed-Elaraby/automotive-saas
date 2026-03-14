<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\BillingPortal\Session;
use Stripe\StripeClient;
use Throwable;

class StripeCustomerPortalService
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

    public function createSession(string $customerId, ?string $returnUrl = null): array
    {
        if ($customerId === '') {
            return [
                'success' => false,
                'url' => null,
                'message' => 'No Stripe customer is linked to this subscription yet.',
            ];
        }

        try {
            $session = $this->stripe->billingPortal->sessions->create([
                'customer' => $customerId,
                'return_url' => $returnUrl ?: config('billing.portal_return_url'),
            ]);

            return [
                'success' => true,
                'url' => $session->url,
                'message' => 'Stripe billing portal session created successfully.',
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe billing portal API error', [
                'message' => $e->getMessage(),
                'customer_id' => $customerId,
            ]);

            return [
                'success' => false,
                'url' => null,
                'message' => 'Stripe billing portal rejected the request: ' . $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected Stripe billing portal error', [
                'message' => $e->getMessage(),
                'customer_id' => $customerId,
            ]);

            return [
                'success' => false,
                'url' => null,
                'message' => 'Unable to open the billing portal right now.',
            ];
        }
    }
}
