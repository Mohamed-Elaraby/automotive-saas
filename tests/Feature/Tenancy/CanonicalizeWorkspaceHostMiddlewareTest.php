<?php

namespace Tests\Feature\Tenancy;

use Tests\TestCase;

class CanonicalizeWorkspaceHostMiddlewareTest extends TestCase
{
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
}
