<?php

namespace Tests\Feature\Admin\Notifications;

use App\Models\AdminNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Admin\Notifications\Concerns\InteractsWithAdminNotificationsTable;
use Tests\TestCase;

class AdminNotificationShowTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAdminNotificationsTable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureCentralAdminNotificationsTable();
    }

    public function test_show_requires_authenticated_central_admin_user(): void
    {
        $notification = AdminNotification::query()->create([
            'type' => 'system_error',
            'title' => 'System Error Detected',
            'message' => 'An exception was captured.',
            'severity' => 'error',
            'source_type' => 'system_error',
            'source_id' => 501,
            'route_name' => null,
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-x',
            'user_id' => null,
            'user_email' => 'ops@example.com',
            'context_payload' => ['code' => 'E500'],
            'is_read' => false,
            'read_at' => null,
            'is_archived' => false,
            'archived_at' => null,
            'notified_at' => now(),
        ]);

        $response = $this->get(route('admin.notifications.show', $notification));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_show_renders_details_and_marks_notification_as_read_automatically(): void
    {
        $admin = User::factory()->create();

        $notification = AdminNotification::query()->create([
            'type' => 'system_error',
            'title' => 'System Error Detected',
            'message' => 'A simulated server exception was captured for UI testing.',
            'severity' => 'error',
            'source_type' => 'system_error',
            'source_id' => 601,
            'route_name' => 'admin.system-errors.index',
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'demo-tenant-07',
            'user_id' => null,
            'user_email' => 'billing@example.com',
            'context_payload' => [
                'demo' => true,
                'event' => 'server.exception',
            ],
            'is_read' => false,
            'read_at' => null,
            'is_archived' => false,
            'archived_at' => null,
            'notified_at' => now(),
        ]);

        $response = $this
            ->actingAs($admin, 'web')
            ->get(route('admin.notifications.show', $notification));

        $response->assertOk();

        $response->assertSee('Notification Details');
        $response->assertSee('System Error Detected');
        $response->assertSee('A simulated server exception was captured for UI testing.');
        $response->assertSee('demo-tenant-07');
        $response->assertSee('billing@example.com');
        $response->assertSee('No', false); // Archived = No
        $response->assertSee('Context Payload');

        $notification->refresh();

        $this->assertTrue($notification->is_read);
        $this->assertNotNull($notification->read_at);

        $this->assertDatabaseHas('admin_notifications', [
            'id' => $notification->id,
            'is_read' => 1,
            'is_archived' => 0,
        ], $this->centralConnectionName());
    }
}
