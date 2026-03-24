<?php

namespace App\Services\Notifications;

use App\Data\AdminNotificationData;
use App\Models\AdminNotification;
use Illuminate\Support\Facades\Schema;

class AdminNotificationService
{
    public function create(AdminNotificationData $data): ?AdminNotification
    {
        if (! $this->tableExists()) {
            return null;
        }

        return AdminNotification::query()->create(
            $data->toModelAttributes()
        );
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

public function createBillingNotification(
    string $title,
    ?string $message = null,
    string $severity = 'warning',
    ?string $tenantId = null,
    ?int $userId = null,
    ?string $userEmail = null,
    array $contextPayload = [],
    ?string $routeName = null,
    array $routeParams = [],
    ?string $targetUrl = null,
    ): ?AdminNotification {
    return $this->create(new AdminNotificationData(
        type: 'billing',
            title: $title,
            message: $message,
            severity: $severity,
            sourceType: 'billing',
            sourceId: null,
            routeName: $routeName,
            routeParams: $routeParams,
            targetUrl: $targetUrl,
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
}
