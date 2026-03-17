<?php

namespace App\Console\Commands\Billing;

use App\Services\Billing\StripeSubscriptionMigrationService;
use Illuminate\Console\Command;

class MigrateStripeSubscriptionsCommand extends Command
{
    protected $signature = 'billing:migrate-stripe-subscriptions
                            {--apply : Perform the Stripe subscription migration instead of dry-run}
                            {--tenant= : Migrate only one tenant_id}';

    protected $description = 'Migrate existing live Stripe subscriptions to the current correct local plan Stripe prices';

    public function __construct(
        protected StripeSubscriptionMigrationService $stripeSubscriptionMigrationService
    ) {
        parent::__construct();
    }

public function handle(): int
{
    $apply = (bool) $this->option('apply');
    $tenantId = $this->option('tenant');

    $rows = $this->stripeSubscriptionMigrationService
        ->migrate($apply, $tenantId)
        ->map(function (array $row) {
            return [
                'Sub ID' => $row['subscription_id'],
                'Tenant' => $row['tenant_id'],
                'Plan ID' => $row['plan_id'],
                'Status' => $row['status'],
                'Old Price' => $row['old_price_id'],
                'Expected' => $row['expected_price_id'],
                'New Price' => $row['new_price_id'],
                'Stripe Sub' => $row['stripe_subscription_id'],
                'Action' => $row['action'],
                'Migrated' => $row['migrated'] ? 'YES' : 'NO',
            ];
        })
        ->all();

    if (empty($rows)) {
        $this->warn('No Stripe subscriptions were found for migration.');
        return self::SUCCESS;
    }

    $this->table([
        'Sub ID',
        'Tenant',
        'Plan ID',
        'Status',
        'Old Price',
        'Expected',
        'New Price',
        'Stripe Sub',
        'Action',
        'Migrated',
    ], $rows);

    if (! $apply) {
        $this->warn('Dry-run only. Re-run with --apply to update Stripe subscriptions.');
        return self::SUCCESS;
    }

    $failed = collect($rows)->filter(function ($row) {
        return in_array($row['Action'], ['FAILED_NO_ITEM', 'FAILED_STRIPE_API', 'FAILED_UNEXPECTED'], true);
    })->count();

    if ($failed > 0) {
        $this->error("Completed with {$failed} failed subscription migrations.");
        return self::FAILURE;
    }

    $this->info('Stripe subscription migration completed.');
    return self::SUCCESS;
}
}
