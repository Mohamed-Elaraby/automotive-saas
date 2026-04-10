<?php

namespace Tests\Feature\Admin\Tenants;

use App\Http\Middleware\VerifyCsrfToken;
use App\Jobs\Admin\SyncTenantProductSubscriptionFromStripeJob;
use App\Models\Admin;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Services\Billing\AdminTenantProductSubscriptionStripeSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class AdminTenantProductSubscriptionStripeSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_admin_can_sync_product_subscription_from_stripe(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Central Admin',
            'email' => 'admin-tps-sync-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        $tenant = Tenant::query()->create([
            'id' => 'tenant-product-sub-sync-' . uniqid(),
            'data' => ['company_name' => 'Sync Tenant'],
        ]);

        $product = Product::query()->create([
            'code' => 'inventory_sync',
            'name' => 'Inventory Sync',
            'slug' => 'inventory-sync',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $plan = Plan::query()->create([
            'product_id' => $product->id,
            'name' => 'Inventory Sync Plan',
            'slug' => 'inventory-sync-plan-' . uniqid(),
            'price' => 199,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        $subscription = TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => 'past_due',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_sync',
            'gateway_subscription_id' => 'sub_sync',
        ]);

        $syncService = Mockery::mock(AdminTenantProductSubscriptionStripeSyncService::class);
        $syncService->shouldReceive('sync')
            ->once()
            ->with(Mockery::on(fn ($model) => (int) $model->id === (int) $subscription->id))
            ->andReturnUsing(function () use ($subscription) {
                $subscription->update([
                    'status' => 'active',
                    'gateway_price_id' => 'price_sync',
                    'last_synced_from_stripe_at' => now(),
                    'last_sync_status' => 'success',
                    'last_sync_error' => null,
                ]);

                return $subscription->fresh();
            });

        $this->app->instance(AdminTenantProductSubscriptionStripeSyncService::class, $syncService);

        $response = $this
            ->actingAs($admin, 'admin')
            ->post(route('admin.tenants.product-subscriptions.sync-stripe', $subscription->id));

        $response
            ->assertRedirect(route('admin.tenants.product-subscriptions.show', $subscription->id))
            ->assertSessionHas('success', 'Product subscription data was synced successfully from Stripe.');

        $this->assertDatabaseHas('tenant_product_subscriptions', [
            'id' => $subscription->id,
            'last_sync_status' => 'success',
            'last_sync_error' => null,
        ]);

        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'tenant.product_subscription.synced_from_stripe',
            'subject_type' => 'tenant_product_subscription',
            'subject_id' => (string) $subscription->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_admin_can_bulk_sync_selected_product_subscriptions(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Central Admin',
            'email' => 'admin-tps-bulk-selected-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        $tenant = Tenant::query()->create([
            'id' => 'tenant-product-sub-bulk-selected-' . uniqid(),
            'data' => ['company_name' => 'Bulk Selected Tenant'],
        ]);

        $product = Product::query()->create([
            'code' => 'bulk_selected_suite',
            'name' => 'Bulk Selected Suite',
            'slug' => 'bulk-selected-suite',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $plan = Plan::query()->create([
            'product_id' => $product->id,
            'name' => 'Bulk Selected Plan',
            'slug' => 'bulk-selected-plan-' . uniqid(),
            'price' => 149,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        $syncable = TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => 'past_due',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_bulk_selected',
            'gateway_subscription_id' => 'sub_bulk_selected',
        ]);

        $skipped = TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'legacy_subscription_id' => null,
            'status' => 'active',
            'gateway' => null,
        ]);

        $syncService = Mockery::mock(AdminTenantProductSubscriptionStripeSyncService::class);
        $syncService->shouldReceive('sync')
            ->once()
            ->with(Mockery::on(fn ($model) => (int) $model->id === (int) $syncable->id))
            ->andReturnUsing(function () use ($syncable) {
                $syncable->update([
                    'last_synced_from_stripe_at' => now(),
                    'last_sync_status' => 'success',
                    'last_sync_error' => null,
                ]);

                return $syncable->fresh();
            });

        $this->app->instance(AdminTenantProductSubscriptionStripeSyncService::class, $syncService);

        $response = $this
            ->actingAs($admin, 'admin')
            ->post(route('admin.tenants.product-subscriptions.bulk-sync-stripe'), [
                'bulk_sync_action' => 'selected',
                'selected_ids' => [$syncable->id, $skipped->id],
            ]);

        $response
            ->assertRedirect(route('admin.tenants.product-subscriptions.index'))
            ->assertSessionHas('success', 'Bulk Stripe sync finished. Succeeded: 1, failed: 0, skipped: 1.');

        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'tenant.product_subscription.bulk_synced_from_stripe',
        ]);
    }

    public function test_admin_can_queue_retry_failed_only_for_filtered_scope(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Central Admin',
            'email' => 'admin-tps-bulk-failed-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        $tenant = Tenant::query()->create([
            'id' => 'tenant-product-sub-bulk-failed-' . uniqid(),
            'data' => ['company_name' => 'Bulk Failed Tenant'],
        ]);

        $product = Product::query()->create([
            'code' => 'bulk_failed_suite',
            'name' => 'Bulk Failed Suite',
            'slug' => 'bulk-failed-suite',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $plan = Plan::query()->create([
            'product_id' => $product->id,
            'name' => 'Bulk Failed Plan',
            'slug' => 'bulk-failed-plan-' . uniqid(),
            'price' => 249,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        $failed = TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => 'past_due',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_failed_retry',
            'gateway_subscription_id' => 'sub_failed_retry',
            'last_sync_status' => 'failed',
            'last_sync_error' => 'Temporary failure.',
        ]);

        $alreadyOk = TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_success_retry',
            'gateway_subscription_id' => 'sub_success_retry',
            'last_sync_status' => 'success',
        ]);

        Queue::fake();

        $response = $this
            ->actingAs($admin, 'admin')
            ->post(route('admin.tenants.product-subscriptions.bulk-sync-stripe'), [
                'bulk_sync_action' => 'failed_only',
                'tenant_id' => $tenant->id,
                'product_id' => $product->id,
            ]);

        $response
            ->assertRedirect(route('admin.tenants.product-subscriptions.index', [
                'tenant_id' => $tenant->id,
                'product_id' => $product->id,
            ]))
            ->assertSessionHas('success', 'Bulk Stripe sync queued. Queued: 1, skipped: 0.');

        Queue::assertPushed(SyncTenantProductSubscriptionFromStripeJob::class, function ($job) use ($failed): bool {
            return (int) $job->subscriptionId === (int) $failed->id;
        });

        Queue::assertNotPushed(SyncTenantProductSubscriptionFromStripeJob::class, function ($job) use ($alreadyOk): bool {
            return (int) $job->subscriptionId === (int) $alreadyOk->id;
        });
    }

    public function test_admin_can_queue_sync_all_filtered_product_subscriptions(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Central Admin',
            'email' => 'admin-tps-bulk-filtered-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        $tenant = Tenant::query()->create([
            'id' => 'tenant-product-sub-bulk-filtered-' . uniqid(),
            'data' => ['company_name' => 'Bulk Filtered Tenant'],
        ]);

        $product = Product::query()->create([
            'code' => 'bulk_filtered_suite',
            'name' => 'Bulk Filtered Suite',
            'slug' => 'bulk-filtered-suite',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $plan = Plan::query()->create([
            'product_id' => $product->id,
            'name' => 'Bulk Filtered Plan',
            'slug' => 'bulk-filtered-plan-' . uniqid(),
            'price' => 299,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        $syncable = TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_filtered_one',
            'gateway_subscription_id' => 'sub_filtered_one',
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'gateway' => null,
        ]);

        Queue::fake();

        $response = $this
            ->actingAs($admin, 'admin')
            ->post(route('admin.tenants.product-subscriptions.bulk-sync-stripe'), [
                'bulk_sync_action' => 'filtered',
                'tenant_id' => $tenant->id,
                'product_id' => $product->id,
            ]);

        $response
            ->assertRedirect(route('admin.tenants.product-subscriptions.index', [
                'tenant_id' => $tenant->id,
                'product_id' => $product->id,
            ]))
            ->assertSessionHas('success', 'Bulk Stripe sync queued. Queued: 1, skipped: 1.');

        Queue::assertPushed(SyncTenantProductSubscriptionFromStripeJob::class, 1);
        Queue::assertPushed(SyncTenantProductSubscriptionFromStripeJob::class, function ($job) use ($syncable): bool {
            return (int) $job->subscriptionId === (int) $syncable->id;
        });
    }
}
