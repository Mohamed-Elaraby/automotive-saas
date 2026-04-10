<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCapabilityCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_admin_can_create_update_and_filter_product_capabilities(): void
    {
        $admin = $this->createAdmin();
        $product = Product::query()->create([
            'code' => 'accounting_suite',
            'name' => 'Accounting Suite',
            'slug' => 'accounting-suite',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $createResponse = $this
            ->actingAs($admin, 'admin')
            ->post(route('admin.products.capabilities.store', $product), [
                'code' => 'general_ledger',
                'name' => 'General Ledger',
                'slug' => 'general-ledger',
                'description' => 'Core accounting ledger module',
                'is_active' => 1,
                'sort_order' => 2,
            ]);

        $capability = ProductCapability::query()->where('product_id', $product->id)->firstOrFail();

        $createResponse
            ->assertRedirect(route('admin.products.capabilities.index', $product))
            ->assertSessionHas('success', 'Product capability created successfully.');

        $updateResponse = $this
            ->actingAs($admin, 'admin')
            ->put(route('admin.products.capabilities.update', [$product, $capability]), [
                'code' => 'general_ledger_plus',
                'name' => 'General Ledger Plus',
                'slug' => 'general-ledger-plus',
                'description' => 'Updated ledger module',
                'is_active' => 0,
                'sort_order' => 5,
            ]);

        $updateResponse
            ->assertRedirect(route('admin.products.capabilities.index', $product))
            ->assertSessionHas('success', 'Product capability updated successfully.');

        $filterResponse = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.products.capabilities.index', [
                'product' => $product,
                'search' => 'ledger plus',
                'is_active' => '0',
            ]));

        $filterResponse->assertOk();
        $filterResponse->assertSee('General Ledger Plus');
        $filterResponse->assertSee('Updated ledger module');
    }

    protected function createAdmin(): Admin
    {
        return Admin::query()->create([
            'name' => 'Central Admin',
            'email' => 'admin-product-capabilities-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);
    }
}
