<?php

namespace Tests\Feature\Admin\Tenants;

use App\Http\Controllers\Admin\TenantController;
use App\Models\Coupon;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Admin\AdminActivityLogger;
use App\Services\Admin\AdminTenantLifecycleService;
use App\Services\Admin\TenantImpersonationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AdminTenantDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_delete_tenant_removes_linked_central_records_for_non_stripe_tenant(): void
    {
        $tenant = Tenant::query()->create([
            'id' => 'tenant-delete-local',
            'data' => [
                'company_name' => 'Delete Local Tenant',
                'db_name' => 'tenant_tenant-delete-local',
            ],
        ]);

        DB::table('domains')->insert([
            'domain' => 'tenant-delete-local.example.test',
            'tenant_id' => $tenant->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::query()->create([
            'name' => 'Owner',
            'email' => 'owner-local@example.test',
            'password' => bcrypt('password'),
        ]);

        DB::table('tenant_users')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $plan = $this->createPlan('Starter Local', 'starter-local');

        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'expired',
            'gateway' => null,
            'gateway_customer_id' => null,
            'gateway_subscription_id' => null,
            'gateway_checkout_session_id' => null,
            'gateway_price_id' => null,
        ]);

        $coupon = Coupon::query()->create([
            'code' => 'DELETELOCAL',
            'name' => 'Delete Local Coupon',
            'discount_type' => 'fixed',
            'discount_value' => 10,
            'currency_code' => 'USD',
        ]);

        DB::table('coupon_redemptions')->insert([
            'coupon_id' => $coupon->id,
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'status' => 'applied',
            'discount_amount' => 10,
            'currency_code' => 'USD',
            'context_payload' => json_encode(['source' => 'test']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(AdminTenantLifecycleService::class);

        $result = $service->deleteTenant($tenant->id);

        $this->assertSame($tenant->id, $result['tenant_id']);
        $this->assertFalse($result['database_dropped']);
        $this->assertSame(1, $result['deleted']['domains']);
        $this->assertSame(1, $result['deleted']['tenant_users']);
        $this->assertSame(1, $result['deleted']['subscriptions']);
        $this->assertSame(1, $result['deleted']['coupon_redemptions']);
        $this->assertSame(1, $result['deleted']['tenant']);

        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
        $this->assertDatabaseMissing('domains', ['tenant_id' => $tenant->id]);
        $this->assertDatabaseMissing('tenant_users', ['tenant_id' => $tenant->id]);
        $this->assertDatabaseMissing('subscriptions', ['tenant_id' => $tenant->id]);
        $this->assertDatabaseMissing('coupon_redemptions', ['tenant_id' => $tenant->id]);
    }

    public function test_delete_tenant_rejects_live_stripe_linked_subscription(): void
    {
        $tenant = Tenant::query()->create([
            'id' => 'tenant-delete-stripe',
            'data' => [
                'company_name' => 'Delete Stripe Tenant',
                'db_name' => 'tenant_tenant-delete-stripe',
            ],
        ]);

        $plan = $this->createPlan('Starter Stripe', 'starter-stripe');

        Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_delete_block',
            'gateway_subscription_id' => 'sub_delete_block',
            'gateway_checkout_session_id' => 'cs_delete_block',
            'gateway_price_id' => 'price_delete_block',
        ]);

        $service = app(AdminTenantLifecycleService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This tenant has a live Stripe-linked subscription. Cancel or expire the subscription first before deleting the tenant.');

        try {
            $service->deleteTenant($tenant->id);
        } finally {
            $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
            $this->assertDatabaseHas('subscriptions', ['tenant_id' => $tenant->id, 'gateway_subscription_id' => 'sub_delete_block']);
        }
    }

    public function test_destroy_redirects_to_index_and_logs_activity_after_successful_deletion(): void
    {
        $tenant = Tenant::query()->create([
            'id' => 'tenant-controller-destroy',
            'data' => [
                'company_name' => 'Controller Delete Tenant',
            ],
        ]);

        $lifecycleService = Mockery::mock(AdminTenantLifecycleService::class);
        $lifecycleService->shouldReceive('deleteTenant')
            ->once()
            ->with($tenant->id)
            ->andReturn([
                'tenant_id' => $tenant->id,
                'deleted' => [
                    'domains' => 1,
                    'tenant_users' => 1,
                    'subscriptions' => 1,
                    'coupon_redemptions' => 0,
                    'tenant' => 1,
                ],
            ]);

        $activityLogger = Mockery::mock(AdminActivityLogger::class);
        $activityLogger->shouldReceive('log')
            ->once()
            ->withArgs(function (string $action, ?string $subjectType, $subjectId, ?string $tenantId, array $contextPayload) use ($tenant): bool {
                return $action === 'tenant.deleted'
                    && $subjectType === 'tenant'
                    && $subjectId === $tenant->id
                    && $tenantId === $tenant->id
                    && ($contextPayload['tenant_id'] ?? null) === $tenant->id;
            });

        $controller = new TenantController(
            $lifecycleService,
            $activityLogger,
            Mockery::mock(TenantImpersonationService::class)
        );

        $response = $controller->destroy($tenant->id);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('admin.tenants.index'), $response->getTargetUrl());
        $this->assertSame('The tenant and its linked central records were deleted successfully.', session('success'));
    }

    protected function createPlan(string $name, string $slug): Plan
    {
        return Plan::query()->create([
            'name' => $name,
            'slug' => $slug,
            'description' => $name . ' description',
            'price' => 99,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);
    }
}
