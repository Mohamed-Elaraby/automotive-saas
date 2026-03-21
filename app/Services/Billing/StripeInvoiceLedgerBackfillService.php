<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use Stripe\Invoice;
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
        $secret = trim((string) config('services.stripe.secret'));

        if ($secret === '') {
            return [
                'ok' => false,
                'message' => 'Stripe secret key is not configured.',
                'count' => 0,
            ];
        }

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
            'limit' => $limit,
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
}
