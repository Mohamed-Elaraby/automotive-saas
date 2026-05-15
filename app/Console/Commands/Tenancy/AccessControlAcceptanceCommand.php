<?php

namespace App\Console\Commands\Tenancy;

use App\Http\Middleware\EnsureTenantUserHasProductPermission;
use App\Models\AccessAuditLog;
use App\Services\Tenancy\AccessAuditService;
use App\Services\Tenancy\AccessDiagnosticsService;
use App\Services\Tenancy\AccessControlRouteInspector;
use App\Services\Tenancy\AccessVisibilityService;
use App\Services\Tenancy\BranchScopeService;
use App\Services\Tenancy\EffectiveUserAccessService;
use App\Services\Tenancy\ProductPermissionCatalogService;
use App\Services\Tenancy\ProductRoleManagementService;
use App\Services\Tenancy\UserRoleAssignmentService;
use Database\Seeders\TenantAccessControlDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AccessControlAcceptanceCommand extends Command
{
    protected $signature = 'platform:access-control-acceptance';

    protected $description = 'Run lightweight static acceptance checks for Phase 2 Access Control UI and enforcement';

    protected array $requiredClasses = [
        EnsureTenantUserHasProductPermission::class,
        ProductPermissionCatalogService::class,
        ProductRoleManagementService::class,
        EffectiveUserAccessService::class,
        UserRoleAssignmentService::class,
        AccessVisibilityService::class,
        BranchScopeService::class,
        AccessAuditService::class,
        AccessDiagnosticsService::class,
        AccessAuditLog::class,
        TenantAccessControlDemoSeeder::class,
    ];

    public function __construct(
        protected AccessControlRouteInspector $routeInspector
    ) {
        parent::__construct();
    }

    protected array $requiredViews = [
        'automotive.admin.access.index',
        'automotive.admin.access.users.index',
        'automotive.admin.access.users.show',
        'automotive.admin.access.users.products',
        'automotive.admin.access.users.branches',
        'automotive.admin.access.users.roles',
        'automotive.admin.access.roles.index',
        'automotive.admin.access.roles.create',
        'automotive.admin.access.roles.edit',
        'automotive.admin.access.roles.permissions',
        'automotive.admin.access.products.branches',
        'automotive.admin.access.audit.index',
        'automotive.admin.access.diagnostics.index',
        'automotive.admin.access.branch-context.select',
    ];

    public function handle(): int
    {
        $failures = [];

        foreach ($this->routeInspector->missingRouteNames() as $routeName) {
            $failures[] = "Missing route: {$routeName}";
        }

        foreach ($this->requiredClasses as $class) {
            if (! class_exists($class)) {
                $failures[] = "Missing class: {$class}";
            }
        }

        foreach ($this->requiredViews as $view) {
            if (! view()->exists($view)) {
                $failures[] = "Missing view: {$view}";
            }
        }

        if (! File::exists(database_path('migrations/tenant/2026_05_15_180000_create_access_audit_logs_table.php'))) {
            $failures[] = 'Missing tenant migration for access_audit_logs.';
        }

        if (! File::exists(base_path('docs/platform-access-control-ui.md'))) {
            $failures[] = 'Missing docs/platform-access-control-ui.md.';
        }

        if (! File::exists(base_path('docs/access-control-ui-acceptance-checklist.md'))) {
            $failures[] = 'Missing docs/access-control-ui-acceptance-checklist.md.';
        }

        if ($this->deployWorkflowsContainRouteCache()) {
            $failures[] = 'Deploy workflow contains php artisan route:cache.';
        }

        if ($failures !== []) {
            foreach ($failures as $failure) {
                $this->error($failure);
            }

            return self::FAILURE;
        }

        $this->info('Phase 2 Access Control static acceptance checks passed.');

        return self::SUCCESS;
    }

    protected function deployWorkflowsContainRouteCache(): bool
    {
        $workflowPath = base_path('.github/workflows');

        if (! File::isDirectory($workflowPath)) {
            return false;
        }

        foreach (File::allFiles($workflowPath) as $file) {
            if (str_contains(File::get($file->getPathname()), 'php artisan route:cache')) {
                return true;
            }
        }

        return false;
    }
}
