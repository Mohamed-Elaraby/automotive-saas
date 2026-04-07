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

        if ($this->shouldDeduplicate($data)) {
            return $this->findExistingDuplicate($data);
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

    protected function shouldDeduplicate(CustomerPortalNotificationData $data): bool
    {
        return $this->findExistingDuplicate($data) !== null;
    }

    protected function findExistingDuplicate(CustomerPortalNotificationData $data): ?CustomerPortalNotification
    {
        $windowMinutes = 10;
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
