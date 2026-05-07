<?php

namespace Tests\Feature\Tenancy;

use App\Models\Employee;
use App\Models\NotificationTemplate;
use App\Models\NumberingSequence;
use App\Models\ProductPermission;
use App\Models\ProductRole;
use App\Models\TenantAttachment;
use App\Models\TenantNotification;
use App\Models\TenantProductBranch;
use App\Models\TenantUserProductAccess;
use App\Services\Core\Documents\DocumentGenerationService;
use App\Services\Tenancy\AttachmentService;
use App\Services\Tenancy\CentralCustomerService;
use App\Services\Tenancy\NotificationService;
use App\Services\Tenancy\NumberingSequenceService;
use App\Services\Tenancy\ProductBranchAccessService;
use App\Services\Tenancy\ProductEntitlementService;
use App\Services\Tenancy\ProductPermissionService;
use App\Services\Tenancy\TenantUserProductAccessService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PlatformProductionAcceptanceTest extends TestCase
{
    public function test_platform_foundation_classes_and_routes_are_available(): void
    {
        $classes = [
            ProductEntitlementService::class,
            TenantUserProductAccessService::class,
            ProductBranchAccessService::class,
            ProductPermissionService::class,
            CentralCustomerService::class,
            DocumentGenerationService::class,
            NumberingSequenceService::class,
            AttachmentService::class,
            NotificationService::class,
            TenantUserProductAccess::class,
            TenantProductBranch::class,
            ProductRole::class,
            ProductPermission::class,
            Employee::class,
            NumberingSequence::class,
            TenantAttachment::class,
            TenantNotification::class,
            NotificationTemplate::class,
        ];

        foreach ($classes as $class) {
            $this->assertTrue(class_exists($class), $class . ' should exist.');
        }

        $this->assertRouteListContains('automotive.admin.dashboard');
        $this->assertRouteListContains('automotive.admin.maintenance.notifications.index');
        $this->assertTrue(Route::has('admin.notifications.index'));
    }

    public function test_deploy_workflow_runs_tenant_migrations_without_route_cache_or_full_test_suite(): void
    {
        $workflow = $this->readProjectFile('.github/workflows/deploy.yml');

        $centralMigrations = strpos($workflow, 'php artisan migrate --force');
        $tenantMigrations = strpos($workflow, 'php artisan tenants:migrate --force');

        $this->assertIsInt($centralMigrations);
        $this->assertIsInt($tenantMigrations);
        $this->assertGreaterThan($centralMigrations, $tenantMigrations);
        $this->assertStringContainsString('echo "==> Central migrations"', $workflow);
        $this->assertStringContainsString('echo "==> Tenant migrations"', $workflow);
        $this->assertStringNotContainsString('php artisan route:cache', $workflow);
        $this->assertStringNotContainsString('php artisan test', $workflow);
    }

    public function test_gitignore_excludes_tenant_runtime_storage_without_ignoring_storage_gitignore_files(): void
    {
        $gitignore = $this->readProjectFile('.gitignore');

        $this->assertStringContainsString('/storage/tenant*/', $gitignore);
        $this->assertStringNotContainsString('/storage/.gitignore', $gitignore);
    }

    public function test_platform_acceptance_documentation_covers_deploy_and_legacy_migration_notes(): void
    {
        $acceptance = $this->readProjectFile('docs/platform-acceptance-checklist.md');
        $migrationNotes = $this->readProjectFile('docs/platform-migration-notes.md');

        $this->assertStringContainsString('php artisan tenants:migrate --force', $acceptance);
        $this->assertStringContainsString('php artisan route:cache', $acceptance);
        $this->assertStringContainsString('USE `tenant_client-1`;', $acceptance);
        $this->assertStringContainsString('legacy Automotive', $migrationNotes);
        $this->assertStringContainsString('Admin Notifications', $migrationNotes);
        $this->assertStringContainsString('canonical', $migrationNotes);
    }

    public function test_foundation_seeders_are_idempotent_by_construction(): void
    {
        $seeders = [
            'database/seeders/TenantBranchDemoSeeder.php',
            'database/seeders/TenantBusinessEntityDemoSeeder.php',
            'database/seeders/TenantDocumentFoundationSeeder.php',
            'database/seeders/TenantNotificationFoundationSeeder.php',
        ];

        foreach ($seeders as $seeder) {
            $source = $this->readProjectFile($seeder);

            $this->assertMatchesRegularExpression('/updateOrCreate|firstOrCreate|findOrCreate/', $source, $seeder . ' should be idempotent.');
        }
    }

    private function assertRouteListContains(string $name): void
    {
        Artisan::call('route:list', ['--name' => $name, '--json' => true]);

        $routes = json_decode(Artisan::output(), true);

        $this->assertIsArray($routes);
        $this->assertContains($name, array_column($routes, 'name'));
    }

    private function readProjectFile(string $path): string
    {
        $contents = file_get_contents(base_path($path));

        $this->assertIsString($contents, $path . ' should be readable.');

        return $contents;
    }
}
