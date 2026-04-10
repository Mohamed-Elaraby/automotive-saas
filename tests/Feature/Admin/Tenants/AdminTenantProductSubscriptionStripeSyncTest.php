<?php

namespace Tests\Feature\Admin\Tenants;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Admin;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Services\Billing\AdminTenantProductSubscriptionStripeSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
