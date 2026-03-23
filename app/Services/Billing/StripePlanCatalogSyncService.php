<?php

namespace App\Services\Billing;

use App\Models\Plan;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;
use Throwable;

class StripePlanCatalogSyncService
{
    protected ?StripeClient $stripe = null;

    protected function stripeSecret(): string
    {
        return trim((string) config('billing.gateways.stripe.secret'));
    }

    public function isConfigured(): bool
    {
        return $this->stripeSecret() !== '';
    }

    public function syncPlan(Plan $plan): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => true,
                'skipped' => true,
                'message' => 'Stripe sync skipped because Stripe secret key is not configured.',
                'stripe_product_id' => $plan->stripe_product_id,
                'stripe_price_id' => $plan->stripe_price_id,
            ];
        }

        if ($this->isLocalOnlyTrial($plan)) {
            $this->archivePlanResources($plan, true);

            return [
                'ok' => true,
                'message' => 'Trial plan kept local-only and excluded from Stripe catalog.',
                'stripe_product_id' => null,
                'stripe_price_id' => null,
            ];
        }

        try {
            $product = $this->ensureProduct($plan);
            $price = $this->ensurePrice($plan, $product->id);

            $this->client()->products->update($product->id, [
                'name' => $plan->name,
                'description' => $plan->description ?: '',
                'active' => (bool) $plan->is_active,
                'default_price' => $price->id,
                'metadata' => $this->buildMetadata($plan),
            ]);

            $plan->forceFill([
                'stripe_product_id' => $product->id,
                'stripe_price_id' => $price->id,
            ])->save();

            return [
                'ok' => true,
                'message' => 'Plan synced successfully with Stripe.',
                'stripe_product_id' => $product->id,
                'stripe_price_id' => $price->id,
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'message' => 'Unable to sync the plan with Stripe right now: ' . $e->getMessage(),
            ];
        }
    }

    public function archivePlanResources(Plan $plan, bool $clearLocalIds = false): array
    {
        if (! $this->isConfigured()) {
            if ($clearLocalIds) {
                $plan->forceFill([
                    'stripe_product_id' => null,
                    'stripe_price_id' => null,
                ])->save();
            }

            return [
                'ok' => true,
                'skipped' => true,
                'message' => 'Stripe archive skipped because Stripe secret key is not configured.',
            ];
        }

        try {
            if ($plan->stripe_price_id) {
                try {
                    $this->client()->prices->update($plan->stripe_price_id, [
                        'active' => false,
                    ]);
                } catch (InvalidRequestException) {
                    // Ignore if the price no longer exists remotely
                }
            }

            if ($plan->stripe_product_id) {
                try {
                    $this->client()->products->update($plan->stripe_product_id, [
                        'active' => false,
                    ]);
                } catch (InvalidRequestException) {
                    // Ignore if the product no longer exists remotely
                }
            }

            if ($clearLocalIds) {
                $plan->forceFill([
                    'stripe_product_id' => null,
                    'stripe_price_id' => null,
                ])->save();
            }

            return [
                'ok' => true,
                'message' => 'Plan resources archived successfully on Stripe.',
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'message' => 'Unable to archive Stripe resources for this plan: ' . $e->getMessage(),
            ];
        }
    }

    protected function client(): StripeClient
    {
        if ($this->stripe instanceof StripeClient) {
            return $this->stripe;
        }

        $secret = $this->stripeSecret();

        if ($secret === '') {
            throw new \RuntimeException('Stripe secret key is not configured.');
        }

        $this->stripe = new StripeClient($secret);

        return $this->stripe;
    }

    protected function ensureProduct(Plan $plan): object
    {
        if ($plan->stripe_product_id) {
            try {
                return $this->client()->products->update($plan->stripe_product_id, [
                    'name' => $plan->name,
                    'description' => $plan->description ?: '',
                    'active' => (bool) $plan->is_active,
                    'metadata' => $this->buildMetadata($plan),
                ]);
            } catch (InvalidRequestException) {
                // Fall through to create a fresh product
            }
        }

        return $this->client()->products->create([
            'name' => $plan->name,
            'description' => $plan->description ?: '',
            'active' => (bool) $plan->is_active,
            'metadata' => $this->buildMetadata($plan),
        ]);
    }

    protected function ensurePrice(Plan $plan, string $stripeProductId): object
    {
        if ($plan->stripe_price_id) {
            try {
                $existingPrice = $this->client()->prices->retrieve($plan->stripe_price_id, []);

                if ($this->priceMatchesPlan($existingPrice, $plan, $stripeProductId)) {
                    $this->client()->prices->update($existingPrice->id, [
                        'active' => (bool) $plan->is_active,
                        'nickname' => $this->buildPriceNickname($plan),
                        'metadata' => $this->buildMetadata($plan),
                    ]);

                    return $this->client()->prices->retrieve($existingPrice->id, []);
                }

                $newPrice = $this->createPrice($plan, $stripeProductId);

                $this->client()->prices->update($existingPrice->id, [
                    'active' => false,
                ]);

                return $newPrice;
            } catch (InvalidRequestException) {
                // Fall through to create a fresh price
            }
        }

        return $this->createPrice($plan, $stripeProductId);
    }

    protected function createPrice(Plan $plan, string $stripeProductId): object
    {
        $payload = [
            'product' => $stripeProductId,
            'currency' => strtolower((string) $plan->currency),
            'unit_amount' => $this->toMinorAmount($plan->price, $plan->currency),
            'active' => (bool) $plan->is_active,
            'nickname' => $this->buildPriceNickname($plan),
            'metadata' => $this->buildMetadata($plan),
        ];

        if ($plan->billing_period === 'monthly') {
            $payload['recurring'] = [
                'interval' => 'month',
            ];
        } elseif ($plan->billing_period === 'yearly') {
            $payload['recurring'] = [
                'interval' => 'year',
            ];
        }

        return $this->client()->prices->create($payload);
    }

    protected function priceMatchesPlan(object $price, Plan $plan, string $stripeProductId): bool
    {
        $expectedUnitAmount = $this->toMinorAmount($plan->price, $plan->currency);
        $expectedCurrency = strtolower((string) $plan->currency);

        if (($price->product ?? null) !== $stripeProductId) {
            return false;
        }

        if (($price->currency ?? null) !== $expectedCurrency) {
            return false;
        }

        if ((int) ($price->unit_amount ?? -1) !== $expectedUnitAmount) {
            return false;
        }

        $priceInterval = $price->recurring->interval ?? null;

        return match ($plan->billing_period) {
        'monthly' => $priceInterval === 'month',
            'yearly' => $priceInterval === 'year',
            'one_time' => $priceInterval === null,
            default => false,
        };
    }

    protected function isLocalOnlyTrial(Plan $plan): bool
    {
        return $plan->billing_period === 'trial';
    }

    protected function buildMetadata(Plan $plan): array
    {
        return [
            'local_plan_id' => (string) $plan->id,
            'slug' => (string) $plan->slug,
            'billing_period' => (string) $plan->billing_period,
            'currency' => strtoupper((string) $plan->currency),
            'is_active' => $plan->is_active ? '1' : '0',
        ];
    }

    protected function buildPriceNickname(Plan $plan): string
    {
        return sprintf(
            '%s (%s)',
            $plan->name,
            ucfirst(str_replace('_', ' ', $plan->billing_period))
        );
    }

    protected function toMinorAmount(float|int|string $amount, string $currency): int
    {
        $currency = strtoupper($currency);

        $zeroDecimalCurrencies = [
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW',
            'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF',
            'XOF', 'XPF',
        ];

        if (in_array($currency, $zeroDecimalCurrencies, true)) {
            return (int) round((float) $amount);
        }

return (int) round(((float) $amount) * 100);
}
}
