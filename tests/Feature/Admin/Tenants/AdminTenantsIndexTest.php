<?php

namespace Tests\Feature\Admin\Tenants;

use App\Models\Admin;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminTenantsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_displays_gateway_and_quick_actions(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Central Admin',
            'email' => 'admin-index@example.test',
            'password' => bcrypt('password'),
        ]);

        $tenantId = 'tenant-index-display-' . uniqid();
        $tenant = Tenant::query()->create([
            'id' => $tenantId,
            'data' => [
                'company_name' => 'Index Display Co',
                'owner_email' => 'owner-index@example.test',
            ],
        ]);

        DB::table('domains')->insert([
            'tenant_id' => $tenant->id,
            'domain' => $tenantId . '.example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $plan = $this->createPlan('Scale', 'scale-' . uniqid());

        DB::table('subscriptions')->insert([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_index',
            'gateway_subscription_id' => 'sub_index',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.tenants.index'));

        $response->assertOk();
        $response->assertSee('Impersonate', false);
        $response->assertSee('Suspend', false);
        $response->assertSee('Open', false);
        $response->assertSee('STRIPE', false);
        $response->assertSee('Stripe-linked', false);
    }

    public function test_index_filters_by_plan_gateway_and_has_domain(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Central Admin',
            'email' => 'admin-filters@example.test',
            'password' => bcrypt('password'),
        ]);

        $matchingPlan = $this->createPlan('Growth', 'growth-' . uniqid());
        $otherPlan = $this->createPlan('Starter', 'starter-' . uniqid());

        $matchingTenant = Tenant::query()->create([
            'id' => 'tenant-match-' . uniqid(),
            'data' => [
                'company_name' => 'Matching Co',
                'owner_email' => 'match@example.test',
            ],
        ]);

        $otherTenant = Tenant::query()->create([
            'id' => 'tenant-other-' . uniqid(),
            'data' => [
                'company_name' => 'Other Co',
                'owner_email' => 'other@example.test',
            ],
        ]);

        DB::table('domains')->insert([
            'tenant_id' => $matchingTenant->id,
            'domain' => $matchingTenant->id . '.example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('subscriptions')->insert([
            'tenant_id' => $matchingTenant->id,
            'plan_id' => $matchingPlan->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_match',
            'gateway_subscription_id' => 'sub_match',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('subscriptions')->insert([
            'tenant_id' => $otherTenant->id,
            'plan_id' => $otherPlan->id,
            'status' => 'active',
            'gateway' => null,
            'gateway_customer_id' => null,
            'gateway_subscription_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.tenants.index', [
            'plan_id' => $matchingPlan->id,
            'gateway' => 'stripe',
            'has_domain' => 'yes',
        ]));

        $response->assertOk();
        $response->assertSee($matchingTenant->id);
        $response->assertDontSee($otherTenant->id);
    }

    protected function createPlan(string $name, string $slug): Plan
    {
        return Plan::query()->create([
            'name' => $name,
            'slug' => $slug,
            'description' => $name . ' description',
            'price' => 199,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_' . uniqid(),
            'stripe_price_id' => 'price_' . uniqid(),
        ]);
    }
}
