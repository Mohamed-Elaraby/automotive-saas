<?php

namespace Tests\Feature\Admin\Notifications;

use App\Models\AdminNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Admin\Notifications\Concerns\InteractsWithAdminNotificationsTable;
use Tests\TestCase;

class AdminNotificationArchiveTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAdminNotificationsTable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureCentralAdminNotificationsTable();
    }

    public function test_archive_requires_authenticated_central_admin_user(): void
    {
        $notification = AdminNotification::query()->create([
            'type' => 'billing',
            'title' => 'Archive me',
            'message' => 'Archive action auth test.',
            'severity' => 'warning',
            'source_type' => 'subscription',
            'source_id' => 2001,
            'route_name' => null,
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-archive-auth',
            'user_id' => null,
            'user_email' => 'archive-auth@example.com',
            'context_payload' => ['demo' => true],
            'is_read' => false,
            'read_at' => null,
            'is_archived' => false,
            'archived_at' => null,
            'notified_at' => now(),
        ]);

        $response = $this->post(route('admin.notifications.archive', $notification));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_archive_marks_notification_as_archived_and_read(): void
    {
        $admin = User::factory()->create();

        $notification = AdminNotification::query()->create([
            'type' => 'billing',
            'title' => 'Archive target',
            'message' => 'Notification should become archived and read.',
            'severity' => 'error',
            'source_type' => 'subscription',
            'source_id' => 2002,
            'route_name' => null,
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-archive',
            'user_id' => null,
            'user_email' => 'archive@example.com',
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
            ->post(route('admin.notifications.archive', $notification));

        $response->assertRedirect(route('admin.notifications.index'));

        $notification->refresh();

        $this->assertTrue($notification->is_archived);
        $this->assertTrue($notification->is_read);
        $this->assertNotNull($notification->archived_at);
        $this->assertNotNull($notification->read_at);

        $this->assertDatabaseHas('admin_notifications', [
            'id' => $notification->id,
            'is_archived' => 1,
            'is_read' => 1,
        ], $this->centralConnectionName());
    }
}
