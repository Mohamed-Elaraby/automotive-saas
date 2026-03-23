<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingInvoice;
use App\Models\Currency;
use App\Support\Billing\SubscriptionStatuses;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingReportController extends Controller
{
    public function index(Request $request): View
    {
        $connection = $this->centralConnection();

        $filters = $this->resolveFilters($request);

        $baseSubscriptions = DB::connection($connection)->table('subscriptions');

        $totalSubscriptions = (clone $baseSubscriptions)->count();

        $activeSubscriptions = (clone $baseSubscriptions)
            ->where('status', SubscriptionStatuses::ACTIVE)
            ->count();

        $trialingSubscriptions = (clone $baseSubscriptions)
            ->where('status', SubscriptionStatuses::TRIALING)
            ->count();

        $pastDueSubscriptions = (clone $baseSubscriptions)
            ->where('status', SubscriptionStatuses::PAST_DUE)
            ->count();

        $suspendedSubscriptions = (clone $baseSubscriptions)
            ->where('status', SubscriptionStatuses::SUSPENDED)
            ->count();

        $canceledSubscriptions = (clone $baseSubscriptions)
            ->where('status', SubscriptionStatuses::CANCELLED)
            ->count();

        $expiredSubscriptions = (clone $baseSubscriptions)
            ->where('status', SubscriptionStatuses::EXPIRED)
            ->count();

        $activePaidSubscriptions = DB::connection($connection)
            ->table('subscriptions')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('subscriptions.status', SubscriptionStatuses::ACTIVE)
            ->where('plans.billing_period', '!=', 'trial')
            ->count();

        $estimatedMrrByCurrency = DB::connection($connection)
            ->table('subscriptions')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->leftJoin('currencies', 'currencies.code', '=', 'plans.currency')
            ->select([
                'plans.currency',
                DB::raw('SUM(plans.price) as estimated_mrr'),
                'currencies.name as currency_name',
                'currencies.symbol as currency_symbol',
                'currencies.native_symbol as currency_native_symbol',
                'currencies.decimal_places as currency_decimal_places',
            ])
            ->where('subscriptions.status', SubscriptionStatuses::ACTIVE)
            ->where('plans.billing_period', 'monthly')
            ->where('plans.billing_period', '!=', 'trial')
            ->groupBy(
                'plans.currency',
                'currencies.name',
                'currencies.symbol',
                'currencies.native_symbol',
                'currencies.decimal_places'
            )
            ->orderBy('plans.currency')
            ->get()
            ->map(function ($row) {
                return [
                    'currency' => strtoupper((string) ($row->currency ?? '')),
                    'currency_name' => $row->currency_name ?: strtoupper((string) ($row->currency ?? '')),
                    'currency_symbol' => $row->currency_symbol,
                    'currency_native_symbol' => $row->currency_native_symbol,
                    'decimal_places' => (int) ($row->currency_decimal_places ?? 2),
                    'estimated_mrr' => round((float) ($row->estimated_mrr ?? 0), 2),
                ];
            })
            ->values();

        $activePlanDistribution = DB::connection($connection)
            ->table('subscriptions')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->leftJoin('currencies', 'currencies.code', '=', 'plans.currency')
            ->select([
                'plans.id as plan_id',
                'plans.name as plan_name',
                'plans.slug as plan_slug',
                'plans.billing_period as billing_period',
                'plans.price as price',
                'plans.currency as currency',
                'currencies.name as currency_name',
                'currencies.symbol as currency_symbol',
                'currencies.native_symbol as currency_native_symbol',
                DB::raw('COUNT(subscriptions.id) as active_subscriptions_count'),
                DB::raw('COUNT(DISTINCT subscriptions.tenant_id) as active_tenants_count'),
                DB::raw('SUM(CASE WHEN plans.billing_period = "monthly" THEN plans.price ELSE 0 END) as estimated_monthly_revenue'),
            ])
            ->where('subscriptions.status', SubscriptionStatuses::ACTIVE)
            ->where('plans.billing_period', '!=', 'trial')
            ->groupBy(
                'plans.id',
                'plans.name',
                'plans.slug',
                'plans.billing_period',
                'plans.price',
                'plans.currency',
                'currencies.name',
                'currencies.symbol',
                'currencies.native_symbol'
            )
            ->orderByDesc('active_subscriptions_count')
            ->orderBy('plans.sort_order')
            ->get();

        $gatewayBreakdown = DB::connection($connection)
            ->table('subscriptions')
            ->select([
                'gateway',
                DB::raw('COUNT(*) as subscriptions_count'),
            ])
            ->whereNotNull('gateway')
            ->where('gateway', '!=', '')
            ->groupBy('gateway')
            ->orderByDesc('subscriptions_count')
            ->get();

        $invoiceReport = $this->buildInvoiceReport($filters);

        return view('admin.reports.billing', [
            'summary' => [
                'total_subscriptions' => (int) $totalSubscriptions,
                'active_subscriptions' => (int) $activeSubscriptions,
                'active_paid_subscriptions' => (int) $activePaidSubscriptions,
                'trialing_subscriptions' => (int) $trialingSubscriptions,
                'past_due_subscriptions' => (int) $pastDueSubscriptions,
                'suspended_subscriptions' => (int) $suspendedSubscriptions,
                'canceled_subscriptions' => (int) $canceledSubscriptions,
                'expired_subscriptions' => (int) $expiredSubscriptions,
                'estimated_mrr_by_currency' => $estimatedMrrByCurrency,
            ],
            'activePlanDistribution' => $activePlanDistribution,
            'gatewayBreakdown' => $gatewayBreakdown,
            'recentInvoices' => $invoiceReport['recentInvoices'],
            'monthlyInvoiceTrend' => $invoiceReport['monthlyInvoiceTrend'],
            'filters' => $filters,
            'filterOptions' => [
                'statuses' => $invoiceReport['statuses'],
                'gateways' => $invoiceReport['gateways'],
                'currencies' => $invoiceReport['currencies'],
                'months' => $invoiceReport['months'],
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = $this->resolveFilters($request);
        $currencyMap = $this->currencyMap();

        $query = BillingInvoice::query()
            ->orderByDesc('issued_at')
            ->orderByDesc('id');

        $this->applyInvoiceFilters($query, $filters);

        $filename = $this->buildExportFilename($filters);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->streamDownload(function () use ($query, $currencyMap) {
            $handle = fopen('php://output', 'w');

            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'invoice_id',
                'invoice_number',
                'tenant_id',
                'subscription_id',
                'gateway',
                'gateway_customer_id',
                'gateway_subscription_id',
                'status',
                'billing_reason',
                'currency',
                'currency_name',
                'total_decimal',
                'amount_paid_decimal',
                'amount_due_decimal',
                'issued_at',
                'paid_at',
                'hosted_invoice_url',
                'invoice_pdf',
                'created_at',
                'updated_at',
            ]);

            $query->chunk(500, function ($invoices) use ($handle, $currencyMap) {
                foreach ($invoices as $invoice) {
                    $currencyCode = strtoupper((string) ($invoice->currency ?? 'USD'));
                    $currency = $currencyMap->get($currencyCode);

                    fputcsv($handle, [
                        (string) $invoice->gateway_invoice_id,
                        (string) ($invoice->invoice_number ?: $invoice->gateway_invoice_id),
                        (string) ($invoice->tenant_id ?? ''),
                        (string) ($invoice->subscription_id ?? ''),
                        strtoupper((string) ($invoice->gateway ?? 'stripe')),
                        (string) ($invoice->gateway_customer_id ?? ''),
                        (string) ($invoice->gateway_subscription_id ?? ''),
                        (string) ($invoice->status ?? ''),
                        (string) ($invoice->billing_reason ?? ''),
                        $currencyCode,
                        (string) ($currency?->name ?? $currencyCode),
                        number_format((float) ($invoice->total_decimal ?? 0), 2, '.', ''),
                        number_format((float) ($invoice->amount_paid_decimal ?? 0), 2, '.', ''),
                        number_format((float) ($invoice->amount_due_decimal ?? 0), 2, '.', ''),
                        optional($invoice->issued_at)?->format('Y-m-d H:i:s'),
                        optional($invoice->paid_at)?->format('Y-m-d H:i:s'),
                        (string) ($invoice->hosted_invoice_url ?? ''),
                        (string) ($invoice->invoice_pdf ?? ''),
                        optional($invoice->created_at)?->format('Y-m-d H:i:s'),
                        optional($invoice->updated_at)?->format('Y-m-d H:i:s'),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, $headers);
    }

    protected function buildInvoiceReport(array $filters): array
    {
        $currencyMap = $this->currencyMap();

        $invoiceQuery = BillingInvoice::query()->orderByDesc('issued_at')->orderByDesc('id');

        $this->applyInvoiceFilters($invoiceQuery, $filters);

        $filteredInvoices = $invoiceQuery->get();

        $recentInvoices = $filteredInvoices
            ->sortByDesc(fn (BillingInvoice $invoice) => (int) ($invoice->issued_at?->timestamp ?? 0))
            ->take(15)
        ->map(function (BillingInvoice $invoice) use ($currencyMap) {
            $currencyCode = strtoupper((string) ($invoice->currency ?? 'USD'));
            $currency = $currencyMap->get($currencyCode);

            return [
                'id' => (string) $invoice->gateway_invoice_id,
                'number' => (string) ($invoice->invoice_number ?: $invoice->gateway_invoice_id),
                'status' => (string) ($invoice->status ?? 'unknown'),
                'currency' => $currencyCode,
                'currency_name' => $currency?->name ?: $currencyCode,
                    'currency_symbol' => $currency?->symbol,
                    'currency_native_symbol' => $currency?->native_symbol,
                    'gateway' => strtoupper((string) ($invoice->gateway ?? 'stripe')),
                    'total_decimal' => (float) ($invoice->total_decimal ?? 0),
                    'amount_paid_decimal' => (float) ($invoice->amount_paid_decimal ?? 0),
                    'amount_due_decimal' => (float) ($invoice->amount_due_decimal ?? 0),
                    'created_at' => $invoice->issued_at?->timestamp,
                    'tenant_id' => (string) ($invoice->tenant_id ?? ''),
                    'subscription_id' => (int) ($invoice->subscription_id ?? 0),
                    'gateway_subscription_id' => (string) ($invoice->gateway_subscription_id ?? ''),
                    'hosted_invoice_url' => $invoice->hosted_invoice_url,
                    'invoice_pdf' => $invoice->invoice_pdf,
                ];
            })
        ->values();

        $monthlyInvoiceTrend = $filteredInvoices
            ->filter(fn (BillingInvoice $invoice) => ! empty($invoice->issued_at))
            ->groupBy(function (BillingInvoice $invoice) {
                return $invoice->issued_at?->format('Y-m') . '|' . strtoupper((string) ($invoice->currency ?? 'USD'));
            })
            ->map(function (Collection $group, string $key) use ($currencyMap) {
                [$month, $currencyCode] = explode('|', $key);
                $currency = $currencyMap->get($currencyCode);

                return [
                    'month' => $month,
                    'currency' => $currencyCode,
                    'currency_name' => $currency?->name ?: $currencyCode,
                    'currency_symbol' => $currency?->symbol,
                    'currency_native_symbol' => $currency?->native_symbol,
                    'invoices_count' => $group->count(),
                    'total_decimal' => round((float) $group->sum('total_decimal'), 2),
                    'amount_paid_decimal' => round((float) $group->sum('amount_paid_decimal'), 2),
                    'amount_due_decimal' => round((float) $group->sum('amount_due_decimal'), 2),
                ];
            })
            ->sortByDesc(fn (array $row) => $row['month'] . '|' . $row['currency'])
            ->values();

        return [
            'recentInvoices' => $recentInvoices,
            'monthlyInvoiceTrend' => $monthlyInvoiceTrend,
            'statuses' => BillingInvoice::query()
                ->whereNotNull('status')
                ->where('status', '!=', '')
                ->distinct()
                ->orderBy('status')
                ->pluck('status')
                ->values(),
            'gateways' => BillingInvoice::query()
                ->whereNotNull('gateway')
                ->where('gateway', '!=', '')
                ->distinct()
                ->orderBy('gateway')
                ->pluck('gateway')
                ->values(),
            'currencies' => Currency::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('code')
                ->pluck('code')
                ->values(),
            'months' => BillingInvoice::query()
                ->whereNotNull('issued_at')
                ->get()
                ->map(fn (BillingInvoice $invoice) => $invoice->issued_at?->format('Y-m'))
                ->filter()
        ->unique()
        ->sortDesc()
        ->values(),
        ];
    }

    protected function applyInvoiceFilters(object $query, array $filters): void
    {
        if (($filters['tenant_id'] ?? '') !== '') {
            $query->where('tenant_id', 'like', '%' . $filters['tenant_id'] . '%');
        }

        if (($filters['status'] ?? '') !== '') {
            $query->where('status', $filters['status']);
        }

        if (($filters['gateway'] ?? '') !== '') {
            $query->where('gateway', strtolower($filters['gateway']));
        }

        if (($filters['currency'] ?? '') !== '') {
            $query->where('currency', strtoupper($filters['currency']));
        }

        if (($filters['month'] ?? '') !== '') {
            $query->whereRaw('DATE_FORMAT(issued_at, "%Y-%m") = ?', [$filters['month']]);
        }
    }

    protected function resolveFilters(Request $request): array
    {
        return [
            'tenant_id' => trim((string) $request->string('tenant_id')),
            'status' => trim((string) $request->string('status')),
            'gateway' => trim((string) $request->string('gateway')),
            'month' => trim((string) $request->string('month')),
            'currency' => strtoupper(trim((string) $request->string('currency'))),
        ];
    }

    protected function buildExportFilename(array $filters): string
    {
        $parts = ['billing-invoices'];

        if (($filters['tenant_id'] ?? '') !== '') {
            $parts[] = 'tenant-' . preg_replace('/[^a-zA-Z0-9\-_]/', '-', $filters['tenant_id']);
        }

        if (($filters['status'] ?? '') !== '') {
            $parts[] = 'status-' . strtolower($filters['status']);
        }

        if (($filters['gateway'] ?? '') !== '') {
            $parts[] = 'gateway-' . strtolower($filters['gateway']);
        }

        if (($filters['currency'] ?? '') !== '') {
            $parts[] = 'currency-' . strtoupper($filters['currency']);
        }

        if (($filters['month'] ?? '') !== '') {
            $parts[] = 'month-' . $filters['month'];
        }

        $parts[] = now()->format('Ymd_His');

        return implode('_', $parts) . '.csv';
    }

    protected function currencyMap()
    {
        return Currency::query()
            ->get(['code', 'name', 'symbol', 'native_symbol', 'decimal_places'])
            ->keyBy(fn (Currency $currency) => strtoupper((string) $currency->code));
    }

    protected function centralConnection(): string
    {
        return (string) (config('tenancy.database.central_connection') ?? config('database.default'));
    }
}
