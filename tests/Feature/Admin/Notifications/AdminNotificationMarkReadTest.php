<?php

namespace Tests\Feature\Admin\Notifications;

use App\Models\AdminNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Admin\Notifications\Concerns\InteractsWithAdminNotificationsTable;
use Tests\TestCase;

class AdminNotificationMarkReadTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAdminNotificationsTable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureCentralAdminNotificationsTable();
    }

    public function test_mark_read_requires_authenticated_central_admin_user(): void
    {
        $notification = AdminNotification::query()->create([
            'type' => 'billing',
            'title' => 'Mark read auth target',
            'message' => 'Auth check for mark read.',
            'severity' => 'info',
            'source_type' => 'subscription',
            'source_id' => 5001,
            'route_name' => null,
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-mark-read-auth',
            'user_id' => null,
            'user_email' => 'mark-read-auth@example.com',
            'context_payload' => ['demo' => true],
            'is_read' => false,
            'read_at' => null,
            'is_archived' => false,
            'archived_at' => null,
            'notified_at' => now(),
        ]);

        $response = $this->post(route('admin.notifications.mark-read', $notification));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_mark_read_marks_notification_as_read_and_redirects_back(): void
    {
        $admin = User::factory()->create();

        $notification = AdminNotification::query()->create([
            'type' => 'billing',
            'title' => 'Mark read target',
            'message' => 'This notification should become read.',
            'severity' => 'warning',
            'source_type' => 'subscription',
            'source_id' => 5002,
            'route_name' => null,
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-mark-read',
            'user_id' => null,
            'user_email' => 'mark-read@example.com',
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
            ->post(route('admin.notifications.mark-read', $notification));

        $response->assertRedirect(route('admin.notifications.index'));

        $notification->refresh();

        $this->assertTrue($notification->is_read);
        $this->assertNotNull($notification->read_at);

        $this->assertDatabaseHas('admin_notifications', [
            'id' => $notification->id,
            'is_read' => 1,
            'is_archived' => 0,
        ], $this->centralConnectionName());
    }

    public function test_mark_read_returns_json_payload_when_requested_as_json(): void
    {
        $admin = User::factory()->create();

        $target = AdminNotification::query()->create([
            'type' => 'billing',
            'title' => 'JSON mark read target',
            'message' => 'Should become read through JSON request.',
            'severity' => 'info',
            'source_type' => 'subscription',
            'source_id' => 5003,
            'route_name' => null,
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-json-target',
            'user_id' => null,
            'user_email' => 'json-target@example.com',
            'context_payload' => ['demo' => true],
            'is_read' => false,
            'read_at' => null,
            'is_archived' => false,
            'archived_at' => null,
            'notified_at' => now(),
        ]);

        $stillUnread = AdminNotification::query()->create([
            'type' => 'system_error',
            'title' => 'Still unread notification',
            'message' => 'Should remain unread after marking the other item.',
            'severity' => 'error',
            'source_type' => 'system_error',
            'source_id' => 5004,
            'route_name' => null,
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-json-unread',
            'user_id' => null,
            'user_email' => 'json-unread@example.com',
            'context_payload' => ['demo' => true],
            'is_read' => false,
            'read_at' => null,
            'is_archived' => false,
            'archived_at' => null,
            'notified_at' => now()->subMinute(),
        ]);

        $response = $this
            ->actingAs($admin, 'web')
            ->postJson(route('admin.notifications.mark-read', $target));

        $response->assertOk();

        $response->assertJson([
            'ok' => true,
            'id' => $target->id,
            'count' => 1,
        ]);

        $target->refresh();
        $stillUnread->refresh();

        $this->assertTrue($target->is_read);
        $this->assertNotNull($target->read_at);
        $this->assertFalse($stillUnread->is_read);
    }
}
