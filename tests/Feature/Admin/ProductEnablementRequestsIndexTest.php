<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductEnablementRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductEnablementRequestsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_filter_product_enablement_requests(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Central Admin',
            'email' => 'admin-enable-requests-' . uniqid() . '@example.test',
            'password' => 'password',
        ]);

        $user = User::query()->create([
            'name' => 'Portal User',
            'email' => 'portal-enable-requests-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        $matchingProduct = Product::query()->create([
            'code' => 'accounting_admin_' . uniqid(),
            'name' => 'Accounting Admin Product',
            'slug' => 'accounting-admin-' . uniqid(),
            'is_active' => true,
        ]);

        $otherProduct = Product::query()->create([
            'code' => 'inventory_admin_' . uniqid(),
            'name' => 'Inventory Admin Product',
            'slug' => 'inventory-admin-' . uniqid(),
            'is_active' => true,
        ]);

        ProductEnablementRequest::query()->create([
            'user_id' => $user->id,
            'tenant_id' => 'tenant-alpha',
            'product_id' => $matchingProduct->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        ProductEnablementRequest::query()->create([
            'user_id' => $user->id,
            'tenant_id' => 'tenant-beta',
            'product_id' => $otherProduct->id,
            'status' => 'approved',
            'requested_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.product-enablement-requests.index', [
            'status' => 'pending',
            'product_id' => $matchingProduct->id,
            'q' => 'tenant-alpha',
        ]));

        $response->assertOk();
        $response->assertSee('Product Enablement Requests', false);
        $response->assertSee('Accounting Admin Product', false);
        $response->assertSee('tenant-alpha', false);
        $response->assertSee('PENDING', false);
        $response->assertDontSee('tenant-beta', false);
    }
}
