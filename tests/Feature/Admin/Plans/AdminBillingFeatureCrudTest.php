<?php

namespace Tests\Feature\Admin\Plans;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Admin;
use App\Models\BillingFeature;
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

    protected function createAdmin(): Admin
    {
        return Admin::query()->create([
            'name' => 'Central Admin',
            'email' => 'admin-billing-features-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);
    }
}
