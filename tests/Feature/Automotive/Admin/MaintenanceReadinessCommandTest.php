<?php

namespace Tests\Feature\Automotive\Admin;

use Tests\TestCase;

class MaintenanceReadinessCommandTest extends TestCase
{
    public function test_maintenance_readiness_command_passes_without_tenant_and_warns_about_tenant_checks(): void
    {
        $this->artisan('maintenance:verify-readiness')
            ->expectsOutputToContain('Automotive maintenance readiness verification passed.')
            ->expectsOutputToContain('Tenant table checks skipped.')
            ->assertSuccessful();
    }
}
