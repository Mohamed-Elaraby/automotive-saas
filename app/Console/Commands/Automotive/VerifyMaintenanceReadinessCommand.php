<?php

namespace App\Console\Commands\Automotive;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class VerifyMaintenanceReadinessCommand extends Command
{
    protected $signature = 'maintenance:verify-readiness
                            {--tenant= : Optional tenant id for tenant database table checks}';

    protected $description = 'Verify Automotive Maintenance SaaS readiness without using route cache';

    protected array $requiredFiles = [
        'routes/tenant.php',
        'routes/products/automotive/admin.php',
        'app/Services/Automotive/Maintenance/VehicleCheckInService.php',
        'app/Services/Automotive/Maintenance/VinOcrService.php',
        'app/Services/Automotive/Maintenance/MaintenanceAttachmentService.php',
        'app/Services/Automotive/Maintenance/VehicleConditionMapService.php',
        'app/Services/Automotive/Maintenance/InspectionWorkflowService.php',
        'app/Services/Automotive/Maintenance/TechnicianJobService.php',
        'app/Services/Automotive/Maintenance/QualityControlService.php',
        'app/Services/Automotive/Maintenance/MaintenanceDocumentService.php',
        'app/Services/Automotive/Maintenance/MaintenanceIntegrationService.php',
        'app/Services/Automotive/Maintenance/MaintenanceApiIntegrationService.php',
        'app/Models/Core/Documents/GeneratedDocument.php',
        'config/documents.php',
        'config/maintenance_notifications.php',
        'resources/views/automotive/admin/maintenance/index.blade.php',
        'resources/views/automotive/customer/maintenance/tracking.blade.php',
        'resources/views/automotive/customer/maintenance/payment-request.blade.php',
        'lang/en/maintenance.php',
        'lang/ar/maintenance.php',
    ];

    protected array $requiredRoutes = [
        'automotive.admin.maintenance.index',
        'automotive.admin.maintenance.check-ins.index',
        'automotive.admin.maintenance.estimates.index',
        'automotive.admin.maintenance.jobs.index',
        'automotive.admin.maintenance.inspections.index',
        'automotive.admin.maintenance.qc.index',
        'automotive.admin.maintenance.documents.index',
        'automotive.admin.maintenance.reports.index',
        'automotive.admin.maintenance.integrations.index',
        'automotive.admin.maintenance.settings.index',
        'automotive.admin.maintenance.fleet.index',
        'automotive.admin.maintenance.advanced.index',
        'automotive.admin.maintenance.appointments.index',
        'automotive.customer.maintenance.tracking',
        'automotive.customer.maintenance.estimate',
        'automotive.customer.maintenance.payment-request',
        'automotive.maintenance.integrations.api.work-orders.show',
        'automotive.maintenance.integrations.api.invoices.show',
    ];

    protected array $requiredTenantTables = [
        'customers',
        'vehicles',
        'branches',
        'work_orders',
        'work_order_lines',
        'maintenance_appointments',
        'vehicle_check_ins',
        'maintenance_attachments',
        'vehicle_condition_maps',
        'vehicle_condition_map_items',
        'maintenance_service_catalog_items',
        'maintenance_estimates',
        'maintenance_estimate_lines',
        'maintenance_inspection_templates',
        'maintenance_inspection_template_items',
        'maintenance_inspections',
        'maintenance_inspection_items',
        'maintenance_diagnosis_records',
        'maintenance_work_order_jobs',
        'maintenance_job_time_logs',
        'maintenance_qc_records',
        'maintenance_qc_items',
        'maintenance_approval_records',
        'maintenance_lost_sales',
        'maintenance_deliveries',
        'maintenance_warranties',
        'maintenance_warranty_claims',
        'maintenance_complaints',
        'maintenance_notifications',
        'maintenance_parts_requests',
        'maintenance_invoices',
        'maintenance_receipts',
        'maintenance_settings',
        'maintenance_audit_entries',
        'maintenance_fleet_accounts',
        'maintenance_customer_feedback',
        'maintenance_api_tokens',
        'maintenance_api_request_logs',
        'maintenance_payment_requests',
        'generated_documents',
        'document_snapshots',
    ];

    public function handle(): int
    {
        $failures = [];
        $warnings = [];

        $this->verifyRouteCache($failures);
        $this->verifyFiles($failures);
        $this->verifyRoutes($failures);
        $this->verifyCoreConfiguration($failures, $warnings);
        $this->verifyTenantOption($failures, $warnings);

        foreach ($warnings as $warning) {
            $this->warn($warning);
        }

        if ($failures !== []) {
            foreach ($failures as $failure) {
                $this->error($failure);
            }

            return self::FAILURE;
        }

        $this->info('Automotive maintenance readiness verification passed.');

        return self::SUCCESS;
    }

