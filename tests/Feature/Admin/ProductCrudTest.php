<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Admin;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductEnablementRequest;
use App\Models\TenantProductSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_admin_can_create_update_and_filter_products(): void
    {
        $admin = $this->createAdmin();

        $createResponse = $this
            ->actingAs($admin, 'admin')
            ->post(route('admin.products.store'), [
                'code' => 'accounting_suite',
                'name' => 'Accounting Suite',
                'slug' => 'accounting-suite',
                'description' => 'Accounting module',
                'is_active' => 1,
                'sort_order' => 5,
            ]);

        $product = Product::query()->where('slug', 'accounting-suite')->firstOrFail();

        $createResponse
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('success', 'Product created successfully.');

        $updateResponse = $this
            ->actingAs($admin, 'admin')
            ->put(route('admin.products.update', $product), [
                'code' => 'accounting_suite_plus',
                'name' => 'Accounting Suite Plus',
                'slug' => 'accounting-suite-plus',
                'description' => 'Updated accounting module',
                'is_active' => 0,
                'sort_order' => 7,
            ]);

        $updateResponse
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('success', 'Product updated successfully.');

        $product->refresh();

        $this->assertSame('accounting_suite_plus', $product->code);
        $this->assertSame('Accounting Suite Plus', $product->name);
        $this->assertSame('accounting-suite-plus', $product->slug);
        $this->assertFalse($product->is_active);
        $this->assertSame(7, $product->sort_order);

        $filterResponse = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.products.index', [
                'search' => 'suite plus',
                'is_active' => '0',
            ]));

        $filterResponse->assertOk();
        $filterResponse->assertSee('Accounting Suite Plus');
    }

    public function test_admin_cannot_delete_product_when_it_is_used(): void
    {
        $admin = $this->createAdmin();

        $product = Product::query()->create([
            'code' => 'inventory_used',
            'name' => 'Inventory Used',
            'slug' => 'inventory-used',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $plan = Plan::query()->create([
            'product_id' => $product->id,
            'name' => 'Inventory Plan',
            'slug' => 'inventory-plan',
            'price' => 199,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        TenantProductSubscription::query()->create([
            'tenant_id' => 'tenant-used-product',
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $user = User::query()->create([
            'name' => 'Portal User',
            'email' => 'product-used-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        ProductEnablementRequest::query()->create([
            'user_id' => $user->id,
            'tenant_id' => 'tenant-used-product',
            'product_id' => $product->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->delete(route('admin.products.destroy', $product));

        $response
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('error', 'This product cannot be deleted because it is already used by plans, subscriptions, or enablement requests.');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
        ]);
    }

    protected function createAdmin(): Admin
    {
        return Admin::query()->create([
            'name' => 'Central Admin',
            'email' => 'admin-products-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);
    }
}
