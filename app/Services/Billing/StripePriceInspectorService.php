<?php

namespace App\Services\Billing;

use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Throwable;

class StripePriceInspectorService
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

    public function inspectPrice(?string $priceId): array
    {
        if (! $priceId) {
            return [
                'success' => false,
                'exists' => false,
                'price_id' => null,
                'unit_amount' => null,
                'unit_amount_decimal' => null,
                'currency' => null,
                'interval' => null,
                'interval_count' => null,
                'product_id' => null,
                'product_name' => null,
                'product_description' => null,
                'message' => 'Stripe price id is missing.',
            ];
        }

        try {
            $price = $this->stripe->prices->retrieve($priceId, [
                'expand' => ['product'],
            ]);

            $product = $price->product ?? null;

            return [
                'success' => true,
                'exists' => true,
                'price_id' => $price->id,
                'active' => (bool) ($price->active ?? false),
                'unit_amount' => $price->unit_amount,
                'unit_amount_decimal' => $price->unit_amount_decimal !== null
                    ? (float) ($price->unit_amount_decimal / 100)
                    : ($price->unit_amount !== null ? (float) ($price->unit_amount / 100) : null),
                'currency' => strtoupper((string) ($price->currency ?? '')),
                'interval' => $price->recurring->interval ?? null,
                'interval_count' => $price->recurring->interval_count ?? null,
                'product_id' => is_object($product) ? ($product->id ?? null) : null,
                'product_name' => is_object($product) ? ($product->name ?? null) : null,
                'product_description' => is_object($product) ? ($product->description ?? null) : null,
                'message' => 'Stripe price loaded successfully.',
            ];
        } catch (ApiErrorException $e) {
            return [
                'success' => false,
                'exists' => false,
                'price_id' => $priceId,
                'active' => null,
                'unit_amount' => null,
                'unit_amount_decimal' => null,
                'currency' => null,
                'interval' => null,
                'interval_count' => null,
                'product_id' => null,
                'product_name' => null,
                'product_description' => null,
                'message' => 'Stripe API error: ' . $e->getMessage(),
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'exists' => false,
                'price_id' => $priceId,
                'active' => null,
                'unit_amount' => null,
                'unit_amount_decimal' => null,
                'currency' => null,
                'interval' => null,
                'interval_count' => null,
                'product_id' => null,
                'product_name' => null,
                'product_description' => null,
                'message' => 'Unable to inspect Stripe price: ' . $e->getMessage(),
            ];
        }
    }

    public function auditPlan(object $plan): array
    {
        $stripe = $this->inspectPrice($plan->stripe_price_id ?? null);

        $localPrice = isset($plan->price) ? (float) $plan->price : null;
        $localCurrency = strtoupper((string) ($plan->currency ?? ''));
        $localInterval = $this->mapBillingPeriodToStripeInterval((string) ($plan->billing_period ?? ''));

        $amountMatches = $stripe['success']
            && $localPrice !== null
            && $stripe['unit_amount_decimal'] !== null
            && ((float) $stripe['unit_amount_decimal'] === (float) $localPrice);

        $currencyMatches = $stripe['success']
            && $localCurrency !== ''
            && strtoupper((string) $stripe['currency']) === $localCurrency;

        $intervalMatches = $stripe['success']
            && $localInterval !== null
            && $stripe['interval'] === $localInterval;

        $activeMatches = $stripe['success']
            && ($stripe['active'] ?? false) === true;

        $isAligned = $stripe['success'] && $activeMatches && $amountMatches && $currencyMatches && $intervalMatches;

        return [
            'local' => [
                'price' => $localPrice,
                'currency' => $localCurrency,
                'billing_period' => $plan->billing_period ?? null,
                'interval' => $localInterval,
            ],
            'stripe' => $stripe,
            'checks' => [
                'active_matches' => $activeMatches,
                'amount_matches' => $amountMatches,
                'currency_matches' => $currencyMatches,
                'interval_matches' => $intervalMatches,
                'is_aligned' => $isAligned,
            ],
            'message' => $isAligned
                ? 'Local plan pricing is aligned with Stripe.'
                : (($stripe['success'] && ($stripe['active'] ?? false) === false)
                    ? 'The linked Stripe price exists but is inactive.'
                    : 'Local plan pricing does not match the linked Stripe price.'),
        ];
    }

    protected function mapBillingPeriodToStripeInterval(string $billingPeriod): ?string
    {
        return match (strtolower($billingPeriod)) {
        'monthly' => 'month',
            'yearly' => 'year',
            default => null,
        };
    }
}
