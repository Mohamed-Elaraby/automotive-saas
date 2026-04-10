<?php

namespace App\Providers;

use App\Services\Tenancy\TenantWorkspaceProductService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        View::composer('automotive.admin.layouts.adminLayout.partials.sidebar', function ($view) {
            $tenant = function_exists('tenant') ? tenant() : null;

            if (! $tenant) {
                $view->with('tenantWorkspaceProducts', collect());

                return;
            }

            $workspaceProducts = app(TenantWorkspaceProductService::class)
                ->getWorkspaceProducts((string) $tenant->id);

            $view->with('tenantWorkspaceProducts', $workspaceProducts);
        });

        $this->app->booted(function () {
            $routes = $this->app['router']->getRoutes();

            $routes->refreshNameLookups();
            $routes->refreshActionLookups();
        });
    }
}
