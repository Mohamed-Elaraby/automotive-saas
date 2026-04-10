<?php

namespace App\Jobs\Admin;

use App\Models\AdminActivityLog;
use App\Models\TenantProductSubscription;
use App\Services\Billing\AdminTenantProductSubscriptionStripeSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncTenantProductSubscriptionFromStripeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $subscriptionId,
        public ?int $adminUserId = null,
        public ?string $adminEmail = null
    ) {
    }

    public function handle(AdminTenantProductSubscriptionStripeSyncService $syncService): void
    {
        $subscription = TenantProductSubscription::query()->find($this->subscriptionId);

        if (! $subscription) {
            $this->log('tenant.product_subscription.sync_from_stripe.job_skipped', null, null, [
                'reason' => 'missing_subscription',
            ]);

            return;
        }

        try {
            $synced = $syncService->sync($subscription);

            $this->log('tenant.product_subscription.sync_from_stripe.job_succeeded', $synced->id, (string) $synced->tenant_id, [
                'status' => $synced->status,
                'last_sync_status' => $synced->last_sync_status,
            ]);
        } catch (Throwable $exception) {
            $this->log('tenant.product_subscription.sync_from_stripe.job_failed', $subscription->id, (string) $subscription->tenant_id, [
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    protected function log(string $action, int|string|null $subjectId, ?string $tenantId, array $contextPayload = []): void
    {
        AdminActivityLog::query()->create([
            'admin_user_id' => $this->adminUserId,
            'admin_email' => $this->adminEmail,
            'action' => $action,
            'subject_type' => 'tenant_product_subscription',
            'subject_id' => $subjectId !== null ? (string) $subjectId : null,
            'tenant_id' => $tenantId,
            'context_payload' => $contextPayload,
        ]);
    }
}
