<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Support\Billing\SubscriptionStatuses;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $connection = $this->centralConnection();

        $filters = [
            'tenant_id' => trim((string) $request->string('tenant_id')),
            'status' => trim((string) $request->string('status')),
            'plan_id' => $request->filled('plan_id') ? (int) $request->input('plan_id') : null,
            'gateway' => trim((string) $request->string('gateway')),
        ];

        $baseQuery = DB::connection($connection)
            ->table('subscriptions')
            ->leftJoin('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->select([
                'subscriptions.id',
                'subscriptions.tenant_id',
                'subscriptions.plan_id',
                'subscriptions.status',
                'subscriptions.trial_ends_at',
                'subscriptions.grace_ends_at',
                'subscriptions.last_payment_failed_at',
                'subscriptions.past_due_started_at',
                'subscriptions.suspended_at',
                'subscriptions.cancelled_at',
                'subscriptions.ends_at',
                'subscriptions.payment_failures_count',
                'subscriptions.gateway',
                'subscriptions.gateway_customer_id',
                'subscriptions.gateway_subscription_id',
                'subscriptions.gateway_checkout_session_id',
                'subscriptions.gateway_price_id',
                'subscriptions.created_at',
                'subscriptions.updated_at',
                'plans.name as plan_name',
                'plans.slug as plan_slug',
                'plans.billing_period as plan_billing_period',
                'plans.price as plan_price',
                'plans.currency as plan_currency',
            ]);

        $this->applyFilters($baseQuery, $filters);

        $perPage = 20;
        $subscriptions = $baseQuery
            ->orderByDesc('subscriptions.id')
            ->paginate($perPage)
            ->withQueryString();

        $statusCounts = $this->buildStatusCounts($connection);
        $plans = Plan::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $gatewayOptions = DB::connection($connection)
            ->table('subscriptions')
            ->select('gateway')
            ->whereNotNull('gateway')
            ->where('gateway', '!=', '')
            ->distinct()
            ->orderBy('gateway')
            ->pluck('gateway');

        return view('admin.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'filters' => $filters,
            'statusCounts' => $statusCounts,
            'plans' => $plans,
            'gatewayOptions' => $gatewayOptions,
            'statusOptions' => [
                SubscriptionStatuses::TRIALING,
                SubscriptionStatuses::ACTIVE,
                SubscriptionStatuses::PAST_DUE,
                SubscriptionStatuses::SUSPENDED,
                SubscriptionStatuses::CANCELLED,
                SubscriptionStatuses::EXPIRED,
            ],
        ]);
    }

    protected function applyFilters(object $query, array $filters): void
    {
        if ($filters['tenant_id'] !== '') {
            $query->where('subscriptions.tenant_id', 'like', '%' . $filters['tenant_id'] . '%');
        }

        if ($filters['status'] !== '') {
            $query->where('subscriptions.status', $filters['status']);
        }

        if (! empty($filters['plan_id'])) {
            $query->where('subscriptions.plan_id', $filters['plan_id']);
        }

        if ($filters['gateway'] !== '') {
            $query->where('subscriptions.gateway', $filters['gateway']);
        }
    }

    protected function buildStatusCounts(string $connection): array
    {
        $rows = DB::connection($connection)
            ->table('subscriptions')
            ->select('status', DB::raw('COUNT(*) as aggregate_count'))
            ->groupBy('status')
            ->pluck('aggregate_count', 'status');

        return [
            'total' => (int) DB::connection($connection)->table('subscriptions')->count(),
            SubscriptionStatuses::ACTIVE => (int) ($rows[SubscriptionStatuses::ACTIVE] ?? 0),
            SubscriptionStatuses::TRIALING => (int) ($rows[SubscriptionStatuses::TRIALING] ?? 0),
            SubscriptionStatuses::PAST_DUE => (int) ($rows[SubscriptionStatuses::PAST_DUE] ?? 0),
            SubscriptionStatuses::SUSPENDED => (int) ($rows[SubscriptionStatuses::SUSPENDED] ?? 0),
            SubscriptionStatuses::CANCELLED => (int) ($rows[SubscriptionStatuses::CANCELLED] ?? 0),
            SubscriptionStatuses::EXPIRED => (int) ($rows[SubscriptionStatuses::EXPIRED] ?? 0),
        ];
    }

    protected function centralConnection(): string
    {
        return (string) (config('tenancy.database.central_connection') ?? config('database.default'));
    }
}
