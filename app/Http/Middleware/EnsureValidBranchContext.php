<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\BranchContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidBranchContext
{
    public function __construct(
        protected BranchContextService $branchContext
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('automotive_admin')->user();

        if (! $user || ! function_exists('tenant') || ! tenant()) {
            return $next($request);
        }

        if ($request->routeIs(
            'automotive.admin.login',
            'automotive.admin.login.submit',
            'automotive.admin.logout',
            'automotive.admin.subscription.*',
            'automotive.admin.billing.*',
            'automotive.admin.impersonate',
            'automotive.admin.stop-impersonation',
            'automotive.admin.access.*'
        )) {
            return $next($request);
        }

        $context = $this->branchContext->contextForUser($user);

        if ($context['has_product_access'] && $context['has_no_branch_access']) {
            return redirect()->route('automotive.admin.access.branch-context.select');
        }

        if ($context['requires_selector']) {
            return redirect()->route('automotive.admin.access.branch-context.select');
        }

        return $next($request);
    }
}
