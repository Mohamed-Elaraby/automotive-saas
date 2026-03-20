<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Support\Billing\SubscriptionStatuses;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Throwable;

class StripeSubscriptionPlanPreviewService
{
    public function previewPlanChange(Subscription $subscription, Plan $targetPlan): array
    {
        if ($subscription->gateway !== 'stripe') {
            return [
                'ok' => false,
                'message' => 'This subscription is not linked to the Stripe gateway.',
            ];
        }

        if (! $subscription->gateway_subscription_id) {
            return [
                'ok' => false,
                'message' => 'No Stripe subscription ID is linked to this subscription.',
            ];
        }

        if (! $this->isEligibleForPlanChange($subscription)) {
            return [
                'ok' => false,
                'message' => 'This subscription is not eligible for in-place plan preview right now.',
            ];
        }

        if (! $targetPlan->is_active) {
            return [
                'ok' => false,
                'message' => 'The selected plan is inactive.',
            ];
        }

        if ($targetPlan->billing_period === 'trial') {
            return [
                'ok' => false,
                'message' => 'Trial plans cannot replace a live Stripe subscription.',
            ];
        }

        if (! $targetPlan->stripe_price_id) {
            return [
                'ok' => false,
                'message' => 'The selected plan is not linked to a Stripe price yet.',
            ];
        }

        $stripe = new StripeClient($this->stripeSecret());

        try {
            $stripeSubscription = $stripe->subscriptions->retrieve(
                $subscription->gateway_subscription_id,
                []
            );

            $items = $stripeSubscription->items->data ?? [];

            if (count($items) !== 1) {
                return [
                    'ok' => false,
                    'message' => 'This Stripe subscription does not have the expected single subscription item structure.',
                ];
            }

            $itemId = $items[0]->id ?? null;

            if (! $itemId) {
                return [
                    'ok' => false,
                    'message' => 'Unable to locate the current Stripe subscription item.',
                ];
            }

            $prorationDate = time();

            $previewInvoice = $stripe->invoices->createPreview([
                'customer' => $stripeSubscription->customer ?? $subscription->gateway_customer_id,
                'subscription' => $subscription->gateway_subscription_id,
                'subscription_details' => [
                    'proration_date' => $prorationDate,
                    'items' => [
                        [
                            'id' => $itemId,
                            'price' => $targetPlan->stripe_price_id,
                        ],
                    ],
                ],
            ]);

            $currency = strtoupper((string) ($previewInvoice->currency ?? $targetPlan->currency ?? 'USD'));
            $lines = $previewInvoice->lines->data ?? [];

            $normalizedLines = [];
            $prorationLines = [];
            $prorationTotalMinor = 0;

            foreach ($lines as $line) {
                $normalized = $this->normalizeLine($line, $currency);
                $normalizedLines[] = $normalized;

                if ($normalized['is_proration']) {
                    $prorationLines[] = $normalized;
                    $prorationTotalMinor += (int) ($normalized['amount_minor'] ?? 0);
                }
            }

            return [
                'ok' => true,
                'message' => 'Stripe preview generated successfully.',
                'preview' => [
                    'currency' => $currency,
                    'proration_date' => $prorationDate,
                    'subtotal_minor' => (int) ($previewInvoice->subtotal ?? 0),
                    'subtotal_decimal' => $this->minorToDecimal((int) ($previewInvoice->subtotal ?? 0), $currency),
                    'total_minor' => (int) ($previewInvoice->total ?? 0),
                    'total_decimal' => $this->minorToDecimal((int) ($previewInvoice->total ?? 0), $currency),
                    'amount_due_minor' => (int) ($previewInvoice->amount_due ?? 0),
                    'amount_due_decimal' => $this->minorToDecimal((int) ($previewInvoice->amount_due ?? 0), $currency),
                    'proration_total_minor' => $prorationTotalMinor,
                    'proration_total_decimal' => $this->minorToDecimal($prorationTotalMinor, $currency),
                    'proration_lines' => $prorationLines,
                    'all_lines' => $normalizedLines,
                ],
            ];
        } catch (ApiErrorException $e) {
            return [
                'ok' => false,
                'message' => 'Stripe rejected the preview request: ' . $e->getMessage(),
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'message' => 'Unable to generate the Stripe preview right now.',
            ];
        }
    }

    protected function normalizeLine(object $line, string $currency): array
    {
        $amountMinor = (int) ($line->amount ?? 0);

        $isProration = false;

        if (isset($line->parent->subscription_item_details->proration)) {
            $isProration = (bool) $line->parent->subscription_item_details->proration;
        } elseif (isset($line->proration)) {
            $isProration = (bool) $line->proration;
        }

        return [
            'description' => (string) ($line->description ?? 'Stripe invoice line'),
            'amount_minor' => $amountMinor,
            'amount_decimal' => $this->minorToDecimal($amountMinor, $currency),
            'currency' => $currency,
            'is_proration' => $isProration,
            'period_start' => ! empty($line->period->start) ? (int) $line->period->start : null,
            'period_end' => ! empty($line->period->end) ? (int) $line->period->end : null,
        ];
    }

    protected function minorToDecimal(int $amountMinor, string $currency): float
    {
        if ($this->isZeroDecimalCurrency($currency)) {
            return (float) $amountMinor;
        }

        return round($amountMinor / 100, 2);
    }

    protected function isZeroDecimalCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), [
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW',
            'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF',
            'XOF', 'XPF',
        ], true);
    }

    protected function isEligibleForPlanChange(Subscription $subscription): bool
    {
        $status = (string) $subscription->status;

        if ($status === SubscriptionStatuses::ACTIVE) {
            return true;
        }

        if ($status === SubscriptionStatuses::CANCELLED) {
            return $subscription->ends_at !== null && $subscription->ends_at->isFuture();
        }

        return false;
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