    protected function verifyRouteCache(array &$failures): void
    {
        $routeCacheFiles = glob(base_path('bootstrap/cache/routes*.php')) ?: [];

        if ($routeCacheFiles !== []) {
            $failures[] = 'Route cache file exists. Remove route cache because tenant/product routes are loaded dynamically: ' . implode(', ', $routeCacheFiles);
        }
    }

    protected function verifyFiles(array &$failures): void
    {
        $missing = collect($this->requiredFiles)
            ->reject(fn (string $path): bool => file_exists(base_path($path)))
            ->values()
            ->all();

        if ($missing !== []) {
            $failures[] = 'Missing maintenance files: ' . implode(', ', $missing);
        }

        $this->table(['File Readiness', 'Value'], [
            ['Required files', $missing === [] ? 'OK' : 'Missing: ' . count($missing)],
        ]);
    }

    protected function verifyRoutes(array &$failures): void
    {
        $missing = collect($this->requiredRoutes)
            ->reject(fn (string $name): bool => $this->routeIsAvailable($name))
            ->values()
            ->all();

        if ($missing !== []) {
            $failures[] = 'Missing maintenance routes: ' . implode(', ', $missing);
        }

        $this->table(['Route Readiness', 'Value'], [
            ['Required routes', $missing === [] ? 'OK' : 'Missing: ' . count($missing)],
            ['Admin route prefix', 'automotive.admin.maintenance.*'],
            ['Customer/API routes', 'Tenant domain scoped'],
        ]);
    }

    protected function verifyCoreConfiguration(array &$failures, array &$warnings): void
    {
        if (! class_exists(\Mpdf\Mpdf::class)) {
            $failures[] = 'mPDF package is not available. Run composer install after pulling the code.';
        }

        if (config('documents.renderer') !== 'mpdf') {
            $failures[] = 'config/documents.php must use mpdf as the central document renderer.';
        }

        foreach (['payment.requested', 'invoice.paid', 'document.generation.completed'] as $eventName) {
            if (! is_array(config('maintenance_notifications.rules')[$eventName] ?? null)) {
                $warnings[] = "Maintenance notification rule is not configured: {$eventName}.";
            }
        }

        $this->table(['Core Readiness', 'Value'], [
            ['mPDF class', class_exists(\Mpdf\Mpdf::class) ? 'OK' : 'Missing'],
            ['Document renderer', (string) config('documents.renderer')],
            ['Maintenance notifications', isset(config('maintenance_notifications.rules')['payment.requested']) ? 'OK' : 'Needs review'],
        ]);
    }

    protected function routeIsAvailable(string $name): bool
    {
        if (Route::has($name)) {
            return true;
        }

        $source = file_get_contents(base_path('routes/tenant.php')) . "\n" .
            file_get_contents(base_path('routes/products/automotive/admin.php'));
        $fallbackName = str_starts_with($name, 'automotive.admin.')
            ? substr($name, strlen('automotive.admin.'))
            : $name;

        return str_contains($source, $name) || str_contains($source, $fallbackName);
    }

    protected function verifyTenantOption(array &$failures, array &$warnings): void
    {
        $tenantId = trim((string) ($this->option('tenant') ?? ''));

        if ($tenantId === '') {
            $warnings[] = 'Tenant table checks skipped. Re-run with --tenant=TENANT_ID before production handoff.';

            return;
        }

        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant) {
            $failures[] = "Tenant not found: {$tenantId}.";

            return;
        }

        tenancy()->initialize($tenant);

        try {
            $missing = collect($this->requiredTenantTables)
                ->reject(fn (string $table): bool => Schema::connection('tenant')->hasTable($table))
                ->values()
                ->all();

            if ($missing !== []) {
                $failures[] = 'Tenant is missing maintenance tables: ' . implode(', ', $missing);
            }

            $this->table(['Tenant Readiness', 'Value'], [
                ['Tenant', $tenantId],
                ['Maintenance tables', $missing === [] ? 'OK' : 'Missing: ' . count($missing)],
                ['Work orders', $this->tableCount('work_orders')],
                ['Maintenance invoices', $this->tableCount('maintenance_invoices')],
                ['Generated documents', $this->tableCount('generated_documents')],
            ]);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }
    }

    protected function tableCount(string $table): string
    {
        if (! Schema::connection('tenant')->hasTable($table)) {
            return 'unavailable';
        }

        return (string) DB::connection('tenant')->table($table)->count();
    }
}
