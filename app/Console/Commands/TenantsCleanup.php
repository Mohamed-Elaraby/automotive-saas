<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TenantsCleanup extends Command
{
    protected $signature = 'tenants:cleanup {--grace-days=7 : Days to keep expired trials before deletion} {--dry-run : Show what would happen without making changes}';

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
            ->where('status', 'trialing')
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
                        'status' => 'expired',
                        'updated_at' => $now,
                    ]);
            }
        }

        // 2) Delete expired tenants after grace period
        $subscriptionsToDelete = DB::connection($centralConnection)
            ->table('subscriptions')
            ->where('status', 'expired')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', Carbon::now()->subDays($graceDays))
            ->get();

        $this->info('Expired tenants past grace period: ' . $subscriptionsToDelete->count());

        foreach ($subscriptionsToDelete as $subscription) {
            $tenantId = $subscription->tenant_id;

            /** @var \App\Models\Tenant|null $tenant */
            $tenant = Tenant::query()->find($tenantId);

            if (! $tenant) {
                $this->warn("Tenant [{$tenantId}] not found, cleaning central records only.");

                if (! $dryRun) {
                    $this->deleteCentralRecords($centralConnection, $tenantId);
                }

                continue;
            }

            $dbName = data_get($tenant->data, 'db_name');

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
        // Always end tenancy if anything was initialized somewhere
        try {
            if (function_exists('tenancy') && tenancy()->initialized) {
                tenancy()->end();
            }
        } catch (\Throwable) {
            //
        }

        DB::purge('tenant');

        DB::connection($centralConnection)->transaction(function () use ($tenant, $centralConnection, $dbName) {
            DB::connection($centralConnection)->table('domains')
                ->where('tenant_id', $tenant->id)
                ->delete();

            DB::connection($centralConnection)->table('tenant_users')
                ->where('tenant_id', $tenant->id)
                ->delete();

            DB::connection($centralConnection)->table('subscriptions')
                ->where('tenant_id', $tenant->id)
                ->delete();

            DB::connection($centralConnection)->table('tenants')
                ->where('id', $tenant->id)
                ->delete();

            if (! empty($dbName)) {
                $escapedDbName = str_replace('`', '``', $dbName);
                DB::connection($centralConnection)->statement("DROP DATABASE IF EXISTS `{$escapedDbName}`");
            }
        });

        DB::purge('tenant');
    }

    protected function deleteCentralRecords(string $centralConnection, string $tenantId): void
    {
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

            DB::connection($centralConnection)->table('tenants')
                ->where('id', $tenantId)
                ->delete();
        });
    }
}
