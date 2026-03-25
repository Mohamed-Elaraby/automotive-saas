<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Schema;

class AdminNotificationSchemaService
{
    public function connectionName(): string
    {
        return (string) (config('tenancy.database.central_connection') ?? config('database.default'));
    }

    public function tableExists(): bool
    {
        return Schema::connection($this->connectionName())->hasTable('admin_notifications');
    }

    public function columns(): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        return Schema::connection($this->connectionName())->getColumnListing('admin_notifications');
    }

    public function hasColumn(string $column): bool
    {
        return in_array($column, $this->columns(), true);
    }

    public function hasRequiredColumns(): bool
    {
        $required = [
            'type',
            'title',
            'severity',
            'is_read',
            'is_archived',
            'notified_at',
        ];

        $columns = $this->columns();

        foreach ($required as $requiredColumn) {
            if (! in_array($requiredColumn, $columns, true)) {
                return false;
            }
        }

        return true;
    }

    public function missingRequiredColumns(): array
    {
        $required = [
            'type',
            'title',
            'message',
            'severity',
            'route_name',
            'route_params',
            'target_url',
            'tenant_id',
            'user_id',
            'user_email',
            'context_payload',
            'is_read',
            'read_at',
            'is_archived',
            'archived_at',
            'notified_at',
        ];

        $columns = $this->columns();

        return collect($required)
            ->reject(fn (string $column) => in_array($column, $columns, true))
            ->values()
            ->all();
    }
}
