<?php

namespace App\Services\Notifications;

use App\Data\AdminNotificationData;
use App\Mail\CriticalAdminNotificationMail;
use App\Models\AdminNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class AdminNotificationService
{
    public function create(AdminNotificationData $data): ?AdminNotification
    {
        if (! $this->tableExists()) {
            return null;
        }

        if ($this->shouldDeduplicate($data)) {
            return $this->findExistingDuplicate($data);
        }

        $notification = AdminNotification::query()->create(
            $data->toModelAttributes()
        );

        $this->sendCriticalEmailIfNeeded($notification);

        return $notification;
    }

    public function createSystemErrorNotification(
        string $message,
        string $exceptionClass,
        array $contextPayload = [],
        ?string $tenantId = null,
        ?int $userId = null,
        ?string $userEmail = null,
    ): ?AdminNotification {
        return $this->create(new AdminNotificationData(
            type: 'system_error',
            title: 'System Error Detected',
            message: $message,
            severity: 'error',
            sourceType: $exceptionClass,
            sourceId: null,
            routeName: 'admin.system-errors.index',
            routeParams: [],
            targetUrl: null,
            tenantId: $tenantId,
            userId: $userId,
            userEmail: $userEmail,
            contextPayload: $contextPayload,
        ));
    }

protected function tableExists(): bool
{
    $connection = (string) (config('tenancy.database.central_connection') ?? config('database.default'));

    return Schema::connection($connection)->hasTable('admin_notifications');
}

protected function shouldDeduplicate(AdminNotificationData $data): bool
{
    return (bool) config('notifications.admin.deduplication.enabled', true)
        && $this->findExistingDuplicate($data) !== null;
}

protected function findExistingDuplicate(AdminNotificationData $data): ?AdminNotification
{
    $windowMinutes = max(1, (int) config('notifications.admin.deduplication.window_minutes', 10));
    $event = (string) ($data->contextPayload['event'] ?? '');

    $query = AdminNotification::query()
        ->where('type', $data->type)
        ->where('severity', $data->severity)
        ->where('title', $data->title)
        ->where('source_type', $data->sourceType)
        ->where('source_id', $data->sourceId)
        ->where('tenant_id', $data->tenantId)
        ->where('created_at', '>=', now()->subMinutes($windowMinutes));

    if ($event !== '') {
        $query->whereJsonContains('context_payload->event', $event);
    }

    return $query->latest('id')->first();
}

protected function sendCriticalEmailIfNeeded(AdminNotification $notification): void
{
    if (! (bool) config('notifications.admin.email.enabled', false)) {
        return;
    }

    $recipients = config('notifications.admin.email.to', []);
    if (empty($recipients)) {
        return;
    }

    $event = (string) ($notification->context_payload['event'] ?? $notification->type);
    $criticalOnly = (bool) config('notifications.admin.email.critical_only', true);
    $errorEvents = (array) config('notifications.admin.email.error_events', []);

    $shouldSend = ! $criticalOnly
        || $notification->severity === 'error'
        || in_array($event, $errorEvents, true);

    if (! $shouldSend) {
        return;
    }

    try {
        Mail::to($recipients)->send(new CriticalAdminNotificationMail($notification));
    } catch (\Throwable $e) {
        report($e);
    }
}
}
