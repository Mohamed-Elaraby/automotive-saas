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

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        foreach (['automotive', 'spareparts', 'system'] as $segment) {
            $pattern = '/(^|\.)' . preg_quote($segment, '/') . '\.(seven-scapital\.com)$/';
            $host = preg_replace($pattern, '$1$2', $host) ?? $host;
        }

        return ltrim($host, '.');
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
