<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

class NormalizeWorkspaceDomainsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_hyphen_aliases_for_underscore_workspace_domains(): void
    {
        $tenant = Tenant::withoutEvents(fn () => Tenant::query()->create([
            'id' => 'client-1',
            'data' => [],
        ]));

        Domain::query()->create([
            'domain' => 'client_1.seven-scapital.com',
            'tenant_id' => $tenant->id,
        ]);

        $this->artisan('tenancy:normalize-workspace-domains')
            ->expectsOutput('create: client_1.seven-scapital.com -> client-1.seven-scapital.com')
            ->expectsOutput('Created 1 domain alias(es); skipped 0.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('domains', [
            'domain' => 'client-1.seven-scapital.com',
            'tenant_id' => $tenant->id,
        ]);
    }
}
