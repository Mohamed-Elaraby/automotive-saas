<?php

namespace App\Console\Commands\Tenancy;

use App\Services\Tenancy\WorkspaceHostResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

class DiagnoseWorkspaceRoutingCommand extends Command
{
    protected $signature = 'tenancy:diagnose-workspace-routing
                            {--host=seven-scapital.com : Central host to validate}
                            {--tenant=demo : Sample tenant subdomain to validate}';

    protected $description = 'Diagnose workspace route registration and root-domain host configuration for central and tenant access';

    public function __construct(protected WorkspaceHostResolver $workspaceHostResolver)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $host = strtolower(trim((string) $this->option('host')));
        $tenant = strtolower(trim((string) $this->option('tenant')));

        $appUrl = (string) Config::get('app.url');
        $sessionDomain = (string) Config::get('session.domain');
        $centralDomains = array_values((array) Config::get('tenancy.central_domains', []));

        $canonicalHost = $this->workspaceHostResolver->canonicalBaseHost($host);
        $tenantHost = $this->workspaceHostResolver->tenantDomain($tenant, $host);

        $portalRoute = Route::has('automotive.portal') ? route('automotive.portal') : '[missing]';
        $loginRoute = Route::has('automotive.login') ? route('automotive.login') : '[missing]';
        $tenantRootPath = Route::has('automotive.admin.home')
            ? parse_url(route('automotive.admin.home'), PHP_URL_PATH)
            : '/workspace';

        $this->table(
            ['Check', 'Value'],
            [
                ['APP_URL', $appUrl],
                ['SESSION_DOMAIN', $sessionDomain !== '' ? $sessionDomain : '[empty]'],
                ['Configured host', $host],
                ['Canonical host', $canonicalHost],
                ['Sample tenant host', $tenantHost],
                ['Central domains', implode(', ', $centralDomains)],
                ['Portal route URL', $portalRoute],
                ['Login route URL', $loginRoute],
                ['Tenant workspace root path', $tenantRootPath],
            ]
        );

        $warnings = [];

        if ($appUrl === '' || str_contains($appUrl, 'localhost')) {
            $warnings[] = 'APP_URL still points to localhost. Production should use https://seven-scapital.com.';
        }

        if (! in_array($canonicalHost, $centralDomains, true)) {
            $warnings[] = 'The canonical host is not listed in tenancy.central_domains.';
        }

        if ($sessionDomain === '') {
            $warnings[] = 'SESSION_DOMAIN is empty. Cross-subdomain central/tenant cookie behavior may be inconsistent.';
        } elseif ($sessionDomain !== '.seven-scapital.com') {
            $warnings[] = 'SESSION_DOMAIN is not .seven-scapital.com.';
        }

        if (! Route::has('automotive.portal') || ! str_contains($portalRoute, '/workspace/portal')) {
            $warnings[] = 'The canonical portal route is not resolving to /workspace/portal.';
        }

        if (! Route::has('automotive.login') || ! str_contains($loginRoute, '/workspace/login')) {
            $warnings[] = 'The canonical login route is not resolving to /workspace/login.';
        }

        if ($tenantRootPath !== '/workspace') {
            $warnings[] = 'The canonical tenant workspace root path is not resolving to /workspace.';
        }

        if ($warnings !== []) {
            foreach ($warnings as $warning) {
                $this->warn($warning);
            }

            $this->newLine();
            $this->line('Expected production web-server behavior:');
            $this->line('- seven-scapital.com/workspace/portal should reach Laravel and redirect guests to /workspace/login.');
            $this->line('- demo.seven-scapital.com/workspace should reach Laravel tenant routes.');
            $this->line('- Any 404 before that point is coming from Nginx/Apache or upstream proxy, not from Laravel routes.');

            return self::FAILURE;
        }

        $this->info('Workspace routing diagnosis passed. Laravel routes and host configuration are internally consistent.');

        return self::SUCCESS;
    }
}
