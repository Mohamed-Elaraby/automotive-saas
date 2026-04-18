<?php

namespace Tests\Feature\Tenancy;

use App\Services\Tenancy\WorkspaceHostResolver;
use Tests\TestCase;

class WorkspaceHostResolverTest extends TestCase
{
    public function test_it_normalizes_product_prefixed_hosts_to_the_root_host(): void
    {
        $resolver = app(WorkspaceHostResolver::class);

        $this->assertSame('seven-scapital.com', $resolver->canonicalBaseHost('automotive.seven-scapital.com'));
        $this->assertSame('seven-scapital.com', $resolver->canonicalBaseHost('spareparts.seven-scapital.com'));
        $this->assertSame('seven-scapital.com', $resolver->canonicalBaseHost('www.seven-scapital.com'));
        $this->assertSame('demo.seven-scapital.com', $resolver->canonicalBaseHost('demo.seven-scapital.com'));
        $this->assertSame('example.test', $resolver->canonicalBaseHost('example.test'));
    }

    public function test_it_builds_tenant_domains_on_the_canonical_root_host(): void
    {
        $resolver = app(WorkspaceHostResolver::class);

        $this->assertSame('demo.seven-scapital.com', $resolver->tenantDomain('demo', 'automotive.seven-scapital.com'));
        $this->assertSame('trial.example.test', $resolver->tenantDomain('trial', 'example.test'));
    }
}
