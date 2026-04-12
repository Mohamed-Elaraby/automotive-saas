<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Admin;
use App\Models\AppSetting;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductCapability;
use App\Models\ProductEnablementRequest;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Admin\ProductLifecycleService;
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
            ->assertRedirect(route('admin.products.show', $product))
            ->assertSessionHas('success', 'Product created successfully. Continue the lifecycle from the product builder below.');

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
            ->assertRedirect(route('admin.products.show', $product))
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

    public function test_products_index_links_plan_counts_to_filtered_plans(): void
    {
        $admin = $this->createAdmin();

        $product = Product::query()->create([
            'code' => 'accounting_suite',
            'name' => 'Accounting Suite',
            'slug' => 'accounting-suite',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Plan::query()->create([
            'product_id' => $product->id,
            'name' => 'Accounting Growth',
            'slug' => 'accounting-growth',
            'price' => 299,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.products.index'));

        $response->assertOk();
        $response->assertSee('1 Plans', false);
        $response->assertSee('0 Capabilities', false);
        $response->assertSee(route('admin.plans.index', ['product_id' => $product->id]), false);
        $response->assertSee(route('admin.products.capabilities.index', $product), false);
        $response->assertSee(route('admin.products.show', $product), false);
    }

    public function test_product_builder_page_shows_lifecycle_steps_and_readiness(): void
    {
        $admin = $this->createAdmin();

        $product = Product::query()->create([
            'code' => 'perfume_retail',
            'name' => 'Perfume Retail Management',
            'slug' => 'perfume-retail',
            'description' => 'Retail and showroom management for perfume stores.',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        Plan::query()->create([
            'product_id' => $product->id,
            'name' => 'Perfume Growth',
            'slug' => 'perfume-growth',
            'price' => 299,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.products.show', $product));

        $response->assertOk();
        $response->assertSee('Product Builder', false);
        $response->assertSee('Lifecycle Snapshot', false);
        $response->assertSee('Builder Steps', false);
        $response->assertSee('Portal Capabilities', false);
        $response->assertSee('Billing Plans', false);
        $response->assertSee(route('admin.plans.create', ['product_id' => $product->id]), false);
        $response->assertSee(route('admin.products.capabilities.index', $product), false);
    }

    public function test_admin_can_save_workspace_experience_draft_from_ui(): void
    {
        $admin = $this->createAdmin();

        $product = Product::query()->create([
            'code' => 'perfume_retail',
            'name' => 'Perfume Retail Management',
            'slug' => 'perfume-retail',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->put(route('admin.products.experience.update', $product), [
                'family_key' => 'perfume_retail',
                'aliases' => "perfume\nfragrance\nshowroom",
                'portal_eyebrow' => 'Perfume Retail Focus',
                'portal_title' => 'Retail and showroom operations',
                'portal_description' => 'Manage branches, sales, and fragrance catalog workflows.',
                'portal_accent' => 'success',
                'sidebar_title' => 'Perfume Retail',
                'dashboard_actions' => "Open POS\nManage Catalog",
                'runtime_modules' => "catalog-management\nsales-pos",
                'integrations' => "accounting\ninventory",
                'notes' => 'Needs POS and inventory runtime.',
            ]);

        $response
            ->assertRedirect(route('admin.products.show', $product))
            ->assertSessionHas('success', 'Workspace experience draft saved successfully.');

        $setting = AppSetting::query()
            ->where('key', 'workspace_products.experience.perfume_retail')
            ->first();

        $this->assertNotNull($setting);

        $payload = json_decode((string) $setting->value, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('perfume_retail', $payload['family_key']);
        $this->assertSame(['perfume', 'fragrance', 'showroom'], $payload['aliases']);
        $this->assertSame(['catalog-management', 'sales-pos'], $payload['runtime_modules']);

        $builderResponse = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.products.show', $product));

        $builderResponse->assertOk();
        $builderResponse->assertSee('Experience Draft', false);
        $builderResponse->assertSee('Yes', false);
        $builderResponse->assertSee(route('admin.products.experience.edit', $product), false);
    }

    public function test_admin_can_publish_ready_product_to_portal_and_hide_it_again(): void
    {
        $admin = $this->createAdmin();

        $product = Product::query()->create([
            'code' => 'perfume_retail',
            'name' => 'Perfume Retail Management',
            'slug' => 'perfume-retail',
            'is_active' => false,
            'sort_order' => 2,
        ]);

        ProductCapability::query()->create([
            'product_id' => $product->id,
            'code' => 'catalog_management',
            'name' => 'Catalog Management',
            'slug' => 'catalog-management',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Plan::query()->create([
            'product_id' => $product->id,
            'name' => 'Perfume Growth',
            'slug' => 'perfume-growth-ready',
            'price' => 299,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        AppSetting::query()->create([
            'group_key' => 'workspace_products',
            'key' => 'workspace_products.experience.perfume_retail',
            'value' => json_encode(['family_key' => 'perfume_retail'], JSON_UNESCAPED_SLASHES),
            'value_type' => 'json',
        ]);

        $showResponse = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.products.portal-publication.show', $product));

        $showResponse->assertOk();
        $showResponse->assertSee('Ready To Publish', false);

        $publishResponse = $this
            ->actingAs($admin, 'admin')
            ->put(route('admin.products.portal-publication.publish', $product));

        $publishResponse
            ->assertRedirect(route('admin.products.portal-publication.show', $product))
            ->assertSessionHas('success', 'Product is now live in the customer portal.');

        $this->assertTrue($product->fresh()->is_active);

        $hideResponse = $this
            ->actingAs($admin, 'admin')
            ->put(route('admin.products.portal-publication.hide', $product));

        $hideResponse
            ->assertRedirect(route('admin.products.portal-publication.show', $product))
            ->assertSessionHas('success', 'Product has been hidden from the customer portal.');

        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_portal_publication_is_blocked_without_family_key_and_audit_is_recorded_after_publish(): void
    {
        $admin = $this->createAdmin();

        $product = Product::query()->create([
            'code' => 'perfume_retail',
            'name' => 'Perfume Retail Management',
            'slug' => 'perfume-retail',
            'is_active' => false,
            'sort_order' => 2,
        ]);

        ProductCapability::query()->create([
            'product_id' => $product->id,
            'code' => 'catalog_management',
            'name' => 'Catalog Management',
            'slug' => 'catalog-management',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Plan::query()->create([
            'product_id' => $product->id,
            'name' => 'Perfume Growth',
            'slug' => 'perfume-growth-ready',
            'price' => 299,
            'currency' => 'USD',
            'billing_period' => 'monthly',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        AppSetting::query()->create([
            'group_key' => 'workspace_products',
            'key' => 'workspace_products.experience.perfume_retail',
            'value' => json_encode(['family_key' => ''], JSON_UNESCAPED_SLASHES),
            'value_type' => 'json',
        ]);

        $blockedResponse = $this
            ->actingAs($admin, 'admin')
            ->put(route('admin.products.portal-publication.publish', $product));

        $blockedResponse
            ->assertRedirect(route('admin.products.portal-publication.show', $product))
            ->assertSessionHas('error', 'This product is not ready for portal publication yet.');

        AppSetting::query()
            ->where('key', 'workspace_products.experience.perfume_retail')
            ->update([
                'value' => json_encode(['family_key' => 'perfume_retail'], JSON_UNESCAPED_SLASHES),
            ]);

        $publishedResponse = $this
            ->actingAs($admin, 'admin')
            ->put(route('admin.products.portal-publication.publish', $product));

        $publishedResponse
            ->assertRedirect(route('admin.products.portal-publication.show', $product))
            ->assertSessionHas('success', 'Product is now live in the customer portal.');

        $audit = app(ProductLifecycleService::class)->auditEntries($product, 20);

        $this->assertNotEmpty($audit);
        $this->assertSame('portal.published', $audit[0]['action']);
    }

    public function test_admin_can_save_runtime_module_draft_from_ui(): void
    {
        $admin = $this->createAdmin();

        $product = Product::query()->create([
            'code' => 'perfume_retail',
            'name' => 'Perfume Retail Management',
            'slug' => 'perfume-retail',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->put(route('admin.products.runtime-modules.update', $product), [
                'modules' => [
                    [
                        'key' => 'sales-pos',
                        'title' => 'Sales POS',
                        'focus_code' => 'perfume_retail',
                        'route_slug' => 'sales-pos',
                        'icon' => 'isax-shop',
                        'description' => 'Retail checkout and in-store sales.',
                    ],
                    [
                        'key' => 'catalog-management',
                        'title' => 'Catalog Management',
                        'focus_code' => 'perfume_retail',
                        'route_slug' => 'catalog-management',
                        'icon' => 'isax-box',
                        'description' => 'Manage perfume catalog and SKUs.',
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('admin.products.show', $product))
            ->assertSessionHas('success', 'Runtime module draft saved successfully.');

        $setting = AppSetting::query()
            ->where('key', 'workspace_products.runtime_modules.perfume_retail')
            ->first();

        $this->assertNotNull($setting);

        $payload = json_decode((string) $setting->value, true, 512, JSON_THROW_ON_ERROR);

        $this->assertCount(2, $payload);
        $this->assertSame('sales-pos', $payload[0]['key']);
        $this->assertSame('catalog-management', $payload[1]['route_slug']);

        $builderResponse = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.products.show', $product));

        $builderResponse->assertOk();
        $builderResponse->assertSee('Runtime Modules Draft', false);
        $builderResponse->assertSee('2', false);
        $builderResponse->assertSee(route('admin.products.runtime-modules.edit', $product), false);
    }

    public function test_admin_can_save_integration_draft_from_ui(): void
    {
        $admin = $this->createAdmin();

        $product = Product::query()->create([
            'code' => 'perfume_retail',
            'name' => 'Perfume Retail Management',
            'slug' => 'perfume-retail',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $accounting = Product::query()->create([
            'code' => 'accounting',
            'name' => 'Accounting System',
            'slug' => 'accounting',
            'is_active' => true,
            'sort_order' => 3,
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->put(route('admin.products.integrations.update', $product), [
                'integrations' => [
                    [
                        'key' => 'perfume-accounting',
                        'target_product_code' => $accounting->code,
                        'title' => 'Sales can post into accounting',
                        'description' => 'Retail invoices and revenue events flow into accounting.',
                        'target_label' => 'Open Accounting',
                        'target_route_slug' => 'general-ledger',
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('admin.products.show', $product))
            ->assertSessionHas('success', 'Integration draft saved successfully.');

        $setting = AppSetting::query()
            ->where('key', 'workspace_products.integrations.perfume_retail')
            ->first();

        $this->assertNotNull($setting);

        $payload = json_decode((string) $setting->value, true, 512, JSON_THROW_ON_ERROR);

        $this->assertCount(1, $payload);
        $this->assertSame('accounting', $payload[0]['target_product_code']);
        $this->assertSame('Open Accounting', $payload[0]['target_label']);

        $builderResponse = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.products.show', $product));

        $builderResponse->assertOk();
        $builderResponse->assertSee('Integrations Draft', false);
        $builderResponse->assertSee('1', false);
        $builderResponse->assertSee(route('admin.products.integrations.edit', $product), false);
    }

    public function test_admin_can_open_manifest_sync_preview_from_saved_drafts(): void
    {
        $admin = $this->createAdmin();

        $product = Product::query()->create([
            'code' => 'perfume_retail',
            'name' => 'Perfume Retail Management',
            'slug' => 'perfume-retail',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        AppSetting::query()->create([
            'group_key' => 'workspace_products',
            'key' => 'workspace_products.experience.perfume_retail',
            'value' => json_encode([
                'family_key' => 'perfume_retail',
                'aliases' => ['perfume', 'fragrance'],
                'portal' => ['title' => 'Retail and showroom operations'],
            ], JSON_UNESCAPED_SLASHES),
            'value_type' => 'json',
        ]);

        AppSetting::query()->create([
            'group_key' => 'workspace_products',
            'key' => 'workspace_products.runtime_modules.perfume_retail',
            'value' => json_encode([
                [
                    'key' => 'sales-pos',
                    'title' => 'Sales POS',
                    'focus_code' => 'perfume_retail',
                    'route_slug' => 'sales-pos',
                    'icon' => 'isax-shop',
                    'description' => 'Retail checkout and in-store sales.',
                ],
            ], JSON_UNESCAPED_SLASHES),
            'value_type' => 'json',
        ]);

        AppSetting::query()->create([
            'group_key' => 'workspace_products',
            'key' => 'workspace_products.integrations.perfume_retail',
            'value' => json_encode([
                [
                    'key' => 'perfume-accounting',
                    'target_product_code' => 'accounting',
                    'title' => 'Sales can post into accounting',
                    'description' => 'Retail invoices and revenue events flow into accounting.',
                    'target_label' => 'Open Accounting',
                    'target_route_slug' => 'general-ledger',
                ],
            ], JSON_UNESCAPED_SLASHES),
            'value_type' => 'json',
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.products.manifest-sync.show', $product));

        $response->assertOk();
        $response->assertSee('Manifest Sync Preview', false);
        $response->assertSee('perfume_retail', false);
        $response->assertSee('sales-pos', false);
        $response->assertSee('perfume-accounting', false);
        $response->assertSee('automotive.admin.modules.general-ledger', false);
        $response->assertSee('Code Writeback Assistant', false);
        $response->assertSee('config/workspace_products.php', false);
    }

    public function test_admin_can_update_manifest_sync_workflow_state(): void
    {
        $admin = $this->createAdmin();

        $product = Product::query()->create([
            'code' => 'perfume_retail',
            'name' => 'Perfume Retail Management',
            'slug' => 'perfume-retail',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        AppSetting::query()->create([
            'group_key' => 'workspace_products',
            'key' => 'workspace_products.experience.perfume_retail',
            'value' => json_encode([
                'family_key' => 'perfume_retail',
            ], JSON_UNESCAPED_SLASHES),
            'value_type' => 'json',
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->put(route('admin.products.manifest-sync.update', $product), [
                'status' => 'approved',
                'notes' => 'Ready for config/workspace_products.php write-back.',
            ]);

        $response
            ->assertRedirect(route('admin.products.manifest-sync.show', $product))
            ->assertSessionHas('success', 'Manifest sync workflow updated successfully.');

        $setting = AppSetting::query()
            ->where('key', 'workspace_products.manifest_sync_workflow.perfume_retail')
            ->first();

        $this->assertNotNull($setting);

        $payload = json_decode((string) $setting->value, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('approved', $payload['status']);
        $this->assertSame('Ready for config/workspace_products.php write-back.', $payload['notes']);
        $this->assertNotEmpty($payload['reviewed_at']);

        $snapshot = AppSetting::query()
            ->where('key', 'workspace_products.manifest_sync_snapshot.perfume_retail')
            ->first();

        $this->assertNotNull($snapshot);

        $snapshotPayload = json_decode((string) $snapshot->value, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('approved', $snapshotPayload['status']);
        $this->assertSame('perfume_retail', $snapshotPayload['family_key']);
        $this->assertArrayHasKey('payload', $snapshotPayload);

        $writebackPackage = AppSetting::query()
            ->where('key', 'workspace_products.manifest_writeback_package.perfume_retail')
            ->first();

        $this->assertNotNull($writebackPackage);

        $writebackPayload = json_decode((string) $writebackPackage->value, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('config/workspace_products.php', $writebackPayload['target_file']);
        $this->assertSame('workspace_products.families.perfume_retail', $writebackPayload['config_path']);
        $this->assertSame('perfume_retail', $writebackPayload['family_key']);
    }

    public function test_manifest_sync_approval_is_blocked_when_structured_drafts_are_missing(): void
    {
        $admin = $this->createAdmin();

        $product = Product::query()->create([
            'code' => 'perfume_retail',
            'name' => 'Perfume Retail Management',
            'slug' => 'perfume-retail',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        AppSetting::query()->create([
            'group_key' => 'workspace_products',
            'key' => 'workspace_products.experience.perfume_retail',
            'value' => json_encode([
                'family_key' => 'perfume_retail',
                'runtime_modules' => ['sales-pos'],
                'integrations' => ['accounting'],
            ], JSON_UNESCAPED_SLASHES),
            'value_type' => 'json',
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->put(route('admin.products.manifest-sync.update', $product), [
                'status' => 'approved',
                'notes' => 'Try to approve early.',
            ]);

        $response
            ->assertRedirect(route('admin.products.manifest-sync.show', $product))
            ->assertSessionHas('error', 'Manifest sync cannot move to an approved state until all blockers are cleared.');

        $this->assertDatabaseMissing('app_settings', [
            'key' => 'workspace_products.manifest_sync_workflow.perfume_retail',
        ]);
    }

    public function test_admin_can_export_manifest_sync_payload_in_multiple_formats(): void
    {
        $admin = $this->createAdmin();

        $product = Product::query()->create([
            'code' => 'perfume_retail',
            'name' => 'Perfume Retail Management',
            'slug' => 'perfume-retail',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        AppSetting::query()->create([
            'group_key' => 'workspace_products',
            'key' => 'workspace_products.experience.perfume_retail',
            'value' => json_encode([
                'family_key' => 'perfume_retail',
                'aliases' => ['perfume', 'fragrance'],
                'portal' => ['title' => 'Retail and showroom operations'],
            ], JSON_UNESCAPED_SLASHES),
            'value_type' => 'json',
        ]);

        $jsonResponse = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.products.manifest-sync.export', [$product, 'json']));

        $jsonResponse->assertOk();
        $jsonResponse->assertSee('"aliases"', false);
        $jsonResponse->assertSee('"perfume"', false);

        $phpResponse = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.products.manifest-sync.export', [$product, 'php']));

        $phpResponse->assertOk();
        $phpResponse->assertSee('$familyDefinition', false);
        $phpResponse->assertSee('perfume_retail', false);

        $familyResponse = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.products.manifest-sync.export', [$product, 'family']));

        $familyResponse->assertOk();
        $familyResponse->assertSee('return array', false);
        $familyResponse->assertSee('perfume_retail', false);

        $executionJsonResponse = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.products.manifest-sync.export', [$product, 'execution-json']));

        $executionJsonResponse->assertOk();
        $executionJsonResponse->assertSee('"target_file"', false);
        $executionJsonResponse->assertSee('"config/workspace_products.php"', false);

        $executionPhpResponse = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.products.manifest-sync.export', [$product, 'execution-php']));

        $executionPhpResponse->assertOk();
        $executionPhpResponse->assertSee('workspace_products.families.perfume_retail', false);
        $executionPhpResponse->assertSee('config/workspace_products.php', false);
    }

    public function test_admin_can_open_and_update_manifest_apply_queue(): void
    {
        $admin = $this->createAdmin();

        $product = Product::query()->create([
            'code' => 'perfume_retail',
            'name' => 'Perfume Retail Management',
            'slug' => 'perfume-retail',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        AppSetting::query()->create([
            'group_key' => 'workspace_products',
            'key' => 'workspace_products.manifest_sync_workflow.perfume_retail',
            'value' => json_encode([
                'status' => 'approved',
                'notes' => 'Approved for writeback.',
                'reviewed_at' => now()->toDateTimeString(),
            ], JSON_UNESCAPED_SLASHES),
            'value_type' => 'json',
        ]);

        AppSetting::query()->create([
            'group_key' => 'workspace_products',
            'key' => 'workspace_products.manifest_sync_snapshot.perfume_retail',
            'value' => json_encode([
                'status' => 'approved',
                'family_key' => 'perfume_retail',
                'payload' => ['aliases' => ['perfume']],
                'captured_at' => now()->toDateTimeString(),
            ], JSON_UNESCAPED_SLASHES),
            'value_type' => 'json',
        ]);

        $showResponse = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.products.manifest-apply-queue.show', $product));

        $showResponse->assertOk();
        $showResponse->assertSee('Manifest Apply Queue', false);
        $showResponse->assertSee('Execution Readiness', false);
        $showResponse->assertSee('Approved for writeback.', false);

        $updateResponse = $this
            ->actingAs($admin, 'admin')
            ->put(route('admin.products.manifest-apply-queue.update', $product), [
                'status' => 'in_progress',
                'owner_name' => 'Platform Team',
                'owner_contact' => 'platform@example.test',
                'blocking_reason' => '',
                'implementation_notes' => 'Update workspace manifest and runtime sidebar wiring.',
                'deployment_notes' => 'Run workspace smoke checks after merge.',
            ]);

        $updateResponse
            ->assertRedirect(route('admin.products.manifest-apply-queue.show', $product))
            ->assertSessionHas('success', 'Manifest apply queue updated successfully.');

        $setting = AppSetting::query()
            ->where('key', 'workspace_products.manifest_apply_queue.perfume_retail')
            ->first();

        $this->assertNotNull($setting);

        $payload = json_decode((string) $setting->value, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('in_progress', $payload['status']);
        $this->assertSame('Platform Team', $payload['owner_name']);
        $this->assertSame('platform@example.test', $payload['owner_contact']);
        $this->assertSame('Update workspace manifest and runtime sidebar wiring.', $payload['implementation_notes']);
        $this->assertSame('Run workspace smoke checks after merge.', $payload['deployment_notes']);
        $this->assertNotEmpty($payload['queued_at']);
        $this->assertNotEmpty($payload['started_at']);
        $this->assertNull($payload['completed_at']);

        $builderResponse = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.products.show', $product));

        $builderResponse->assertOk();
        $builderResponse->assertSee('Manifest Apply Queue', false);
        $builderResponse->assertSee(route('admin.products.manifest-apply-queue.show', $product), false);
        $builderResponse->assertSee('IN_PROGRESS', false);
    }

    public function test_manifest_apply_queue_cannot_start_without_owner_assignment(): void
    {
        $admin = $this->createAdmin();

        $product = Product::query()->create([
            'code' => 'perfume_retail',
            'name' => 'Perfume Retail Management',
            'slug' => 'perfume-retail',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        AppSetting::query()->create([
            'group_key' => 'workspace_products',
            'key' => 'workspace_products.manifest_sync_workflow.perfume_retail',
            'value' => json_encode([
                'status' => 'approved',
                'notes' => 'Approved for writeback.',
                'reviewed_at' => now()->toDateTimeString(),
            ], JSON_UNESCAPED_SLASHES),
            'value_type' => 'json',
        ]);

        AppSetting::query()->create([
            'group_key' => 'workspace_products',
            'key' => 'workspace_products.manifest_sync_snapshot.perfume_retail',
            'value' => json_encode([
                'status' => 'approved',
                'family_key' => 'perfume_retail',
                'payload' => ['aliases' => ['perfume']],
                'captured_at' => now()->toDateTimeString(),
            ], JSON_UNESCAPED_SLASHES),
            'value_type' => 'json',
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->put(route('admin.products.manifest-apply-queue.update', $product), [
                'status' => 'in_progress',
                'owner_name' => '',
                'owner_contact' => '',
                'blocking_reason' => '',
                'implementation_notes' => 'Start coding.',
                'deployment_notes' => '',
            ]);

        $response
            ->assertRedirect(route('admin.products.manifest-apply-queue.show', $product))
            ->assertSessionHas('error', 'Manifest apply execution cannot start until workflow and ownership blockers are cleared.');

        $this->assertDatabaseMissing('app_settings', [
            'key' => 'workspace_products.manifest_apply_queue.perfume_retail',
        ]);
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
            ->assertSessionHas('error', 'This product cannot be deleted because it is already used by plans, capabilities, subscriptions, or enablement requests.');

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
