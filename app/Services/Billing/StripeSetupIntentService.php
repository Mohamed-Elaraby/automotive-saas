<?php

namespace App\Services\Billing;

use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Throwable;

class StripeSetupIntentService
{
    public function createForCustomer(string $customerId): array
    {
        $customerId = trim($customerId);

        if ($customerId === '') {
            return [
                'ok' => false,
                'message' => 'No Stripe customer is linked to this tenant yet.',
            ];
        }

        try {
            $stripe = new StripeClient($this->stripeSecret());

            $setupIntent = $stripe->setupIntents->create([
                'customer' => $customerId,
                'usage' => 'off_session',
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            return [
                'ok' => true,
                'client_secret' => $setupIntent->client_secret ?? null,
                'setup_intent_id' => $setupIntent->id ?? null,
            ];
        } catch (ApiErrorException $e) {
            return [
                'ok' => false,
                'message' => 'Stripe rejected the setup intent request: ' . $e->getMessage(),
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'message' => 'Unable to initialize the payment method form right now.',
            ];
        }
    }

    protected function stripeSecret(): string
    {
        $secret = trim((string) config('billing.gateways.stripe.secret'));

        if ($secret === '') {
            throw new RuntimeException('Stripe secret key is not configured.');
        }

        return $secret;
    }
}
