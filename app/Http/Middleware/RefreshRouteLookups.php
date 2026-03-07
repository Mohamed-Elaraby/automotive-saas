<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RefreshRouteLookups
{
    public function handle(Request $request, Closure $next): Response
    {
        $routes = app('router')->getRoutes();

        $routes->refreshNameLookups();
        $routes->refreshActionLookups();

        return $next($request);
    }
}
