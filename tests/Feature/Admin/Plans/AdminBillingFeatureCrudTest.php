<?php

namespace Tests\Feature\Admin\Plans;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Admin;
use App\Models\BillingFeature;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBillingFeatureCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_admin_can_create_update_and_toggle_billing_feature(): void
    {
        $admin = $this->createAdmin();

        $createResponse = $this
            ->actingAs($admin, 'admin')
            ->post(route('admin.billing-features.store'), [
                'name' => 'Invoicing',
                'slug' => 'invoicing',
                'description' => 'Create invoices and quotes',
                'sort_order' => 1,
                'is_active' => 1,
            ]);

        $feature = BillingFeature::query()->where('slug', 'invoicing')->firstOrFail();

        $createResponse
            ->assertRedirect(route('admin.billing-features.index'))
            ->assertSessionHas('success', 'Feature created successfully.');

        $updateResponse = $this
            ->actingAs($admin, 'admin')
            ->put(route('admin.billing-features.update', $feature), [
                'name' => 'Advanced Invoicing',
                'slug' => 'advanced-invoicing',
                'description' => 'Create invoices, quotes, and credit notes',
                'sort_order' => 2,
                'is_active' => 1,
            ]);

        $updateResponse
            ->assertRedirect(route('admin.billing-features.index'))
            ->assertSessionHas('success', 'Feature updated successfully.');

        $feature->refresh();

        $this->assertSame('Advanced Invoicing', $feature->name);
        $this->assertSame('advanced-invoicing', $feature->slug);
        $this->assertSame(2, $feature->sort_order);

        $toggleResponse = $this
            ->actingAs($admin, 'admin')
            ->patch(route('admin.billing-features.toggle-active', $feature));

        $toggleResponse
            ->assertRedirect(route('admin.billing-features.index'))
            ->assertSessionHas('success', 'Feature status updated successfully.');

        $this->assertFalse($feature->fresh()->is_active);
    }

    public function test_admin_can_filter_billing_features_index_by_search_status_and_usage(): void
    {
        $admin = $this->createAdmin();

        $inventory = BillingFeature::query()->create([
            'name' => 'Inventory Control',
            'slug' => 'inventory-control',
            'description' => 'Track stock across branches',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        BillingFeature::query()->create([
            'name' => 'Workshop Jobs',
            'slug' => 'workshop-jobs',
            'description' => 'Manage repair orders',
            'sort_order' => 2,
            'is_active' => false,
        ]);
        BillingFeature::query()->create([
            'name' => 'CRM',
            'slug' => 'crm',
            'description' => 'Customer follow-up tools',
            'sort_order' => 3,
            'is_active' => true,
        ]);

        $plan = Plan::query()->create([
            'name' => 'Growth',
            'slug' => 'growth',
            'price' => 199,
            'currency' => 'AED',
            'billing_period' => 'monthly',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $plan->billingFeatures()->attach($inventory->id, ['sort_order' => 1]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.billing-features.index', [
                'q' => 'inventory',
                'status' => 'active',
                'usage' => 'assigned',
            ]));

        $response->assertOk();
        $response->assertSee('Inventory Control');
        $response->assertSee('Growth');
        $response->assertDontSee('Workshop Jobs');
        $response->assertDontSee('Customer follow-up tools');
    }

    protected function createAdmin(): Admin
    {
        return Admin::query()->create([
            'name' => 'Central Admin',
            'email' => 'admin-billing-features-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);
    }
}
