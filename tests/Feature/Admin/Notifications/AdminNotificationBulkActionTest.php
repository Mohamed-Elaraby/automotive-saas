<?php

namespace Tests\Feature\Admin\Notifications;

use App\Models\AdminNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Admin\Notifications\Concerns\InteractsWithAdminNotificationsTable;
use Tests\TestCase;

class AdminNotificationBulkActionTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAdminNotificationsTable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureCentralAdminNotificationsTable();
    }

    public function test_bulk_action_requires_authenticated_central_admin_user(): void
    {
        $notification = AdminNotification::query()->create([
            'type' => 'billing',
            'title' => 'Bulk auth target',
            'message' => 'Auth check for bulk action.',
            'severity' => 'info',
            'source_type' => 'subscription',
            'source_id' => 3001,
            'route_name' => null,
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-bulk-auth',
            'user_id' => null,
            'user_email' => 'bulk-auth@example.com',
            'context_payload' => ['demo' => true],
            'is_read' => false,
            'read_at' => null,
            'is_archived' => false,
            'archived_at' => null,
            'notified_at' => now(),
        ]);

        $response = $this->post(route('admin.notifications.bulk-action'), [
            'action' => 'mark_read',
            'selected_ids' => [$notification->id],
        ]);

        $response->assertRedirect(route('admin.login'));
    }

    public function test_bulk_action_can_mark_selected_notifications_as_read(): void
    {
        $admin = User::factory()->create();

        $first = AdminNotification::query()->create([
            'type' => 'billing',
            'title' => 'Bulk read one',
            'message' => 'Should become read.',
            'severity' => 'warning',
            'source_type' => 'subscription',
            'source_id' => 3002,
            'route_name' => null,
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-bulk-1',
            'user_id' => null,
            'user_email' => 'bulk1@example.com',
            'context_payload' => ['demo' => true],
            'is_read' => false,
            'read_at' => null,
            'is_archived' => false,
            'archived_at' => null,
            'notified_at' => now(),
        ]);

        $second = AdminNotification::query()->create([
            'type' => 'billing',
            'title' => 'Bulk read two',
            'message' => 'Should also become read.',
            'severity' => 'warning',
            'source_type' => 'subscription',
            'source_id' => 3003,
            'route_name' => null,
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-bulk-2',
            'user_id' => null,
            'user_email' => 'bulk2@example.com',
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
            ->post(route('admin.notifications.bulk-action'), [
                'action' => 'mark_read',
                'selected_ids' => [$first->id, $second->id],
            ]);

        $response->assertRedirect(route('admin.notifications.index'));

        $first->refresh();
        $second->refresh();

        $this->assertTrue($first->is_read);
        $this->assertTrue($second->is_read);
        $this->assertNotNull($first->read_at);
        $this->assertNotNull($second->read_at);
    }

    public function test_bulk_action_can_archive_selected_notifications(): void
    {
        $admin = User::factory()->create();

        $first = AdminNotification::query()->create([
            'type' => 'billing',
            'title' => 'Bulk archive one',
            'message' => 'Should become archived.',
            'severity' => 'error',
            'source_type' => 'subscription',
            'source_id' => 3004,
            'route_name' => null,
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-archive-1',
            'user_id' => null,
            'user_email' => 'archive1@example.com',
            'context_payload' => ['demo' => true],
            'is_read' => false,
            'read_at' => null,
            'is_archived' => false,
            'archived_at' => null,
            'notified_at' => now(),
        ]);

        $second = AdminNotification::query()->create([
            'type' => 'billing',
            'title' => 'Bulk archive two',
            'message' => 'Should also become archived.',
            'severity' => 'error',
            'source_type' => 'subscription',
            'source_id' => 3005,
            'route_name' => null,
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-archive-2',
            'user_id' => null,
            'user_email' => 'archive2@example.com',
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
            ->post(route('admin.notifications.bulk-action'), [
                'action' => 'archive',
                'selected_ids' => [$first->id, $second->id],
            ]);

        $response->assertRedirect(route('admin.notifications.index'));

        $first->refresh();
        $second->refresh();

        $this->assertTrue($first->is_archived);
        $this->assertTrue($second->is_archived);
        $this->assertTrue($first->is_read);
        $this->assertTrue($second->is_read);
        $this->assertNotNull($first->archived_at);
        $this->assertNotNull($second->archived_at);
    }

    public function test_bulk_action_can_delete_selected_notifications(): void
    {
        $admin = User::factory()->create();

        $first = AdminNotification::query()->create([
            'type' => 'billing',
            'title' => 'Bulk delete one',
            'message' => 'Should be deleted.',
            'severity' => 'info',
            'source_type' => 'subscription',
            'source_id' => 3006,
            'route_name' => null,
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-delete-1',
            'user_id' => null,
            'user_email' => 'delete1@example.com',
            'context_payload' => ['demo' => true],
            'is_read' => false,
            'read_at' => null,
            'is_archived' => false,
            'archived_at' => null,
            'notified_at' => now(),
        ]);

        $second = AdminNotification::query()->create([
            'type' => 'billing',
            'title' => 'Bulk delete two',
            'message' => 'Should also be deleted.',
            'severity' => 'info',
            'source_type' => 'subscription',
            'source_id' => 3007,
            'route_name' => null,
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-delete-2',
            'user_id' => null,
            'user_email' => 'delete2@example.com',
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
            ->post(route('admin.notifications.bulk-action'), [
                'action' => 'delete',
                'selected_ids' => [$first->id, $second->id],
            ]);

        $response->assertRedirect(route('admin.notifications.index'));

        $this->assertDatabaseMissing('admin_notifications', [
            'id' => $first->id,
        ], $this->centralConnectionName());

        $this->assertDatabaseMissing('admin_notifications', [
            'id' => $second->id,
        ], $this->centralConnectionName());
    }
}
