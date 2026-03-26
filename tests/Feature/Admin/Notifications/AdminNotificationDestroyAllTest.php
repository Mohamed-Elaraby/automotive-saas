<?php

namespace Tests\Feature\Admin\Notifications;

use App\Models\AdminNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Admin\Notifications\Concerns\InteractsWithAdminNotificationsTable;
use Tests\TestCase;

class AdminNotificationDestroyAllTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAdminNotificationsTable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureCentralAdminNotificationsTable();
    }

    public function test_destroy_all_requires_authenticated_central_admin_user(): void
    {
        $response = $this->post(route('admin.notifications.destroy-all'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_destroy_all_deletes_only_notifications_in_current_filtered_view(): void
    {
        $admin = User::factory()->create();

        $matchingFirst = AdminNotification::query()->create([
            'type' => 'billing',
            'title' => 'Billing unread active one',
            'message' => 'Should be deleted by current view action.',
            'severity' => 'warning',
            'source_type' => 'subscription',
            'source_id' => 8001,
            'route_name' => null,
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-destroy-all-1',
            'user_id' => null,
            'user_email' => 'destroy-all-1@example.com',
            'context_payload' => ['demo' => true],
            'is_read' => false,
            'read_at' => null,
            'is_archived' => false,
            'archived_at' => null,
            'notified_at' => now(),
        ]);

        $matchingSecond = AdminNotification::query()->create([
            'type' => 'billing',
            'title' => 'Billing unread active two',
            'message' => 'Should also be deleted by current view action.',
            'severity' => 'warning',
            'source_type' => 'subscription',
            'source_id' => 8002,
            'route_name' => null,
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-destroy-all-2',
            'user_id' => null,
            'user_email' => 'destroy-all-2@example.com',
            'context_payload' => ['demo' => true],
            'is_read' => false,
            'read_at' => null,
            'is_archived' => false,
            'archived_at' => null,
            'notified_at' => now()->subMinute(),
        ]);

        $differentType = AdminNotification::query()->create([
            'type' => 'system_error',
            'title' => 'System error should remain',
            'message' => 'Different type, should not be deleted.',
            'severity' => 'warning',
            'source_type' => 'system_error',
            'source_id' => 8003,
            'route_name' => null,
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-destroy-all-3',
            'user_id' => null,
            'user_email' => 'destroy-all-3@example.com',
            'context_payload' => ['demo' => true],
            'is_read' => false,
            'read_at' => null,
            'is_archived' => false,
            'archived_at' => null,
            'notified_at' => now()->subMinutes(2),
        ]);

        $differentReadState = AdminNotification::query()->create([
            'type' => 'billing',
            'title' => 'Billing read should remain',
            'message' => 'Read item should not match current filtered view.',
            'severity' => 'warning',
            'source_type' => 'subscription',
            'source_id' => 8004,
            'route_name' => null,
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-destroy-all-4',
            'user_id' => null,
            'user_email' => 'destroy-all-4@example.com',
            'context_payload' => ['demo' => true],
            'is_read' => true,
            'read_at' => now(),
            'is_archived' => false,
            'archived_at' => null,
            'notified_at' => now()->subMinutes(3),
        ]);

        $differentArchivedState = AdminNotification::query()->create([
            'type' => 'billing',
            'title' => 'Billing archived should remain',
            'message' => 'Archived item should not match current filtered view.',
            'severity' => 'warning',
            'source_type' => 'subscription',
            'source_id' => 8005,
            'route_name' => null,
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-destroy-all-5',
            'user_id' => null,
            'user_email' => 'destroy-all-5@example.com',
            'context_payload' => ['demo' => true],
            'is_read' => false,
            'read_at' => null,
            'is_archived' => true,
            'archived_at' => now(),
            'notified_at' => now()->subMinutes(4),
        ]);

        $response = $this
            ->actingAs($admin, 'web')
            ->from(route('admin.notifications.index', [
                'tab' => 'billing',
                'severity' => 'warning',
                'is_read' => '0',
                'is_archived' => '0',
            ]))
            ->post(route('admin.notifications.destroy-all'), [
                'tab' => 'billing',
                'severity' => 'warning',
                'is_read' => '0',
                'is_archived' => '0',
            ]);

        $response->assertRedirect(route('admin.notifications.index'));

        $this->assertDatabaseMissing('admin_notifications', [
            'id' => $matchingFirst->id,
        ], $this->centralConnectionName());

        $this->assertDatabaseMissing('admin_notifications', [
            'id' => $matchingSecond->id,
        ], $this->centralConnectionName());

        $this->assertDatabaseHas('admin_notifications', [
            'id' => $differentType->id,
        ], $this->centralConnectionName());

        $this->assertDatabaseHas('admin_notifications', [
            'id' => $differentReadState->id,
        ], $this->centralConnectionName());

        $this->assertDatabaseHas('admin_notifications', [
            'id' => $differentArchivedState->id,
        ], $this->centralConnectionName());
    }

    public function test_destroy_all_can_delete_everything_in_unfiltered_current_view(): void
    {
        $admin = User::factory()->create();

        $first = AdminNotification::query()->create([
            'type' => 'billing',
            'title' => 'Delete all one',
            'message' => 'Should be deleted in unfiltered current view.',
            'severity' => 'info',
            'source_type' => 'subscription',
            'source_id' => 8101,
            'route_name' => null,
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-delete-all-1',
            'user_id' => null,
            'user_email' => 'delete-all-1@example.com',
            'context_payload' => ['demo' => true],
            'is_read' => false,
            'read_at' => null,
            'is_archived' => false,
            'archived_at' => null,
            'notified_at' => now(),
        ]);

        $second = AdminNotification::query()->create([
            'type' => 'system_error',
            'title' => 'Delete all two',
            'message' => 'Should also be deleted in unfiltered current view.',
            'severity' => 'error',
            'source_type' => 'system_error',
            'source_id' => 8102,
            'route_name' => null,
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-delete-all-2',
            'user_id' => null,
            'user_email' => 'delete-all-2@example.com',
            'context_payload' => ['demo' => true],
            'is_read' => true,
            'read_at' => now(),
            'is_archived' => true,
            'archived_at' => now(),
            'notified_at' => now()->subMinute(),
        ]);

        $response = $this
            ->actingAs($admin, 'web')
            ->from(route('admin.notifications.index'))
            ->post(route('admin.notifications.destroy-all'));

        $response->assertRedirect(route('admin.notifications.index'));

        $this->assertDatabaseMissing('admin_notifications', [
            'id' => $first->id,
        ], $this->centralConnectionName());

        $this->assertDatabaseMissing('admin_notifications', [
            'id' => $second->id,
        ], $this->centralConnectionName());
    }
}
