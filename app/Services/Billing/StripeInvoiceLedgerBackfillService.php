<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use Stripe\StripeClient;
use Throwable;

class StripeInvoiceLedgerBackfillService
{
    public function __construct(
        protected LocalBillingInvoiceService $localBillingInvoiceService
    ) {
    }

public function backfillForSubscription(Subscription $subscription, int $limit = 100): array
{
    try {
        $secret = $this->stripeSecret();

        if (empty($subscription->gateway_customer_id)) {
            return [
                'ok' => false,
                'message' => 'Subscription has no Stripe customer id.',
                'count' => 0,
            ];
        }

        $stripe = new StripeClient($secret);

        $invoices = $stripe->invoices->all([
            'customer' => (string) $subscription->gateway_customer_id,
            'limit' => max(1, min($limit, 100)),
        ]);

        $count = 0;

        foreach ($invoices->data as $invoice) {
            $this->localBillingInvoiceService->upsertFromStripeInvoice($invoice, $subscription);
            $count++;
        }

        return [
            'ok' => true,
            'message' => "Backfilled {$count} invoice(s) from Stripe.",
            'count' => $count,
        ];
    } catch (Throwable $e) {
        report($e);

        return [
            'ok' => false,
            'message' => 'Failed to backfill Stripe invoices: ' . $e->getMessage(),
            'count' => 0,
        ];
    }
}

protected function stripeSecret(): string
{
    $secret = trim((string) config('billing.gateways.stripe.secret'));

    if ($secret === '') {
        throw new \RuntimeException('Stripe secret key is not configured.');
    }

    return $secret;
}
}
