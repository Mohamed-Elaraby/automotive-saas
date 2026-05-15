<?php

namespace App\Services\Tenancy;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

class AccessControlRouteInspector
{
    public function requiredRouteNames(): array
    {
        return [
            'automotive.admin.access.index',
            'automotive.admin.access.users.index',
            'automotive.admin.access.users.show',
            'automotive.admin.access.users.products.edit',
            'automotive.admin.access.users.branches.edit',
            'automotive.admin.access.users.roles.edit',
            'automotive.admin.access.roles.index',
            'automotive.admin.access.roles.create',
            'automotive.admin.access.roles.edit',
            'automotive.admin.access.roles.permissions.edit',
            'automotive.admin.access.products.index',
            'automotive.admin.access.products.branches.index',
            'automotive.admin.access.audit.index',
            'automotive.admin.access.diagnostics.index',
            'automotive.admin.access.branch-context.select',
            'automotive.admin.access.branch-context.switch',
        ];
    }

    public function missingRouteNames(?array $routeNames = null): array
    {
        $this->refreshLookups();

        return collect($routeNames ?: $this->requiredRouteNames())
            ->reject(fn (string $routeName): bool => Route::has($routeName) || $this->routeNameExistsInCollection($routeName))
            ->values()
            ->all();
    }

    public function refreshLookups(): void
    {
        $routes = Route::getRoutes();

        $routes->refreshNameLookups();
        $routes->refreshActionLookups();
    }

    protected function routeNameExistsInCollection(string $routeName): bool
    {
        foreach (Route::getRoutes() as $route) {
            if ($route instanceof RoutingRoute && $route->getName() === $routeName) {
                return true;
            }
        }

        return false;
    }
}
