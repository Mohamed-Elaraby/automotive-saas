<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RefreshRouteLookups
{
    public function handle(Request $request, Closure $next): Response
    {
        $router = app('router');
        $routes = $router->getRoutes();

        $routes->refreshNameLookups();
        $routes->refreshActionLookups();

        app('url')->setRoutes($routes);

        return $next($request);
    }
}
