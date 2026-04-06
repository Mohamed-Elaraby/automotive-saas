<?php

namespace App\Console\Commands\Billing;

use App\Models\Subscription;
use App\Models\TenantProductSubscription;
use App\Services\Billing\TenantProductSubscriptionSyncService;
use Illuminate\Console\Command;

class RepairTenantProductSubscriptionMirrorsCommand extends Command
{
    protected $signature = 'billing:repair-product-subscription-mirrors
                            {--apply : Perform the mirror repair instead of dry-run}
                            {--tenant= : Repair only one tenant_id}
                            {--subscription= : Repair only one local subscription id}
                            {--only-missing : Repair only records that are missing a mirror row}
                            {--limit=100 : Maximum number of legacy subscriptions to inspect}';

    protected $description = 'Repair tenant_product_subscriptions from legacy subscriptions for product-aware plans';

    public function __construct(
        protected TenantProductSubscriptionSyncService $tenantProductSubscriptionSyncService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $tenantId = $this->option('tenant') ?: null;
        $subscriptionId = $this->option('subscription') ? (int) $this->option('subscription') : null;
        $onlyMissing = (bool) $this->option('only-missing');
        $limit = max(1, min((int) $this->option('limit'), 500));

        $subscriptions = Subscription::query()
            ->with('plan')
            ->whereHas('plan', fn ($query) => $query->whereNotNull('product_id'))
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->when($subscriptionId, fn ($query) => $query->whereKey($subscriptionId))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->warn('No product-aware legacy subscriptions were found for mirror repair.');
            return self::SUCCESS;
        }

        $rows = [];
        $repaired = 0;

        foreach ($subscriptions as $subscription) {
            $mirror = TenantProductSubscription::query()
                ->where('legacy_subscription_id', $subscription->id)
                ->first();

            $status = $mirror ? 'MISMATCH_OR_EXISTS' : 'MISSING';

            if ($onlyMissing && $mirror) {
                continue;
            }

            if ($apply) {
                $synced = $this->tenantProductSubscriptionSyncService->syncFromLegacySubscription($subscription);
                $status = $synced ? ($mirror ? 'REPAIRED' : 'CREATED') : 'SKIPPED';

                if (in_array($status, ['REPAIRED', 'CREATED'], true)) {
                    $repaired++;
                }
            }

            $rows[] = [
                'Sub ID' => $subscription->id,
                'Tenant' => $subscription->tenant_id,
                'Plan ID' => $subscription->plan_id,
                'Product ID' => $subscription->plan?->product_id ?? '-',
                'Mirror Before' => $mirror ? 'YES' : 'NO',
                'Action' => $apply ? $status : ($mirror ? 'WOULD_REPAIR' : 'WOULD_CREATE'),
            ];
        }

        if (empty($rows)) {
            $this->warn('No legacy subscription mirrors matched the requested repair scope.');
            return self::SUCCESS;
        }

        $this->table([
            'Sub ID',
            'Tenant',
            'Plan ID',
            'Product ID',
            'Mirror Before',
            'Action',
        ], $rows);

        if (! $apply) {
            $this->warn('Dry-run only. Re-run with --apply to repair the selected mirror rows.');
            return self::SUCCESS;
        }

        $this->info("Mirror repair completed. Updated or created {$repaired} row(s).");
        return self::SUCCESS;
    }
}
