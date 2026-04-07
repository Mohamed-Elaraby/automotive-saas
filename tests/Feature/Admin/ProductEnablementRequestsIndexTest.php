<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\CustomerPortalNotification;
use App\Models\Product;
use App\Models\ProductEnablementRequest;
use App\Models\TenantProductSubscription;
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

    public function test_admin_can_approve_a_product_enablement_request(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Central Admin',
            'email' => 'admin-approve-enable-' . uniqid() . '@example.test',
            'password' => 'password',
        ]);

        $user = User::query()->create([
            'name' => 'Portal User',
            'email' => 'portal-approve-enable-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        $product = Product::query()->create([
            'code' => 'approve_product_' . uniqid(),
            'name' => 'Approve Product',
            'slug' => 'approve-product-' . uniqid(),
            'is_active' => true,
        ]);

        $requestRow = ProductEnablementRequest::query()->create([
            'user_id' => $user->id,
            'tenant_id' => 'tenant-approve',
            'product_id' => $product->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->withSession(['_token' => 'test-token'])
            ->post(route('admin.product-enablement-requests.approve', $requestRow->id), [
                '_token' => 'test-token',
            ]);

        $response->assertRedirect(route('admin.product-enablement-requests.index'));
        $response->assertSessionHas('success', 'Product enablement request approved successfully.');

        $this->assertDatabaseHas('product_enablement_requests', [
            'id' => $requestRow->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('tenant_product_subscriptions', [
            'tenant_id' => 'tenant-approve',
            'product_id' => $product->id,
            'status' => 'active',
            'legacy_subscription_id' => null,
        ]);
        $this->assertDatabaseHas('admin_notifications', [
            'type' => 'product_enablement_request',
            'source_id' => $requestRow->id,
            'tenant_id' => 'tenant-approve',
            'user_id' => $user->id,
            'severity' => 'success',
        ]);
        $this->assertDatabaseHas('customer_portal_notifications', [
            'user_id' => $user->id,
            'tenant_id' => 'tenant-approve',
            'product_id' => $product->id,
            'severity' => 'success',
        ]);
    }

    public function test_approving_request_does_not_duplicate_existing_attached_product_subscription(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Central Admin',
            'email' => 'admin-approve-existing-enable-' . uniqid() . '@example.test',
            'password' => 'password',
        ]);

        $user = User::query()->create([
            'name' => 'Portal User',
            'email' => 'portal-approve-existing-enable-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        $product = Product::query()->create([
            'code' => 'approve_existing_product_' . uniqid(),
            'name' => 'Approve Existing Product',
            'slug' => 'approve-existing-product-' . uniqid(),
            'is_active' => true,
        ]);

        $existingSubscription = TenantProductSubscription::query()->create([
            'tenant_id' => 'tenant-approve-existing',
            'product_id' => $product->id,
            'status' => 'active',
            'payment_failures_count' => 0,
        ]);

        $requestRow = ProductEnablementRequest::query()->create([
            'user_id' => $user->id,
            'tenant_id' => 'tenant-approve-existing',
            'product_id' => $product->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->withSession(['_token' => 'test-token'])
            ->post(route('admin.product-enablement-requests.approve', $requestRow->id), [
                '_token' => 'test-token',
            ]);

        $response->assertRedirect(route('admin.product-enablement-requests.index'));

        $this->assertSame(
            1,
            TenantProductSubscription::query()
                ->where('tenant_id', 'tenant-approve-existing')
                ->where('product_id', $product->id)
                ->count()
        );
        $this->assertDatabaseHas('tenant_product_subscriptions', [
            'id' => $existingSubscription->id,
            'tenant_id' => 'tenant-approve-existing',
            'product_id' => $product->id,
            'status' => 'active',
        ]);
        $this->assertSame(1, AdminNotification::query()->where('source_id', $requestRow->id)->count());
        $this->assertSame(1, CustomerPortalNotification::query()->where('user_id', $user->id)->count());
    }

    public function test_admin_can_reject_a_product_enablement_request(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Central Admin',
            'email' => 'admin-reject-enable-' . uniqid() . '@example.test',
            'password' => 'password',
        ]);

        $user = User::query()->create([
            'name' => 'Portal User',
            'email' => 'portal-reject-enable-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
        ]);

        $product = Product::query()->create([
            'code' => 'reject_product_' . uniqid(),
            'name' => 'Reject Product',
            'slug' => 'reject-product-' . uniqid(),
            'is_active' => true,
        ]);

        $requestRow = ProductEnablementRequest::query()->create([
            'user_id' => $user->id,
            'tenant_id' => 'tenant-reject',
            'product_id' => $product->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->withSession(['_token' => 'test-token'])
            ->post(route('admin.product-enablement-requests.reject', $requestRow->id), [
                '_token' => 'test-token',
            ]);

        $response->assertRedirect(route('admin.product-enablement-requests.index'));
        $response->assertSessionHas('success', 'Product enablement request rejected successfully.');

        $this->assertDatabaseHas('product_enablement_requests', [
            'id' => $requestRow->id,
            'status' => 'rejected',
        ]);
        $this->assertDatabaseHas('admin_notifications', [
            'type' => 'product_enablement_request',
            'source_id' => $requestRow->id,
            'tenant_id' => 'tenant-reject',
            'user_id' => $user->id,
            'severity' => 'warning',
        ]);
        $this->assertDatabaseHas('customer_portal_notifications', [
            'user_id' => $user->id,
            'tenant_id' => 'tenant-reject',
            'product_id' => $product->id,
            'severity' => 'warning',
        ]);
    }
}
