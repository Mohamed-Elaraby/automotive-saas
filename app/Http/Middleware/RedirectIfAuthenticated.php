<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (! Auth::guard($guard)->check()) {
                continue;
            }

            if ($guard === 'admin') {
                return redirect()->route('admin.dashboard');
            }

            if ($guard === 'automotive_admin') {
                if (
                    $request->is('workspace/admin/dashboard')
                    || $request->is('workspace/admin/subscription-expired')
                    || $request->is('automotive/admin/dashboard')
                    || $request->is('automotive/admin/subscription-expired')
                ) {
                    return $next($request);
                }

                return redirect('/workspace');
            }

            if ($guard === 'web' || $guard === null) {
                if (
                    $request->is('workspace')
                    || $request->is('workspace/*')
                    || $request->is('automotive')
                    || $request->is('automotive/*')
                ) {
                    return redirect()->route('automotive.portal');
                }
            }

            return redirect(RouteServiceProvider::HOME);
        }

        return $next($request);
    }
}
