<?php

namespace App\Services\Automotive;

use Illuminate\Http\Request;

class TenantUrlBuilder
{
    public function tenantLoginUrl(Request $request, string $subdomain): string
    {
        $scheme = $request->getScheme();
        $host = $request->getHost();
        $port = $request->getPort();

        $tenantHost = $this->buildTenantHost($host, $subdomain);

        $url = $scheme . '://' . $tenantHost;

        if ($this->shouldAppendPort($scheme, $port)) {
            $url .= ':' . $port;
        }

        return $url . '/automotive/admin/login';
    }

    protected function buildTenantHost(string $currentHost, string $subdomain): string
    {
        $currentHost = strtolower($currentHost);
        $subdomain = strtolower($subdomain);

        // لو أصلاً نفس الـ subdomain موجود، رجعه كما هو
        if (str_starts_with($currentHost, $subdomain . '.')) {
            return $currentHost;
        }

        return $subdomain . '.' . $currentHost;
    }

    protected function shouldAppendPort(string $scheme, int|string|null $port): bool
    {
        if (empty($port)) {
            return false;
        }

$port = (int) $port;

return ! (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443));
}
}
