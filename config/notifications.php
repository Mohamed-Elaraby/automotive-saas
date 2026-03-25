<?php

return [
    'admin' => [
        'transport' => env('ADMIN_NOTIFICATIONS_TRANSPORT', 'sse'),
        'sse_poll_seconds' => (int) env('ADMIN_NOTIFICATIONS_SSE_POLL_SECONDS', 10),

        'deduplication' => [
            'enabled' => env('ADMIN_NOTIFICATIONS_DEDUP_ENABLED', true),
            'window_minutes' => (int) env('ADMIN_NOTIFICATIONS_DEDUP_WINDOW_MINUTES', 10),
        ],

        'email' => [
            'enabled' => env('ADMIN_NOTIFICATIONS_EMAIL_ENABLED', false),
            'critical_only' => env('ADMIN_NOTIFICATIONS_EMAIL_CRITICAL_ONLY', true),
            'to' => array_values(array_filter(array_map('trim', explode(',', (string) env('ADMIN_NOTIFICATIONS_EMAIL_TO', ''))))),
            'error_events' => [
                'system_error',
                'payment_failed',
                'suspended',
                'trial_expired',
                'tenant_provisioning_failed',
                'domain_failed',
                'tenant_database_migration_failed',
                'queue_failed',
                'scheduled_job_failed',
                'backup_failed',
                'disk_usage_high',
                'mail_sending_failed',
                'webhook_verification_failed',
            ],
        ],

        'retention' => [
            'delete_demo_after_days' => (int) env('ADMIN_NOTIFICATIONS_DELETE_DEMO_AFTER_DAYS', 7),
            'delete_archived_after_days' => (int) env('ADMIN_NOTIFICATIONS_DELETE_ARCHIVED_AFTER_DAYS', 60),
            'delete_read_after_days' => (int) env('ADMIN_NOTIFICATIONS_DELETE_READ_AFTER_DAYS', 45),
            'delete_resolved_system_errors_after_days' => (int) env('ADMIN_NOTIFICATIONS_DELETE_RESOLVED_ERRORS_AFTER_DAYS', 60),
        ],
    ],
];
