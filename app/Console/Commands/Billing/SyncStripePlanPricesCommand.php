<?php

namespace App\Console\Commands\Billing;

use App\Services\Billing\StripePlanCatalogSyncService;
use Illuminate\Console\Command;

class SyncStripePlanPricesCommand extends Command
{
    protected $signature = 'billing:sync-stripe-plan-prices
                            {--apply : Create/link Stripe products and prices and update local plans table}
                            {--slug= : Sync only one paid plan slug}';

    protected $description = 'Synchronize local paid plans with Stripe products/prices and update stripe_price_id mappings';

    public function __construct(
        protected StripePlanCatalogSyncService $stripePlanCatalogSyncService
    ) {
        parent::__construct();
    }

public function handle(): int
{
    $apply = (bool) $this->option('apply');
    $slug = $this->option('slug');

    $rows = $this->stripePlanCatalogSyncService
        ->syncPaidPlans($apply, $slug)
        ->map(function (array $row) {
            return [
                'Plan' => $row['plan_name'],
                'Slug' => $row['slug'],
                'Local Price' => number_format((float) $row['local_price'], 2),
                'Currency' => $row['currency'],
                'Period' => $row['billing_period'],
                'Old Price ID' => $row['old_price_id'],
                'New Price ID' => $row['new_price_id'],
                'Product ID' => $row['product_id'],
                'Action' => $row['action'],
                'Aligned Before' => $row['aligned_before'] ? 'YES' : 'NO',
                'Aligned After' => $row['aligned_after'] ? 'YES' : 'NO',
            ];
        })
        ->all();

    if (empty($rows)) {
        $this->warn('No paid plans were found for synchronization.');
        return self::SUCCESS;
    }

    $this->table([
        'Plan',
        'Slug',
        'Local Price',
        'Currency',
        'Period',
        'Old Price ID',
        'New Price ID',
        'Product ID',
        'Action',
        'Aligned Before',
        'Aligned After',
    ], $rows);

    if (! $apply) {
        $this->warn('Dry-run only. Re-run with --apply to create/link Stripe prices and update local plans.');
        return self::SUCCESS;
    }

    $failed = collect($rows)->where('Aligned After', 'NO')->count();

    if ($failed > 0) {
        $this->error("Completed with {$failed} plans still not aligned.");
        return self::FAILURE;
    }

    $this->info('Stripe plan price synchronization completed successfully.');
    return self::SUCCESS;
}
}
