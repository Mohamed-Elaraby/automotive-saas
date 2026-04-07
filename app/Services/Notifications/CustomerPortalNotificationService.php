<?php

namespace App\Services\Notifications;

use App\Data\CustomerPortalNotificationData;
use App\Models\CustomerPortalNotification;
use Illuminate\Support\Facades\Schema;

class CustomerPortalNotificationService
{
    public function create(CustomerPortalNotificationData $data): ?CustomerPortalNotification
    {
        if (! $this->tableExists()) {
            return null;
        }

        $existingDuplicate = $this->findExistingDuplicate($data);
        if ($existingDuplicate) {
            $existingDuplicate->update([
                'message' => $data->message,
                'severity' => $data->severity,
                'target_url' => $data->targetUrl,
                'context_payload' => $data->contextPayload,
                'is_read' => false,
                'read_at' => null,
                'notified_at' => now(),
            ]);

            return $existingDuplicate->fresh();
        }

        return CustomerPortalNotification::query()->create(
            $data->toModelAttributes()
        );
    }

    protected function tableExists(): bool
    {
        $connection = (string) (config('tenancy.database.central_connection') ?? config('database.default'));

        return Schema::connection($connection)->hasTable('customer_portal_notifications');
    }

    protected function findExistingDuplicate(CustomerPortalNotificationData $data): ?CustomerPortalNotification
    {
        $windowMinutes = max(1, (int) config('notifications.portal.deduplication.window_minutes', 10));
        $event = (string) ($data->contextPayload['event'] ?? '');

        $query = CustomerPortalNotification::query()
            ->where('user_id', $data->userId)
            ->where('type', $data->type)
            ->where('title', $data->title)
            ->where('tenant_id', $data->tenantId)
            ->where('product_id', $data->productId)
            ->where('created_at', '>=', now()->subMinutes($windowMinutes));

        if ($event !== '') {
            $query->whereJsonContains('context_payload->event', $event);
        }

        return $query->latest('id')->first();
    }
}
