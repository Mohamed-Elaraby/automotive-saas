<?php

namespace Tests\Feature\Admin\Notifications;

use App\Models\AdminNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Admin\Notifications\Concerns\InteractsWithAdminNotificationsTable;
use Tests\TestCase;

class AdminNotificationUnreadSummaryTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAdminNotificationsTable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureCentralAdminNotificationsTable();
    }

    public function test_unread_summary_requires_authenticated_central_admin_user(): void
    {
        $response = $this->get(route('admin.notifications.unread-summary'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_unread_summary_returns_only_unread_active_notifications(): void
    {
        $admin = User::factory()->create();

        $firstVisible = AdminNotification::query()->create([
            'type' => 'billing',
            'title' => 'Unread active billing notification',
            'message' => 'Should appear in summary.',
            'severity' => 'warning',
            'source_type' => 'subscription',
            'source_id' => 6001,
            'route_name' => 'admin.subscriptions.index',
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-summary-1',
            'user_id' => null,
            'user_email' => 'summary1@example.com',
            'context_payload' => ['demo' => true],
            'is_read' => false,
            'read_at' => null,
            'is_archived' => false,
            'archived_at' => null,
            'notified_at' => now(),
        ]);

        $secondVisible = AdminNotification::query()->create([
            'type' => 'system_error',
            'title' => 'Unread active system error',
            'message' => 'Should also appear in summary.',
            'severity' => 'error',
            'source_type' => 'system_error',
            'source_id' => 6002,
            'route_name' => 'admin.system-errors.index',
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-summary-2',
            'user_id' => null,
            'user_email' => 'summary2@example.com',
            'context_payload' => ['demo' => true],
            'is_read' => false,
            'read_at' => null,
            'is_archived' => false,
            'archived_at' => null,
            'notified_at' => now()->subMinute(),
        ]);

        AdminNotification::query()->create([
            'type' => 'billing',
            'title' => 'Read notification',
            'message' => 'Should not appear because it is already read.',
            'severity' => 'success',
            'source_type' => 'subscription',
            'source_id' => 6003,
            'route_name' => 'admin.subscriptions.index',
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-summary-3',
            'user_id' => null,
            'user_email' => 'summary3@example.com',
            'context_payload' => ['demo' => true],
            'is_read' => true,
            'read_at' => now(),
            'is_archived' => false,
            'archived_at' => null,
            'notified_at' => now()->subMinutes(2),
        ]);

        AdminNotification::query()->create([
            'type' => 'billing',
            'title' => 'Archived notification',
            'message' => 'Should not appear because it is archived.',
            'severity' => 'info',
            'source_type' => 'subscription',
            'source_id' => 6004,
            'route_name' => 'admin.subscriptions.index',
            'route_params' => [],
            'target_url' => null,
            'tenant_id' => 'tenant-summary-4',
            'user_id' => null,
            'user_email' => 'summary4@example.com',
            'context_payload' => ['demo' => true],
            'is_read' => false,
            'read_at' => null,
            'is_archived' => true,
            'archived_at' => now(),
            'notified_at' => now()->subMinutes(3),
        ]);

        $response = $this
            ->actingAs($admin, 'web')
            ->getJson(route('admin.notifications.unread-summary'));

        $response->assertOk();

        $response->assertJsonPath('count', 2);
        $response->assertJsonPath('index_url', route('admin.notifications.index'));

        $items = $response->json('items');

        $this->assertIsArray($items);
        $this->assertCount(2, $items);

        $this->assertSame($firstVisible->id, $items[0]['id']);
        $this->assertSame('Unread active billing notification', $items[0]['title']);
        $this->assertSame(route('admin.notifications.show', $firstVisible->id), $items[0]['show_url']);
        $this->assertSame(route('admin.notifications.mark-read', $firstVisible->id), $items[0]['mark_read_url']);

        $this->assertSame($secondVisible->id, $items[1]['id']);
        $this->assertSame('Unread active system error', $items[1]['title']);
    }

    public function test_unread_summary_limits_items_to_eight_but_keeps_total_count(): void
    {
        $admin = User::factory()->create();

        for ($i = 1; $i <= 10; $i++) {
            AdminNotification::query()->create([
                'type' => 'billing',
                'title' => "Unread notification {$i}",
                'message' => "Unread summary item {$i}.",
                'severity' => $i % 2 === 0 ? 'warning' : 'info',
                'source_type' => 'subscription',
                'source_id' => 7000 + $i,
                'route_name' => 'admin.subscriptions.index',
                'route_params' => [],
                'target_url' => null,
                'tenant_id' => "tenant-limit-{$i}",
                'user_id' => null,
                'user_email' => "limit{$i}@example.com",
                'context_payload' => ['demo' => true, 'position' => $i],
                'is_read' => false,
                'read_at' => null,
                'is_archived' => false,
                'archived_at' => null,
                'notified_at' => now()->subMinutes(10 - $i),
            ]);
        }

        $response = $this
            ->actingAs($admin, 'web')
            ->getJson(route('admin.notifications.unread-summary'));

        $response->assertOk();

        $response->assertJsonPath('count', 10);

        $items = $response->json('items');

        $this->assertIsArray($items);
        $this->assertCount(8, $items);

        $this->assertSame('Unread notification 10', $items[0]['title']);
        $this->assertSame('Unread notification 9', $items[1]['title']);
        $this->assertSame('Unread notification 3', $items[7]['title']);
    }
}
