<?php

namespace Tests\Feature\Admin\Notifications;

use App\Models\AdminNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Admin\Notifications\Concerns\InteractsWithAdminNotificationsTable;
use Tests\TestCase;

class AdminNotificationsIndexTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAdminNotificationsTable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureCentralAdminNotificationsTable();
    }

    public function test_index_requires_authenticated_central_admin_user(): void
    {
        $response = $this->get(route('admin.notifications.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_index_renders_notifications_screen_with_seeded_notification_data(): void
    {
        $admin = User::factory()->create();

        $notification = AdminNotification::query()->create([
            'type' => 'billing',
            'title' => 'Invoice payment failed',
            'message' => 'Stripe reported a failed payment for tenant demo-tenant.',
            'severity' => 'error',
            'source_type' => 'subscription',
            'source_id' => 101,
            'route_name' => 'admin.subscriptions.index',
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'demo-tenant',
            'user_id' => null,
            'user_email' => 'billing@example.com',
            'context_payload' => ['demo' => true, 'event' => 'invoice.payment_failed'],
            'is_read' => false,
            'read_at' => null,
            'is_archived' => false,
            'archived_at' => null,
            'notified_at' => now(),
        ]);

        $response = $this
            ->actingAs($admin, 'web')
            ->get(route('admin.notifications.index'));

        $response->assertOk();

        $response->assertSee('Notifications');
        $response->assertSee('Central notification center for system-wide in-app alerts.');
        $response->assertSee('Delete Current View');
        $response->assertSee('Bulk Action');
        $response->assertSee('Mark Read Selected');
        $response->assertSee('Archive Selected');
        $response->assertSee('Delete Selected');

        $response->assertSee('Invoice payment failed');
        $response->assertSee('demo-tenant');
        $response->assertSee('Unread');
        $response->assertSee('Active');

        $this->assertDatabaseHas('admin_notifications', [
            'id' => $notification->id,
            'title' => 'Invoice payment failed',
            'user_email' => 'billing@example.com',
            'is_read' => 0,
            'is_archived' => 0,
        ], $this->centralConnectionName());
    }

    public function test_index_can_filter_unread_active_notifications(): void
    {
        $admin = User::factory()->create();

        AdminNotification::query()->create([
            'type' => 'billing',
            'title' => 'Unread active notification',
            'message' => 'Should remain visible under unread + active filter.',
            'severity' => 'warning',
            'source_type' => 'subscription',
            'source_id' => 102,
            'route_name' => 'admin.subscriptions.index',
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-a',
            'user_id' => null,
            'user_email' => 'tenant-a@example.com',
            'context_payload' => ['demo' => true],
            'is_read' => false,
            'read_at' => null,
            'is_archived' => false,
            'archived_at' => null,
            'notified_at' => now()->subMinute(),
        ]);

        AdminNotification::query()->create([
            'type' => 'billing',
            'title' => 'Read archived notification',
            'message' => 'Should be filtered out.',
            'severity' => 'success',
            'source_type' => 'subscription',
            'source_id' => 103,
            'route_name' => 'admin.subscriptions.index',
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-b',
            'user_id' => null,
            'user_email' => 'tenant-b@example.com',
            'context_payload' => ['demo' => true],
            'is_read' => true,
            'read_at' => now()->subMinute(),
            'is_archived' => true,
            'archived_at' => now()->subMinute(),
            'notified_at' => now()->subMinutes(2),
        ]);

        $response = $this
            ->actingAs($admin, 'web')
            ->get(route('admin.notifications.index', [
                'is_read' => '0',
                'is_archived' => '0',
            ]));

        $response->assertOk();
        $response->assertSee('Unread active notification');
        $response->assertDontSee('Read archived notification');
    }
}
