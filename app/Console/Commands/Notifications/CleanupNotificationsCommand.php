<?php

namespace App\Console\Commands\Notifications;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupNotificationsCommand extends Command
{
    protected $signature = 'notifications:cleanup';

    protected $description = 'Cleanup old demo notifications, archived/read notifications, and resolved system errors';

    public function handle(): int
    {
        $connection = (string) (config('tenancy.database.central_connection') ?? config('database.default'));

        $demoDays = (int) config('notifications.admin.retention.delete_demo_after_days', 7);
        $archivedDays = (int) config('notifications.admin.retention.delete_archived_after_days', 60);
        $readDays = (int) config('notifications.admin.retention.delete_read_after_days', 45);
        $resolvedErrorDays = (int) config('notifications.admin.retention.delete_resolved_system_errors_after_days', 60);

        $deletedDemo = DB::connection($connection)
            ->table('admin_notifications')
            ->whereRaw("JSON_EXTRACT(context_payload, '$.demo') = true")
            ->where('created_at', '<=', now()->subDays($demoDays))
            ->delete();

        $deletedArchived = DB::connection($connection)
            ->table('admin_notifications')
            ->where('is_archived', true)
            ->where('updated_at', '<=', now()->subDays($archivedDays))
            ->delete();

        $deletedRead = DB::connection($connection)
            ->table('admin_notifications')
            ->where('is_read', true)
            ->where('is_archived', false)
            ->where('updated_at', '<=', now()->subDays($readDays))
            ->delete();

        $deletedResolvedErrors = DB::connection($connection)
            ->table('system_error_logs')
            ->where('is_resolved', true)
            ->where('updated_at', '<=', now()->subDays($resolvedErrorDays))
            ->delete();

        $this->info("Deleted demo notifications: {$deletedDemo}");
        $this->info("Deleted archived notifications: {$deletedArchived}");
        $this->info("Deleted read notifications: {$deletedRead}");
        $this->info("Deleted resolved system errors: {$deletedResolvedErrors}");

        return self::SUCCESS;
    }
}
