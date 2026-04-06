<?php

namespace App\Services\Billing;

use App\Models\BillingInvoice;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LocalBillingInvoiceService
{
    public function upsertFromStripeInvoice(object|array $invoice, ?Subscription $subscription = null): BillingInvoice
    {
        $invoice = is_array($invoice) ? (object) $invoice : $invoice;

        $gatewayInvoiceId = (string) ($invoice->id ?? '');

        if ($gatewayInvoiceId === '') {
            throw new \InvalidArgumentException('Stripe invoice id is required for ledger upsert.');
        }

$subscription ??= $this->resolveSubscriptionFromStripeInvoice($invoice);

$totalMinor = (int) ($invoice->total ?? 0);
$amountPaidMinor = (int) ($invoice->amount_paid ?? 0);
$amountDueMinor = (int) ($invoice->amount_due ?? 0);

$currency = strtoupper((string) ($invoice->currency ?? 'USD'));

return BillingInvoice::query()->updateOrCreate(
    [
        'gateway_invoice_id' => $gatewayInvoiceId,
    ],
    [
        'subscription_id' => $subscription?->id,
                'tenant_id' => $subscription?->tenant_id,
                'gateway' => 'stripe',
                'gateway_customer_id' => (string) ($invoice->customer ?? ''),
                'gateway_subscription_id' => (string) ($invoice->subscription ?? ''),
                'invoice_number' => $invoice->number ?? null,
                'status' => strtolower((string) ($invoice->status ?? 'draft')),
                'billing_reason' => $invoice->billing_reason ?? null,
                'currency' => $currency,
                'total_minor' => $totalMinor,
                'total_decimal' => $this->toDecimal($totalMinor),
                'amount_paid_minor' => $amountPaidMinor,
                'amount_paid_decimal' => $this->toDecimal($amountPaidMinor),
                'amount_due_minor' => $amountDueMinor,
                'amount_due_decimal' => $this->toDecimal($amountDueMinor),
                'hosted_invoice_url' => $invoice->hosted_invoice_url ?? null,
                'invoice_pdf' => $invoice->invoice_pdf ?? null,
                'issued_at' => $this->toCarbonFromUnix($invoice->created ?? null),
                'paid_at' => $this->extractPaidAt($invoice),
                'raw_payload' => json_decode(json_encode($invoice), true),
            ]
        );
    }

    public function mapLedgerRowsToHistoryPayload(Collection $rows): array
{
    return [
        'ok' => true,
        'invoices' => $rows->map(function (BillingInvoice $invoice) {
            return [
                'id' => $invoice->gateway_invoice_id,
                'number' => $invoice->invoice_number,
                'status' => $invoice->status,
                'currency' => $invoice->currency,
                'total_decimal' => (float) $invoice->total_decimal,
                'amount_paid_decimal' => (float) $invoice->amount_paid_decimal,
                'amount_due_decimal' => (float) $invoice->amount_due_decimal,
                'hosted_invoice_url' => $invoice->hosted_invoice_url,
                'invoice_pdf' => $invoice->invoice_pdf,
                'created_at' => $invoice->issued_at?->timestamp,
                'subscription_id' => $invoice->gateway_subscription_id,
                'tenant_id' => $invoice->tenant_id,
            ];
            })->values()->all(),
        'message' => null,
    ];
}

    public function getCustomerInvoiceHistory(string $customerId, int $limit = 20): array
{
    $rows = BillingInvoice::query()
        ->where('gateway', 'stripe')
        ->where('gateway_customer_id', $customerId)
        ->orderByDesc('issued_at')
        ->limit($limit)
        ->get();

    return $this->mapLedgerRowsToHistoryPayload($rows);
}

    public function getSubscriptionInvoiceHistory(string $gatewaySubscriptionId, int $limit = 20): array
{
    $rows = BillingInvoice::query()
        ->where('gateway', 'stripe')
        ->where('gateway_subscription_id', $gatewaySubscriptionId)
        ->orderByDesc('issued_at')
        ->limit($limit)
        ->get();

    return $this->mapLedgerRowsToHistoryPayload($rows);
}

    protected function resolveSubscriptionFromStripeInvoice(object $invoice): ?Subscription
{
    $gatewaySubscriptionId = (string) ($invoice->subscription ?? '');

    if ($gatewaySubscriptionId !== '') {
        $subscription = Subscription::query()
            ->where('gateway', 'stripe')
            ->where('gateway_subscription_id', $gatewaySubscriptionId)
            ->first();

        if ($subscription) {
            return $subscription;
        }
    }

    $gatewayCustomerId = (string) ($invoice->customer ?? '');

    if ($gatewayCustomerId !== '') {
        return Subscription::query()
            ->where('gateway', 'stripe')
            ->where('gateway_customer_id', $gatewayCustomerId)
            ->latest('id')
            ->first();
    }

    return null;
}

    protected function toDecimal(int $minor): float
{
    return round($minor / 100, 2);
}

    protected function toCarbonFromUnix(?int $timestamp): ?Carbon
{
    if (empty($timestamp)) {
        return null;
    }

    return Carbon::createFromTimestamp($timestamp);
}

    protected function extractPaidAt(object $invoice): ?Carbon
{
    $statusTransitions = $invoice->status_transitions ?? null;
    $paidAt = is_object($statusTransitions) ? ($statusTransitions->paid_at ?? null) : null;

    if (! empty($paidAt)) {
        return Carbon::createFromTimestamp((int) $paidAt);
    }

    return null;
}
}
