<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Throwable;

class StripePaymentMethodManagementService
{
    public function setDefaultPaymentMethod(Subscription $subscription, string $paymentMethodId): array
    {
        $paymentMethodId = trim($paymentMethodId);

        if ($subscription->gateway !== 'stripe') {
            return [
                'ok' => false,
                'message' => 'This subscription is not linked to the Stripe gateway.',
            ];
        }

        if (! $subscription->gateway_customer_id) {
            return [
                'ok' => false,
                'message' => 'No Stripe customer is linked to this subscription.',
            ];
        }

        if (! $subscription->gateway_subscription_id) {
            return [
                'ok' => false,
                'message' => 'No Stripe subscription is linked to this subscription.',
            ];
        }

        if ($paymentMethodId === '') {
            return [
                'ok' => false,
                'message' => 'No payment method was provided.',
            ];
        }

        try {
            $stripe = new StripeClient($this->stripeSecret());

            $stripe->customers->update(
                (string) $subscription->gateway_customer_id,
                [
                    'invoice_settings' => [
                        'default_payment_method' => $paymentMethodId,
                    ],
                ]
            );

            $updatedSubscription = $stripe->subscriptions->update(
                (string) $subscription->gateway_subscription_id,
                [
                    'default_payment_method' => $paymentMethodId,
                ]
            );

            return [
                'ok' => true,
                'message' => 'Default payment method updated successfully.',
                'stripe_subscription_id' => $updatedSubscription->id ?? null,
                'default_payment_method' => $paymentMethodId,
            ];
        } catch (ApiErrorException $e) {
            return [
                'ok' => false,
                'message' => 'Stripe rejected the payment method update: ' . $e->getMessage(),
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'message' => 'Unable to update the default payment method right now.',
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
