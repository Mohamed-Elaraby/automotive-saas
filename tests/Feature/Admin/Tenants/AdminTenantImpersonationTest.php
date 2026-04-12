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
        $tenantId = 'tenant-impersonate-001-' . uniqid();

        $admin = Admin::query()->create([
            'name' => 'Central Admin',
            'email' => 'central-admin@example.test',
            'password' => bcrypt('password'),
        ]);

        Auth::guard('admin')->login($admin);

        $tenant = Tenant::query()->create([
            'id' => $tenantId,
            'data' => [
                'company_name' => 'Impersonation Tenant',
            ],
        ]);

        DB::table('domains')->insert([
            'tenant_id' => $tenant->id,
            'domain' => $tenantId . '.example.test',
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

        $this->assertStringStartsWith('https://' . $tenantId . '.example.test/workspace/admin/impersonate/', $url);

        $token = basename(parse_url($url, PHP_URL_PATH));
        $payload = $service->consume($token, $tenant->id);

        $this->assertSame($tenant->id, $payload['tenant_id']);
        $this->assertSame('tenant-owner@example.test', $payload['target_user_email']);
        $this->assertSame('central-admin@example.test', $payload['central_admin_email']);
        $this->assertSame('https://central.example.test/admin/tenants/' . $tenant->id, $payload['return_url']);
    }

    public function test_consume_returns_payload_for_valid_short_lived_token(): void
    {
        config()->set('app.url', 'https://central.example.test');
        $tenantId = 'tenant-impersonate-once-' . uniqid();

        $admin = Admin::query()->create([
            'name' => 'Central Admin',
            'email' => 'central-admin2@example.test',
            'password' => bcrypt('password'),
        ]);

        Auth::guard('admin')->login($admin);

        $tenant = Tenant::query()->create([
            'id' => $tenantId,
            'data' => ['company_name' => 'One Time Tenant'],
        ]);

        DB::table('domains')->insert([
            'tenant_id' => $tenant->id,
            'domain' => $tenantId . '.example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::query()->create([
            'name' => 'Tenant Admin',
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
        $token = basename(parse_url($url, PHP_URL_PATH));

        $payload = $service->consume($token, $tenantId);

        $this->assertSame('tenant-owner@example.test', $payload['target_user_email']);
    }

    public function test_impersonate_controller_redirects_to_generated_tenant_url_and_logs_activity(): void
    {
        $tenantId = 'tenant-controller-impersonate-' . uniqid();

        $tenant = Tenant::query()->create([
            'id' => $tenantId,
            'data' => ['company_name' => 'Controller Tenant'],
        ]);

        $impersonationService = Mockery::mock(TenantImpersonationService::class);
        $impersonationService->shouldReceive('start')
            ->once()
            ->with($tenant->id)
            ->andReturn('https://' . $tenantId . '.example.test/workspace/admin/impersonate/mock-token');

        $activityLogger = Mockery::mock(AdminActivityLogger::class);
        $activityLogger->shouldReceive('log')
            ->once()
            ->withArgs(function (string $action, ?string $subjectType, $subjectId, ?string $tenantId, array $contextPayload) use ($tenant): bool {
                return $action === 'tenant.impersonation.started'
                    && $subjectType === 'tenant'
                    && $subjectId === $tenant->id
                    && $tenantId === $tenant->id
                    && ($contextPayload['redirect_url'] ?? null) === 'https://' . $tenantId . '.example.test/workspace/admin/impersonate/mock-token';
            });

        $controller = new TenantController(
            Mockery::mock(AdminTenantLifecycleService::class),
            $activityLogger,
            $impersonationService
        );

        $response = $controller->impersonate($tenant->id);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://' . $tenantId . '.example.test/workspace/admin/impersonate/mock-token', $response->getTargetUrl());
    }
}
