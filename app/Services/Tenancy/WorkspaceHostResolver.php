<?php

namespace App\Services\Tenancy;

class WorkspaceHostResolver
{
    public function canonicalBaseHost(?string $host): string
    {
        $host = strtolower(trim((string) $host));

        if ($host === '') {
            return '';
        }

        $host = preg_replace('#^https?://#', '', $host) ?? $host;
        $host = preg_replace('#:\d+$#', '', $host) ?? $host;
        $host = trim($host, '/');

        foreach (['www.', 'automotive.', 'spareparts.'] as $prefix) {
            if (str_starts_with($host, $prefix)) {
                return substr($host, strlen($prefix));
            }
        }

        return $host;
    }

    public function tenantDomain(string $subdomain, ?string $host): string
    {
        $subdomain = strtolower(trim($subdomain));
        $baseHost = $this->canonicalBaseHost($host);

        if ($subdomain === '') {
            return $baseHost;
        }

        if ($baseHost === '') {
            return $subdomain;
        }

        if (str_starts_with($baseHost, $subdomain . '.')) {
            return $baseHost;
        }

        return $subdomain . '.' . $baseHost;
    }
}
