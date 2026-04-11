<?php

namespace App\Providers;

use App\Services\Tenancy\TenantWorkspaceProductService;
use App\Services\Tenancy\WorkspaceModuleCatalogService;
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
                $view->with('focusedWorkspaceProduct', null);
                $view->with('workspaceSidebarSections', []);
                $view->with('workspaceQuickCreateActions', []);

                return;
            }

            $workspaceProductService = app(TenantWorkspaceProductService::class);
            $workspaceModuleCatalogService = app(WorkspaceModuleCatalogService::class);

            $workspaceProducts = $workspaceProductService
                ->getWorkspaceProducts((string) $tenant->id);
            $focusedWorkspaceProduct = $workspaceProductService->resolveFocusedProduct(
                $workspaceProducts,
                request()->attributes->get('workspace_product_code', request()->query('workspace_product'))
            );

            $view->with('tenantWorkspaceProducts', $workspaceProducts);
            $view->with('focusedWorkspaceProduct', $focusedWorkspaceProduct);
            $view->with('workspaceSidebarSections', $workspaceModuleCatalogService->getSidebarSections($focusedWorkspaceProduct));
            $view->with('workspaceQuickCreateActions', $workspaceModuleCatalogService->getQuickCreateActions($focusedWorkspaceProduct));
        });

        $this->app->booted(function () {
            $routes = $this->app['router']->getRoutes();

            $routes->refreshNameLookups();
            $routes->refreshActionLookups();
        });
    }
}
