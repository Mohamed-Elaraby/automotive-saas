<?php

namespace App\Console\Commands\Billing;

use App\Models\Subscription;
use App\Services\Billing\TenantBillingLifecycleService;
use App\Support\Billing\SubscriptionStatuses;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RunBillingLifecycleCommand extends Command
{
    protected $signature = 'billing:run-lifecycle';

    protected $description = 'Run billing lifecycle transitions and alerts';

    public function handle(TenantBillingLifecycleService $lifecycleService): int
    {
        $now = now();

        $trialEndingSoonSubscriptions = Subscription::query()
            ->where('status', SubscriptionStatuses::TRIALING)
            ->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [$now->copy(), $now->copy()->addDays(2)])
            ->get();

        foreach ($trialEndingSoonSubscriptions as $subscription) {
            $lifecycleService->emitTrialEndingSoon($subscription, Carbon::now());
            $this->info("Trial ending notification emitted for subscription #{$subscription->id}");
        }

        $pastDueToSuspend = Subscription::query()
            ->where('status', SubscriptionStatuses::PAST_DUE)
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<=', $now)
            ->get();

        foreach ($pastDueToSuspend as $subscription) {
            $lifecycleService->markAsSuspended($subscription, Carbon::now());
            $this->info("Subscription #{$subscription->id} marked as suspended.");
        }

        return self::SUCCESS;
    }
}
