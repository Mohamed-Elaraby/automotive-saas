<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\Billing\SubscriptionStatuses;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Database\Models\Domain;

class TenantsCleanup extends Command
{
    protected $signature = 'tenants:cleanup
        {--grace-days=7 : Days to keep expired trials before deletion}
        {--dry-run : Show what would happen without making changes}';

    protected $description = 'Expire ended trials and delete tenants after grace period';

    public function handle(): int
    {
        $centralConnection = config('tenancy.database.central_connection') ?? config('database.default');
        $graceDays = (int) $this->option('grace-days');
        $dryRun = (bool) $this->option('dry-run');
        $now = now();

        $this->info('Starting tenants cleanup...');
        $this->line('Central connection: ' . $centralConnection);
        $this->line('Grace days: ' . $graceDays);
        $this->line('Dry run: ' . ($dryRun ? 'yes' : 'no'));

        // 1) Expire ended trials
        $expiredSubscriptions = DB::connection($centralConnection)
            ->table('subscriptions')
            ->where('status', SubscriptionStatuses::TRIALING)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', $now)
            ->get();

        $this->info('Trials to expire: ' . $expiredSubscriptions->count());

        foreach ($expiredSubscriptions as $subscription) {
            $this->line("Expiring tenant [{$subscription->tenant_id}]");

            if (! $dryRun) {
                DB::connection($centralConnection)
                    ->table('subscriptions')
                    ->where('tenant_id', $subscription->tenant_id)
                    ->update([
                        'status' => SubscriptionStatuses::EXPIRED,
                        'updated_at' => $now,
                    ]);
            }
        }

        // 2) Re-query expired tenants after updates so expire + delete can happen in same run
        $subscriptionsToDelete = DB::connection($centralConnection)
            ->table('subscriptions')
            ->where('status', SubscriptionStatuses::EXPIRED)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', Carbon::now()->subDays($graceDays))
            ->get();

        $this->info('Expired tenants past grace period: ' . $subscriptionsToDelete->count());

        foreach ($subscriptionsToDelete as $subscription) {
            $tenantId = $subscription->tenant_id;

            if (! $this->eligibleForAutomaticCleanup($centralConnection, (string) $tenantId)) {
                $this->warn("Skipping tenant [{$tenantId}] because automatic cleanup is limited to expired trial tenants without active billing linkage.");

                continue;
            }

            /** @var \App\Models\Tenant|null $tenant */
            $tenant = Tenant::query()->find($tenantId);

            if (! $tenant) {
                $this->warn("Tenant [{$tenantId}] not found in tenants table, cleaning central records only.");

                if (! $dryRun) {
                    $this->deleteCentralRecords($centralConnection, $tenantId);
                }

                continue;
            }

            $dbName = data_get($tenant->data, 'db_name');

            // Fallback in case db_name is missing
            if (empty($dbName)) {
                $dbName = 'tenant_' . $tenant->id;
            }

            $this->line("Deleting tenant [{$tenantId}] with DB [{$dbName}]");

            if ($dryRun) {
                continue;
            }

            try {
                $this->deleteTenantSafely($tenant, $centralConnection, $dbName);
                $this->info("Tenant [{$tenantId}] deleted successfully.");
            } catch (\Throwable $e) {
                report($e);
                $this->error("Failed deleting tenant [{$tenantId}]: " . $e->getMessage());
            }
        }

        $this->info('Tenants cleanup finished.');

        return self::SUCCESS;
    }

    protected function deleteTenantSafely(Tenant $tenant, string $centralConnection, ?string $dbName): void
    {
        try {
            if (function_exists('tenancy') && tenancy()->initialized) {
                tenancy()->end();
            }
        } catch (\Throwable) {
            //
        }

        DB::purge('tenant');

        // 1) Drop database first, outside transaction
        if (! empty($dbName)) {
            $escapedDbName = str_replace('`', '``', $dbName);

            $exists = DB::connection($centralConnection)->selectOne(
                'SELECT SCHEMA_NAME
                 FROM INFORMATION_SCHEMA.SCHEMATA
                 WHERE SCHEMA_NAME = ?',
                [$dbName]
            );

            if ($exists) {
                $this->line("Dropping database [{$dbName}]...");

                DB::connection($centralConnection)->statement("DROP DATABASE IF EXISTS `{$escapedDbName}`");

                $this->info("Database [{$dbName}] dropped.");
            } else {
                $this->warn("Database [{$dbName}] not found, skipping DROP DATABASE.");
            }
        } else {
            $this->warn("No db_name found for tenant [{$tenant->id}], skipping database drop.");
        }

        // 2) Delete central records
        DB::connection($centralConnection)->transaction(function () use ($tenant, $centralConnection) {
            DB::connection($centralConnection)->table('domains')
                ->where('tenant_id', $tenant->id)
                ->delete();

            DB::connection($centralConnection)->table('tenant_users')
                ->where('tenant_id', $tenant->id)
                ->delete();

            DB::connection($centralConnection)->table('subscriptions')
                ->where('tenant_id', $tenant->id)
                ->delete();

            if (DB::connection($centralConnection)->getSchemaBuilder()->hasTable('coupon_redemptions')) {
                DB::connection($centralConnection)->table('coupon_redemptions')
                    ->where('tenant_id', $tenant->id)
                    ->delete();
            }

            DB::connection($centralConnection)->table('tenants')
                ->where('id', $tenant->id)
                ->delete();
        });

        DB::purge('tenant');
    }

    protected function deleteCentralRecords(string $centralConnection, string $tenantId): void
    {
        $domainCount = Domain::query()->where('tenant_id', $tenantId)->count();

        if ($domainCount > 0) {
            $this->warn("Tenant [{$tenantId}] missing from tenants table, but {$domainCount} domain record(s) still exist.");
        }

        DB::connection($centralConnection)->transaction(function () use ($centralConnection, $tenantId) {
            DB::connection($centralConnection)->table('domains')
                ->where('tenant_id', $tenantId)
                ->delete();

            DB::connection($centralConnection)->table('tenant_users')
                ->where('tenant_id', $tenantId)
                ->delete();

            DB::connection($centralConnection)->table('subscriptions')
                ->where('tenant_id', $tenantId)
                ->delete();

            if (DB::connection($centralConnection)->getSchemaBuilder()->hasTable('coupon_redemptions')) {
                DB::connection($centralConnection)->table('coupon_redemptions')
                    ->where('tenant_id', $tenantId)
                    ->delete();
            }

            DB::connection($centralConnection)->table('tenants')
                ->where('id', $tenantId)
                ->delete();
        });
    }

    protected function eligibleForAutomaticCleanup(string $centralConnection, string $tenantId): bool
    {
        $subscriptions = DB::connection($centralConnection)
            ->table('subscriptions')
            ->where('tenant_id', $tenantId)
            ->get();

        if ($subscriptions->isEmpty()) {
            return true;
        }

        $hasStripeLinkage = $subscriptions->contains(function ($subscription) {
            return (string) ($subscription->gateway ?? '') === 'stripe'
                || filled($subscription->gateway_customer_id ?? null)
                || filled($subscription->gateway_subscription_id ?? null)
                || filled($subscription->gateway_checkout_session_id ?? null)
                || filled($subscription->gateway_price_id ?? null);
        });

        if ($hasStripeLinkage) {
            return false;
        }

        return $subscriptions->every(function ($subscription) {
            return ($subscription->status ?? null) === SubscriptionStatuses::EXPIRED
                && ! empty($subscription->trial_ends_at);
        });
    }
}
