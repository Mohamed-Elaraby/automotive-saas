<?php

namespace App\Providers;

use App\Services\Tenancy\TenantWorkspaceProductService;
use App\Services\Tenancy\AccessVisibilityService;
use App\Services\Tenancy\BranchContextService;
use App\Services\Tenancy\WorkspaceModuleCatalogService;
use App\Services\Tenancy\WorkspaceOwnerAccessService;
use App\Services\Core\Documents\DocumentRendererInterface;
use App\Services\Core\Documents\MpdfDocumentRenderer;
use Illuminate\Support\Facades\Blade;
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

        $this->registerAccessVisibilityDirectives();

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
                $view->with('branchContext', []);

                return;
            }

            $workspaceProductService = app(TenantWorkspaceProductService::class);
            $workspaceModuleCatalogService = app(WorkspaceModuleCatalogService::class);
            $accessVisibility = app(AccessVisibilityService::class);
            $user = auth('automotive_admin')->user();

            $workspaceProducts = $workspaceProductService
                ->getWorkspaceProducts((string) $tenant->id);
            $focusedWorkspaceProduct = $workspaceProductService->resolveFocusedProduct(
                $workspaceProducts,
                request()->attributes->get('workspace_product_code', request()->query('workspace_product'))
            );
            $sidebarSections = $workspaceModuleCatalogService->getSidebarSections($focusedWorkspaceProduct);
            $quickCreateActions = $workspaceModuleCatalogService->getQuickCreateActions($focusedWorkspaceProduct);

            if ($user) {
                $sidebarSections = $accessVisibility->filterSidebarSections($sidebarSections, $user, $focusedWorkspaceProduct);
                $quickCreateActions = $accessVisibility->filterQuickCreateActions($quickCreateActions, $user, $focusedWorkspaceProduct);
            } else {
                $sidebarSections = [];
                $quickCreateActions = [];
            }

            $view->with('tenantWorkspaceProducts', $workspaceProducts);
            $view->with('focusedWorkspaceProduct', $focusedWorkspaceProduct);
            $view->with('focusedWorkspaceProductFamily', $workspaceModuleCatalogService->getFocusedProductFamily($focusedWorkspaceProduct));
            $view->with('workspaceSidebarSections', $sidebarSections);
            $view->with('workspaceQuickCreateActions', $quickCreateActions);
            $view->with('canManageAccessControl', $this->canManageAccessControl((string) $tenant->id));
            $view->with('branchContext', $this->branchContextPayload(is_array($focusedWorkspaceProduct) ? ($focusedWorkspaceProduct['product_key'] ?? null) : null));
        });

        $this->app->booted(function () {
            $routes = $this->app['router']->getRoutes();

            $routes->refreshNameLookups();
            $routes->refreshActionLookups();
        });
    }

    private function registerAccessVisibilityDirectives(): void
    {
        Blade::if('productCan', function (string $permissionKey, string $productKey = 'automotive_service', ?int $branchId = null): bool {
            return $this->productCan($permissionKey, $productKey, $branchId);
        });

        Blade::if('productCannot', function (string $permissionKey, string $productKey = 'automotive_service', ?int $branchId = null): bool {
            return ! $this->productCan($permissionKey, $productKey, $branchId);
        });

        Blade::if('branchCan', function (string $permissionKey, string $productKey = 'automotive_service', ?int $branchId = null): bool {
            $user = auth('automotive_admin')->user();

            if (! $user) {
                return false;
            }

            $branchId = $branchId ?: app(AccessVisibilityService::class)->currentBranchId($user, $productKey);

            return $branchId !== null && $this->productCan($permissionKey, $productKey, $branchId);
        });

        Blade::if('ownerAccess', function (): bool {
            $user = auth('automotive_admin')->user();

            return $user !== null && app(WorkspaceOwnerAccessService::class)->isWorkspaceOwner($user);
        });

        Blade::if('notOwnerAccess', function (): bool {
            $user = auth('automotive_admin')->user();

            return $user === null || ! app(WorkspaceOwnerAccessService::class)->isWorkspaceOwner($user);
        });
    }

    private function productCan(string $permissionKey, string $productKey = 'automotive_service', ?int $branchId = null): bool
    {
        $user = auth('automotive_admin')->user();

        if (! $user) {
            return false;
        }

        try {
            return app(AccessVisibilityService::class)->canSeeAction($user, $permissionKey, $productKey, $branchId);
        } catch (\Throwable) {
            return false;
        }
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
            return app(AccessVisibilityService::class)
                ->canSeeMenu($user, 'shared.access-control', 'automotive_service');
        } catch (\Throwable) {
            return false;
        }
    }

    private function branchContextPayload(?string $focusedProductKey): array
    {
        $user = auth('automotive_admin')->user();

        if (! $user) {
            return [];
        }

        try {
            return app(BranchContextService::class)->contextForUser($user, $focusedProductKey ?: 'automotive_service');
        } catch (\Throwable) {
            return [];
        }
    }
}
