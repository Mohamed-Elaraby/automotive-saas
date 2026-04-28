<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\WorkspaceHostResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Stancl\Tenancy\Database\Models\Domain;

class CanonicalizeWorkspaceHost
{
    public function __construct(protected WorkspaceHostResolver $workspaceHostResolver)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $currentHost = strtolower((string) $request->getHost());
        $canonicalHost = $this->workspaceHostResolver->canonicalBaseHost($currentHost);

        if ($currentHost !== '' && $canonicalHost !== '' && $canonicalHost !== $currentHost) {
            $this->ensureCanonicalTenantDomainAlias($currentHost, $canonicalHost);

            return redirect()->to($request->getScheme() . '://' . $canonicalHost . $request->getRequestUri(), 308);
        }

        return $next($request);
    }

    private function ensureCanonicalTenantDomainAlias(string $currentHost, string $canonicalHost): void
    {
        if (! str_contains($currentHost, '_')) {
            return;
        }

        if (Domain::query()->where('domain', $canonicalHost)->exists()) {
            return;
        }

        $legacyDomain = Domain::query()->where('domain', $currentHost)->first();

        if (! $legacyDomain) {
            return;
        }

        Domain::query()->create([
            'domain' => $canonicalHost,
            'tenant_id' => $legacyDomain->tenant_id,
        ]);
    }
}
