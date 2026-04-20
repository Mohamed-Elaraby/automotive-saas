<?php

namespace Tests\Feature\Tenancy;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DiagnoseWorkspaceRoutingCommandTest extends TestCase
{
    public function test_it_reports_success_when_workspace_routing_configuration_is_consistent(): void
    {
        Config::set('app.url', 'https://seven-scapital.com');
        Config::set('session.domain', '.seven-scapital.com');
        Config::set('tenancy.central_domains', [
            'seven-scapital.com',
            'www.seven-scapital.com',
            'system.seven-scapital.com',
            'automotive.seven-scapital.com',
        ]);

        $this->artisan('tenancy:diagnose-workspace-routing --host=seven-scapital.com --tenant=demo')
            ->expectsOutput('Workspace routing diagnosis passed. Laravel routes and host configuration are internally consistent.')
            ->assertExitCode(0);
    }

    public function test_it_reports_failure_when_production_host_settings_are_not_consistent(): void
    {
        Config::set('app.url', 'http://localhost');
        Config::set('session.domain', null);
        Config::set('tenancy.central_domains', [
            'automotive.seven-scapital.com',
        ]);

        $this->artisan('tenancy:diagnose-workspace-routing --host=seven-scapital.com --tenant=demo')
            ->expectsOutputToContain('APP_URL still points to localhost')
            ->expectsOutputToContain('The canonical host is not listed in tenancy.central_domains.')
            ->expectsOutputToContain('SESSION_DOMAIN is empty.')
            ->assertExitCode(1);
    }
}
