<?php

namespace Tests\Feature\Tenancy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Tenant;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

class CanonicalizeWorkspaceHostMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_workspace_portal_path_redirects_guest_to_login_on_root_domain(): void
    {
        $response = $this->get('https://seven-scapital.com/workspace/portal');

        $response->assertRedirectContains('/workspace/login');
    }

    public function test_it_redirects_legacy_central_workspace_host_to_root_domain(): void
    {
        $response = $this->get('https://automotive.seven-scapital.com/workspace/login?product=accounting');

        $response->assertRedirect('https://seven-scapital.com/workspace/login?product=accounting');
        $this->assertSame(308, $response->getStatusCode());
    }

    public function test_it_redirects_legacy_tenant_product_prefixed_host_to_root_domain(): void
    {
        $response = $this->get('https://demo.automotive.seven-scapital.com/workspace');

        $response->assertRedirect('https://demo.seven-scapital.com/workspace');
        $this->assertSame(308, $response->getStatusCode());
    }

    public function test_it_redirects_underscore_workspace_hosts_to_valid_hyphen_hosts(): void
    {
        $response = $this->get('https://client_1.seven-scapital.com/ar/workspace/login');

        $response->assertRedirect('https://client-1.seven-scapital.com/ar/workspace/login');
        $this->assertSame(308, $response->getStatusCode());
    }

    public function test_it_creates_hyphen_domain_alias_for_existing_underscore_tenants(): void
    {
        $tenant = Tenant::withoutEvents(fn () => Tenant::query()->create([
            'id' => 'client-1',
            'data' => [],
        ]));

        Domain::query()->create([
            'domain' => 'client_1.seven-scapital.com',
            'tenant_id' => $tenant->id,
        ]);

        $response = $this->get('https://client_1.seven-scapital.com/ar/workspace/login');

        $response->assertRedirect('https://client-1.seven-scapital.com/ar/workspace/login');
        $this->assertSame(308, $response->getStatusCode());
        $this->assertDatabaseHas('domains', [
            'domain' => 'client-1.seven-scapital.com',
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_missing_tenant_workspace_domain_returns_not_found_instead_of_server_error(): void
    {
        $response = $this->get('https://missing-tenant.seven-scapital.com/workspace/admin/general-ledger?workspace_product=accounting');

        $response->assertNotFound();
        $response->assertSee('Workspace not found', false);
    }
}
