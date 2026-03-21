<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Billing\SubscriptionStatuses;
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
        ]);
    }

    protected function centralConnection(): string
    {
        return (string) (config('tenancy.database.central_connection') ?? config('database.default'));
    }
}
