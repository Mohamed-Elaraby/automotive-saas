<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingInvoice;
use App\Support\Billing\SubscriptionStatuses;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BillingReportController extends Controller
{
    public function index(): View
    {
        $connection = $this->centralConnection();

        $baseSubscriptions = DB::connection($connection)
            ->table('subscriptions');

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

        $estimatedMrr = (float) DB::connection($connection)
            ->table('subscriptions')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('subscriptions.status', SubscriptionStatuses::ACTIVE)
            ->where('plans.billing_period', 'monthly')
            ->where('plans.billing_period', '!=', 'trial')
            ->sum('plans.price');

        $activePlanDistribution = DB::connection($connection)
            ->table('subscriptions')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->select([
                'plans.id as plan_id',
                'plans.name as plan_name',
                'plans.slug as plan_slug',
                'plans.billing_period as billing_period',
                'plans.price as price',
                'plans.currency as currency',
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
                'plans.currency'
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

        $invoiceReport = $this->buildInvoiceReport();

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
                'estimated_mrr' => round($estimatedMrr, 2),
            ],
            'activePlanDistribution' => $activePlanDistribution,
            'gatewayBreakdown' => $gatewayBreakdown,
            'recentInvoices' => $invoiceReport['recentInvoices'],
            'monthlyInvoiceTrend' => $invoiceReport['monthlyInvoiceTrend'],
        ]);
    }

    protected function buildInvoiceReport(): array
    {
        $allInvoices = BillingInvoice::query()
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->get();

        $recentInvoices = $allInvoices
            ->sortByDesc(fn (BillingInvoice $invoice) => (int) ($invoice->issued_at?->timestamp ?? 0))
            ->take(15)
        ->map(function (BillingInvoice $invoice) {
            return [
                'id' => (string) $invoice->gateway_invoice_id,
                'number' => (string) ($invoice->invoice_number ?: $invoice->gateway_invoice_id),
                'status' => (string) ($invoice->status ?? 'unknown'),
                'currency' => strtoupper((string) ($invoice->currency ?? 'USD')),
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

        $monthlyInvoiceTrend = $allInvoices
            ->filter(fn (BillingInvoice $invoice) => ! empty($invoice->issued_at))
            ->groupBy(function (BillingInvoice $invoice) {
                return $invoice->issued_at?->format('Y-m');
            })
            ->map(function (Collection $group, string $month) {
                /** @var BillingInvoice|null $sample */
                $sample = $group->first();

                return [
                    'month' => $month,
                    'currency' => (string) ($sample?->currency ?? 'USD'),
                    'invoices_count' => $group->count(),
                    'total_decimal' => round((float) $group->sum('total_decimal'), 2),
                    'amount_paid_decimal' => round((float) $group->sum('amount_paid_decimal'), 2),
                    'amount_due_decimal' => round((float) $group->sum('amount_due_decimal'), 2),
                ];
            })
            ->sortByDesc('month')
            ->values();

        return [
            'recentInvoices' => $recentInvoices,
            'monthlyInvoiceTrend' => $monthlyInvoiceTrend,
        ];
    }

    protected function centralConnection(): string
    {
        return (string) (config('tenancy.database.central_connection') ?? config('database.default'));
    }
}
