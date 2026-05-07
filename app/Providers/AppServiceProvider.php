<?php

namespace App\Providers;

use App\Services\Tenancy\TenantWorkspaceProductService;
use App\Services\Tenancy\ProductPermissionService;
use App\Services\Tenancy\WorkspaceModuleCatalogService;
use App\Services\Core\Documents\DocumentRendererInterface;
use App\Services\Core\Documents\MpdfDocumentRenderer;
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
        $this->app->bind(DocumentRendererInterface::class, MpdfDocumentRenderer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        View::composer([
            'automotive.admin.layouts.adminLayout.partials.sidebar',
            'automotive.admin.layouts.adminLayout.partials.header',
        ], function ($view) {
            $tenant = function_exists('tenant') ? tenant() : null;

            if (! $tenant) {
                $view->with('tenantWorkspaceProducts', collect());
                $view->with('focusedWorkspaceProduct', null);
                $view->with('focusedWorkspaceProductFamily', 'automotive_service');
                $view->with('workspaceSidebarSections', []);
                $view->with('workspaceQuickCreateActions', []);
                $view->with('canManageAccessControl', false);

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
            $view->with('focusedWorkspaceProductFamily', $workspaceModuleCatalogService->getFocusedProductFamily($focusedWorkspaceProduct));
            $view->with('workspaceSidebarSections', $workspaceModuleCatalogService->getSidebarSections($focusedWorkspaceProduct));
            $view->with('workspaceQuickCreateActions', $workspaceModuleCatalogService->getQuickCreateActions($focusedWorkspaceProduct));
            $view->with('canManageAccessControl', $this->canManageAccessControl((string) $tenant->id));
        });

        $this->app->booted(function () {
            $routes = $this->app['router']->getRoutes();

            $routes->refreshNameLookups();
            $routes->refreshActionLookups();
        });
    }

    private function canManageAccessControl(string $tenantId): bool
    {
        $user = auth('automotive_admin')->user();

        if (! $user) {
            return false;
        }

        if ((int) $user->id === 1) {
            return true;
        }

        try {
            return app(ProductPermissionService::class)
                ->can($user, 'automotive_service', 'automotive.access.manage', null, $tenantId);
        } catch (\Throwable) {
            return false;
        }
    }
}
