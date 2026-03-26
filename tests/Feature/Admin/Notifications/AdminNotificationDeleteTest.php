<?php

namespace Tests\Feature\Admin\Notifications;

use App\Models\AdminNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Admin\Notifications\Concerns\InteractsWithAdminNotificationsTable;
use Tests\TestCase;

class AdminNotificationDeleteTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAdminNotificationsTable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureCentralAdminNotificationsTable();
    }

    public function test_destroy_requires_authenticated_central_admin_user(): void
    {
        $notification = AdminNotification::query()->create([
            'type' => 'system_error',
            'title' => 'Delete auth target',
            'message' => 'Delete auth check.',
            'severity' => 'error',
            'source_type' => 'system_error',
            'source_id' => 4001,
            'route_name' => null,
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-delete-auth',
            'user_id' => null,
            'user_email' => 'delete-auth@example.com',
            'context_payload' => ['demo' => true],
            'is_read' => false,
            'read_at' => null,
            'is_archived' => false,
            'archived_at' => null,
            'notified_at' => now(),
        ]);

        $response = $this->post(route('admin.notifications.destroy', $notification));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_destroy_deletes_single_notification(): void
    {
        $admin = User::factory()->create();

        $notification = AdminNotification::query()->create([
            'type' => 'system_error',
            'title' => 'Delete target',
            'message' => 'This row should be deleted.',
            'severity' => 'error',
            'source_type' => 'system_error',
            'source_id' => 4002,
            'route_name' => null,
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-delete',
            'user_id' => null,
            'user_email' => 'delete@example.com',
            'context_payload' => ['demo' => true],
            'is_read' => false,
            'read_at' => null,
            'is_archived' => false,
            'archived_at' => null,
            'notified_at' => now(),
        ]);

        $response = $this
            ->actingAs($admin, 'web')
            ->from(route('admin.notifications.index'))
            ->post(route('admin.notifications.destroy', $notification));

        $response->assertRedirect(route('admin.notifications.index'));

        $this->assertDatabaseMissing('admin_notifications', [
            'id' => $notification->id,
        ], $this->centralConnectionName());
    }
}
