<?php

namespace App\Services\Billing;

use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Throwable;

class StripeInvoiceHistoryService
{
    public function __construct(
        protected LocalBillingInvoiceService $localBillingInvoiceService
    ) {
    }

public function listCustomerInvoices(string $customerId, int $limit = 12): array
{
    $customerId = trim($customerId);

    if ($customerId === '') {
        return [
            'ok' => true,
            'invoices' => [],
            'message' => 'No Stripe customer is linked yet.',
        ];
    }

    $limit = max(1, min($limit, 50));

    $localResult = $this->localBillingInvoiceService->getCustomerInvoiceHistory($customerId, $limit);

    if (! empty($localResult['invoices'])) {
        return $localResult;
    }

    try {
        $stripe = new StripeClient($this->stripeSecret());

        $response = $stripe->invoices->all([
            'customer' => $customerId,
            'limit' => $limit,
        ]);

        $invoices = [];

        foreach (($response->data ?? []) as $invoice) {
            $currency = strtoupper((string) ($invoice->currency ?? 'USD'));
            $totalMinor = (int) ($invoice->total ?? 0);
            $amountPaidMinor = (int) ($invoice->amount_paid ?? 0);
            $amountDueMinor = (int) ($invoice->amount_due ?? 0);

            $normalizedInvoice = [
                'id' => (string) ($invoice->id ?? ''),
                'number' => (string) ($invoice->number ?? ($invoice->id ?? '')),
                'status' => (string) ($invoice->status ?? 'unknown'),
                'billing_reason' => (string) ($invoice->billing_reason ?? ''),
                'currency' => $currency,
                'total_minor' => $totalMinor,
                'total_decimal' => $this->minorToDecimal($totalMinor, $currency),
                'amount_paid_minor' => $amountPaidMinor,
                'amount_paid_decimal' => $this->minorToDecimal($amountPaidMinor, $currency),
                'amount_due_minor' => $amountDueMinor,
                'amount_due_decimal' => $this->minorToDecimal($amountDueMinor, $currency),
                'hosted_invoice_url' => $invoice->hosted_invoice_url ?? null,
                'invoice_pdf' => $invoice->invoice_pdf ?? null,
                'created_at' => ! empty($invoice->created) ? (int) $invoice->created : null,
                'subscription_id' => (string) ($invoice->subscription ?? ''),
            ];

            $invoices[] = $normalizedInvoice;

            try {
                $this->localBillingInvoiceService->upsertFromStripeInvoice($invoice);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return [
            'ok' => true,
            'invoices' => $invoices,
            'message' => null,
        ];
    } catch (ApiErrorException $e) {
        return [
            'ok' => false,
            'invoices' => [],
            'message' => 'Stripe rejected the invoice history request: ' . $e->getMessage(),
        ];
    } catch (Throwable $e) {
        report($e);

        return [
            'ok' => false,
            'invoices' => [],
            'message' => 'Unable to load invoice history right now.',
        ];
    }
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
        'BIF',
        'CLP',
        'DJF',
        'GNF',
        'JPY',
        'KMF',
        'KRW',
        'MGA',
        'PYG',
        'RWF',
        'UGX',
        'VND',
        'VUV',
        'XAF',
        'XOF',
        'XPF',
    ], true);
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
