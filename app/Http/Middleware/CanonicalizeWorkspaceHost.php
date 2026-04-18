<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\WorkspaceHostResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
            return redirect()->to($request->getScheme() . '://' . $canonicalHost . $request->getRequestUri(), 308);
        }

        return $next($request);
    }
}
