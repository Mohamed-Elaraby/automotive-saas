<?php

namespace App\Console\Commands\Billing;

use App\Services\Billing\TenantBillingLifecycleService;
use Illuminate\Console\Command;

class RunBillingLifecycleCommand extends Command
{
    protected $signature = 'billing:run-lifecycle {--dry-run}';

    protected $description = 'Run billing lifecycle transitions such as past_due grace expiration and cancelled subscription expiry';

    public function __construct(
        protected TenantBillingLifecycleService $tenantBillingLifecycleService
    ) {
        parent::__construct();
    }

public function handle(): int
{
    if ($this->option('dry-run')) {
        $this->info('Dry run mode is enabled. No updates will be written.');

        $pastDueToSuspend = \DB::connection(
            config('tenancy.database.central_connection') ?? config('database.default')
        )
            ->table('subscriptions')
            ->where('status', \App\Support\Billing\SubscriptionStatuses::PAST_DUE)
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<=', now())
            ->count();

        $cancelledToExpire = \DB::connection(
            config('tenancy.database.central_connection') ?? config('database.default')
        )
            ->table('subscriptions')
            ->where('status', \App\Support\Billing\SubscriptionStatuses::CANCELLED)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->count();

        $this->line('Would suspend: ' . $pastDueToSuspend);
        $this->line('Would expire cancelled: ' . $cancelledToExpire);

        return self::SUCCESS;
    }

    $result = $this->tenantBillingLifecycleService->runDailyLifecycle();

    $this->info('Billing lifecycle completed successfully.');
    $this->line('Suspended: ' . ($result['suspended'] ?? 0));
    $this->line('Expired cancelled: ' . ($result['expired_cancelled'] ?? 0));

    return self::SUCCESS;
}
}
