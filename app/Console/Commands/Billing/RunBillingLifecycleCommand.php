<?php

namespace App\Console\Commands\Billing;

use App\Models\Subscription;
use App\Services\Billing\BillingNotificationService;
use App\Services\Billing\TenantBillingLifecycleService;
use App\Support\Billing\SubscriptionStatuses;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class RunBillingLifecycleCommand extends Command
{
    protected $signature = 'billing:run-lifecycle';

    protected $description = 'Run billing lifecycle transitions and alerts';

    public function handle(
        TenantBillingLifecycleService $lifecycleService,
        BillingNotificationService $billingNotificationService
    ): int {
        $now = now();

        $trialEndingSoonSubscriptions = Subscription::query()
            ->where('status', SubscriptionStatuses::TRIALING)
            ->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [$now->copy(), $now->copy()->addDays(2)])
            ->get();

        foreach ($trialEndingSoonSubscriptions as $subscription) {
            try {
                if (method_exists($lifecycleService, 'emitTrialEndingSoon')) {
                    $lifecycleService->emitTrialEndingSoon($subscription, Carbon::now());
                }

                $billingNotificationService->trialEnding($subscription->fresh(), [
                    'source' => 'billing.run_lifecycle',
                    'trial_ends_at' => optional($subscription->trial_ends_at)?->format('Y-m-d H:i:s'),
                ]);

                $this->info("Trial ending notification emitted for subscription #{$subscription->id}");
            } catch (Throwable $e) {
                report($e);
                $this->error("Failed to emit trial ending notification for subscription #{$subscription->id}");
            }
        }

        $pastDueToSuspend = Subscription::query()
            ->where('status', SubscriptionStatuses::PAST_DUE)
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<=', $now)
            ->get();

        foreach ($pastDueToSuspend as $subscription) {
            try {
                $lifecycleService->markAsSuspended($subscription, Carbon::now());

                $billingNotificationService->suspended($subscription->fresh(), [
                    'source' => 'billing.run_lifecycle',
                    'grace_ends_at' => optional($subscription->grace_ends_at)?->format('Y-m-d H:i:s'),
                ]);

                $this->info("Subscription #{$subscription->id} marked as suspended.");
            } catch (Throwable $e) {
                report($e);
                $this->error("Failed to suspend subscription #{$subscription->id}");
            }
        }

        $cancelledToExpire = Subscription::query()
            ->where('status', SubscriptionStatuses::CANCELLED)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $now)
            ->get();

        foreach ($cancelledToExpire as $subscription) {
            try {
                $lifecycleService->markAsExpired($subscription, Carbon::now());

                $billingNotificationService->subscriptionExpired($subscription->fresh(), [
                    'source' => 'billing.run_lifecycle',
                    'ends_at' => optional($subscription->ends_at)?->format('Y-m-d H:i:s'),
                    'previous_status' => SubscriptionStatuses::CANCELLED,
                ]);

                $this->info("Subscription #{$subscription->id} marked as expired.");
            } catch (Throwable $e) {
                report($e);
                $this->error("Failed to expire cancelled subscription #{$subscription->id}");
            }
        }

        return self::SUCCESS;
    }
}
