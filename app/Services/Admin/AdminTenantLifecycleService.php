<?php

namespace App\Services\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class AdminTenantLifecycleService
{
    public function centralConnectionName(): string
    {
        return (string) (Config::get('tenancy.database.central_connection') ?: Config::get('database.default'));
    }

    public function latestSubscriptionByTenantId(string $tenantId): ?object
    {
        return DB::connection($this->centralConnectionName())
            ->table('subscriptions')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->first();
    }

    public function suspendLatestSubscription(string $tenantId): void
    {
        $subscription = $this->latestSubscriptionByTenantId($tenantId);

        if (! $subscription) {
            throw new RuntimeException('No linked subscription was found for this tenant.');
        }

        DB::connection($this->centralConnectionName())
            ->table('subscriptions')
            ->where('id', $subscription->id)
            ->update([
                'status' => 'suspended',
                'suspended_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function activateLatestSubscription(string $tenantId): void
    {
        $subscription = $this->latestSubscriptionByTenantId($tenantId);

        if (! $subscription) {
            throw new RuntimeException('No linked subscription was found for this tenant.');
        }

        $currentStatus = (string) ($subscription->status ?? '');

        if (in_array($currentStatus, ['cancelled', 'expired'], true)) {
            throw new RuntimeException('Cancelled or expired subscriptions cannot be re-activated from this screen.');
        }

        $trialEndsAt = $this->nullableCarbon($subscription->trial_ends_at ?? null);

        $hasPaidGatewaySignals =
            (string) ($subscription->gateway ?? '') === 'stripe' ||
            filled($subscription->gateway_subscription_id ?? null);

        if ($hasPaidGatewaySignals) {
            $restoredStatus = 'active';
        } elseif ($trialEndsAt && $trialEndsAt->isFuture()) {
            $restoredStatus = 'trialing';
        } else {
            $restoredStatus = 'active';
        }

        DB::connection($this->centralConnectionName())
            ->table('subscriptions')
            ->where('id', $subscription->id)
            ->update([
                'status' => $restoredStatus,
                'suspended_at' => null,
                'updated_at' => now(),
            ]);
    }

    public function extendLatestTrial(string $tenantId, int $days): void
    {
        $subscription = $this->latestSubscriptionByTenantId($tenantId);

        if (! $subscription) {
            throw new RuntimeException('No linked subscription was found for this tenant.');
        }

        $trialEndsAt = $this->nullableCarbon($subscription->trial_ends_at ?? null);

        if (! $trialEndsAt) {
            throw new RuntimeException('This tenant does not have a trial end date to extend.');
        }

        $baseDate = $trialEndsAt->isFuture() ? $trialEndsAt->copy() : now();

        $newTrialEndsAt = $baseDate->addDays($days);

        DB::connection($this->centralConnectionName())
            ->table('subscriptions')
            ->where('id', $subscription->id)
            ->update([
                'trial_ends_at' => $newTrialEndsAt,
                'status' => 'trialing',
                'updated_at' => now(),
            ]);
    }

    public function availablePlans(): Collection
    {
        $connection = $this->centralConnectionName();

        if (! Schema::connection($connection)->hasTable('plans')) {
            return collect();
        }

        $columns = Schema::connection($connection)->getColumnListing('plans');

        $selectColumns = array_values(array_filter([
            'id',
            'name',
            'slug',
            'billing_period',
            'price',
            in_array('currency_code', $columns, true) ? 'currency_code' : null,
            in_array('currency', $columns, true) ? 'currency' : null,
            'is_active',
        ]));

        $query = DB::connection($connection)
            ->table('plans')
            ->select($selectColumns)
            ->orderByDesc('is_active');

        if (in_array('sort_order', $columns, true)) {
            $query->orderBy('sort_order');
        }

        $query->orderBy('id');

        return $query->get();
    }

    public function changeLatestPlan(string $tenantId, int $planId): void
    {
        $subscription = $this->latestSubscriptionByTenantId($tenantId);

        if (! $subscription) {
            throw new RuntimeException('No linked subscription was found for this tenant.');
        }

        $plan = DB::connection($this->centralConnectionName())
            ->table('plans')
            ->where('id', $planId)
            ->first();

        if (! $plan) {
            throw new RuntimeException('The selected plan does not exist.');
        }

        $isStripeLinked =
            (string) ($subscription->gateway ?? '') === 'stripe' ||
            filled($subscription->gateway_subscription_id ?? null);

        if ($isStripeLinked) {
            throw new RuntimeException(
                'This subscription is linked to Stripe. Change plan for Stripe-linked subscriptions from the dedicated Stripe-aware billing flow, not from this local admin action.'
            );
        }

        DB::connection($this->centralConnectionName())
            ->table('subscriptions')
            ->where('id', $subscription->id)
            ->update([
                'plan_id' => $plan->id,
                'billing_period' => $plan->billing_period ?? $subscription->billing_period,
                'updated_at' => now(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteTenant(string $tenantId): array
    {
        $tenant = $this->findTenantOrFail($tenantId);
        $subscription = $this->latestSubscriptionByTenantId($tenantId);

        if ($subscription && $this->isStripeLinkedSubscription($subscription) && ! $this->isTerminalSubscriptionStatus($subscription->status ?? null)) {
            throw new RuntimeException(
                'This tenant has a live Stripe-linked subscription. Cancel or expire the subscription first before deleting the tenant.'
            );
        }

        $databaseName = $this->databaseNameFromTenant($tenant);
        $databaseDropped = $this->dropTenantDatabaseIfSupported($databaseName);
        $connection = $this->centralConnectionName();

        $deleted = DB::connection($connection)->transaction(function () use ($connection, $tenantId): array {
            $deleted = [
                'domains' => $this->deleteRows($connection, 'domains', $tenantId),
                'tenant_users' => $this->deleteRows($connection, 'tenant_users', $tenantId),
                'subscriptions' => $this->deleteRows($connection, 'subscriptions', $tenantId),
                'coupon_redemptions' => $this->deleteRows($connection, 'coupon_redemptions', $tenantId),
                'tenant' => $this->deleteTenantRow($connection, $tenantId),
            ];

            return $deleted;
        });

        DB::purge('tenant');

        return [
            'tenant_id' => $tenantId,
            'database_name' => $databaseName,
            'database_dropped' => $databaseDropped,
            'deleted' => $deleted,
            'subscription_status' => $subscription->status ?? null,
            'stripe_linked_subscription' => $subscription ? $this->isStripeLinkedSubscription($subscription) : false,
        ];
    }

    protected function nullableCarbon(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value);
    }

    protected function tenantModelClass(): string
    {
        return (string) (Config::get('tenancy.tenant_model') ?: \App\Models\Tenant::class);
    }

    protected function findTenantOrFail(string $tenantId): Model
    {
        $tenantModelClass = $this->tenantModelClass();

        /** @var Model|null $tenant */
        $tenant = $tenantModelClass::query()->find($tenantId);

        if (! $tenant) {
            throw new RuntimeException('The tenant record was not found.');
        }

        return $tenant;
    }

    protected function isStripeLinkedSubscription(object $subscription): bool
    {
        return (string) ($subscription->gateway ?? '') === 'stripe'
            || filled($subscription->gateway_subscription_id ?? null);
    }

    protected function isTerminalSubscriptionStatus(mixed $status): bool
    {
        return in_array((string) $status, ['cancelled', 'expired'], true);
    }

    protected function databaseNameFromTenant(Model $tenant): ?string
    {
        $data = $tenant->getAttribute('data');

        if (is_string($data)) {
            $decoded = json_decode($data, true);
            $data = is_array($decoded) ? $decoded : [];
        } elseif (is_object($data)) {
            $data = (array) $data;
        } elseif (! is_array($data)) {
            $data = [];
        }

        return $tenant->getAttribute('tenancy_db_name')
            ?: $tenant->getAttribute('database')
            ?: ($data['database'] ?? null)
            ?: ($data['db_name'] ?? null);
    }

    protected function dropTenantDatabaseIfSupported(?string $databaseName): bool
    {
        if (blank($databaseName)) {
            return false;
        }

        $connection = $this->centralConnectionName();
        $driver = (string) DB::connection($connection)->getDriverName();

        if ($driver !== 'mysql') {
            return false;
        }

        $exists = DB::connection($connection)->selectOne(
            'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1',
            [$databaseName]
        );

        if (! $exists) {
            return false;
        }

        $escapedDatabaseName = str_replace('`', '``', $databaseName);

        DB::connection($connection)->statement("DROP DATABASE IF EXISTS `{$escapedDatabaseName}`");

        return true;
    }

    protected function deleteRows(string $connection, string $table, string $tenantId): int
    {
        if (! Schema::connection($connection)->hasTable($table)) {
            return 0;
        }

        return DB::connection($connection)
            ->table($table)
            ->where('tenant_id', $tenantId)
            ->delete();
    }

    protected function deleteTenantRow(string $connection, string $tenantId): int
    {
        if (! Schema::connection($connection)->hasTable('tenants')) {
            return 0;
        }

        return DB::connection($connection)
            ->table('tenants')
            ->where('id', $tenantId)
            ->delete();
    }
}
