<?php

namespace Tests\Feature\Automotive\Admin;

use App\Http\Middleware\EnsureTenantSubscriptionIsActive;
use App\Models\Tenant;
use App\Services\Billing\TenantBillingLifecycleService;
use App\Services\Tenancy\TenantPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class EnsureTenantSubscriptionIsActiveMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::name('automotive.admin.billing.status')->get('/test-billing-status', fn () => 'billing-status');
        Route::name('automotive.admin.dashboard')->get('/test-dashboard', fn () => 'dashboard');
    }

    protected function tearDown(): void
    {
        try {
            if (function_exists('tenancy') && tenancy()->initialized) {
                tenancy()->end();
            }
        } catch (\Throwable) {
            //
        }

        Mockery::close();

        parent::tearDown();
    }

    public function test_it_allows_access_when_billing_state_allows_access(): void
    {
        $tenant = $this->initializeTenantContext('tenant-middleware-active');

        $tenantPlanService = Mockery::mock(TenantPlanService::class);
        $tenantPlanService->shouldReceive('getCurrentSubscription')
            ->once()
            ->with($tenant->id)
            ->andReturn((object) ['tenant_id' => $tenant->id, 'status' => 'active']);

        $lifecycleService = Mockery::mock(TenantBillingLifecycleService::class);
        $lifecycleService->shouldReceive('resolveState')
            ->once()
            ->andReturn([
                'status' => 'active',
                'allow_access' => true,
                'message' => 'Allowed',
            ]);

        $middleware = new EnsureTenantSubscriptionIsActive($tenantPlanService, $lifecycleService);

        $request = Request::create('/test-dashboard', 'GET');
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

        $response = $middleware->handle($request, fn () => new Response('ok', 200));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }

    public function test_it_redirects_blocked_tenants_to_billing_page_for_protected_routes(): void
    {
        $tenant = $this->initializeTenantContext('tenant-middleware-blocked');

        $tenantPlanService = Mockery::mock(TenantPlanService::class);
        $tenantPlanService->shouldReceive('getCurrentSubscription')
            ->once()
            ->with($tenant->id)
            ->andReturn((object) ['tenant_id' => $tenant->id, 'status' => 'suspended']);

        $lifecycleService = Mockery::mock(TenantBillingLifecycleService::class);
        $lifecycleService->shouldReceive('resolveState')
            ->once()
            ->andReturn([
                'status' => 'suspended',
                'allow_access' => false,
                'message' => 'Billing action required.',
            ]);

        $middleware = new EnsureTenantSubscriptionIsActive($tenantPlanService, $lifecycleService);

        $request = Request::create('/test-dashboard', 'GET');
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

        $response = $middleware->handle($request, fn () => new Response('ok', 200));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(route('automotive.admin.billing.status'), $response->headers->get('Location'));
    }

    public function test_it_allows_blocked_tenants_to_access_billing_routes(): void
    {
        $tenant = $this->initializeTenantContext('tenant-middleware-billing');

        $tenantPlanService = Mockery::mock(TenantPlanService::class);
        $tenantPlanService->shouldReceive('getCurrentSubscription')
            ->once()
            ->with($tenant->id)
            ->andReturn((object) ['tenant_id' => $tenant->id, 'status' => 'suspended']);

        $lifecycleService = Mockery::mock(TenantBillingLifecycleService::class);
        $lifecycleService->shouldReceive('resolveState')
            ->once()
            ->andReturn([
                'status' => 'suspended',
                'allow_access' => false,
                'message' => 'Billing action required.',
            ]);

        $middleware = new EnsureTenantSubscriptionIsActive($tenantPlanService, $lifecycleService);

        $request = Request::create('/test-billing-status', 'GET');
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

        $response = $middleware->handle($request, fn () => new Response('billing-ok', 200));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('billing-ok', $response->getContent());
    }

    protected function initializeTenantContext(string $tenantId): Tenant
    {
        Config::set('tenancy.bootstrappers', []);

        DB::table('tenants')->insert([
            'id' => $tenantId,
            'data' => json_encode(['db_name' => 'tenant_' . $tenantId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tenant = Tenant::query()->findOrFail($tenantId);

        tenancy()->initialize($tenant);

        return $tenant;
    }
}
