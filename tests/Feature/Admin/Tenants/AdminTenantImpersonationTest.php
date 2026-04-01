<?php

namespace Tests\Feature\Admin\Tenants;

use App\Http\Controllers\Admin\TenantController;
use App\Models\Admin;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Admin\AdminActivityLogger;
use App\Services\Admin\AdminTenantLifecycleService;
use App\Services\Admin\TenantImpersonationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AdminTenantImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_start_generates_one_time_impersonation_url_for_primary_domain(): void
    {
        config()->set('app.url', 'https://central.example.test');

        $admin = Admin::query()->create([
            'name' => 'Central Admin',
            'email' => 'central-admin@example.test',
            'password' => bcrypt('password'),
        ]);

        Auth::guard('admin')->login($admin);

        $tenant = Tenant::query()->create([
            'id' => 'tenant-impersonate-001',
            'data' => [
                'company_name' => 'Impersonation Tenant',
            ],
        ]);

        DB::table('domains')->insert([
            'tenant_id' => $tenant->id,
            'domain' => 'tenant-impersonate-001.example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::query()->create([
            'name' => 'Tenant Owner',
            'email' => 'tenant-owner@example.test',
            'password' => bcrypt('password'),
        ]);

        DB::table('tenant_users')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(TenantImpersonationService::class);

        $url = $service->start($tenant->id);

        $this->assertStringStartsWith('https://tenant-impersonate-001.example.test/automotive/admin/impersonate/', $url);

        $token = basename(parse_url($url, PHP_URL_PATH));
        $payload = Cache::get('tenant_impersonation:' . $token);

        $this->assertIsArray($payload);
        $this->assertSame($tenant->id, $payload['tenant_id']);
        $this->assertSame('tenant-owner@example.test', $payload['target_user_email']);
        $this->assertSame('central-admin@example.test', $payload['central_admin_email']);
        $this->assertSame('https://central.example.test/admin/tenants/' . $tenant->id, $payload['return_url']);
    }

    public function test_consume_returns_payload_once_and_rejects_second_use(): void
    {
        Cache::put('tenant_impersonation:test-token', [
            'tenant_id' => 'tenant-impersonate-once',
            'target_user_email' => 'tenant-owner@example.test',
        ], now()->addMinutes(5));

        $service = app(TenantImpersonationService::class);

        $payload = $service->consume('test-token', 'tenant-impersonate-once');

        $this->assertSame('tenant-owner@example.test', $payload['target_user_email']);
        $this->assertNull(Cache::get('tenant_impersonation:test-token'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This impersonation link is invalid or has expired.');

        $service->consume('test-token', 'tenant-impersonate-once');
    }

    public function test_impersonate_controller_redirects_to_generated_tenant_url_and_logs_activity(): void
    {
        $tenant = Tenant::query()->create([
            'id' => 'tenant-controller-impersonate',
            'data' => ['company_name' => 'Controller Tenant'],
        ]);

        $impersonationService = Mockery::mock(TenantImpersonationService::class);
        $impersonationService->shouldReceive('start')
            ->once()
            ->with($tenant->id)
            ->andReturn('https://tenant-controller-impersonate.example.test/automotive/admin/impersonate/mock-token');

        $activityLogger = Mockery::mock(AdminActivityLogger::class);
        $activityLogger->shouldReceive('log')
            ->once()
            ->withArgs(function (string $action, ?string $subjectType, $subjectId, ?string $tenantId, array $contextPayload) use ($tenant): bool {
                return $action === 'tenant.impersonation.started'
                    && $subjectType === 'tenant'
                    && $subjectId === $tenant->id
                    && $tenantId === $tenant->id
                    && ($contextPayload['redirect_url'] ?? null) === 'https://tenant-controller-impersonate.example.test/automotive/admin/impersonate/mock-token';
            });

        $controller = new TenantController(
            Mockery::mock(AdminTenantLifecycleService::class),
            $activityLogger,
            $impersonationService
        );

        $response = $controller->impersonate($tenant->id);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://tenant-controller-impersonate.example.test/automotive/admin/impersonate/mock-token', $response->getTargetUrl());
    }
}
