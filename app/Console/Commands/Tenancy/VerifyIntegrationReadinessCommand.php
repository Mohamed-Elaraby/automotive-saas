<?php

namespace App\Console\Commands\Tenancy;

use App\Models\Tenant;
use App\Services\Tenancy\TenantWorkspaceProductService;
use App\Services\Tenancy\WorkspaceIntegrationContractService;
use App\Services\Tenancy\WorkspaceManifestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VerifyIntegrationReadinessCommand extends Command
{
    protected $signature = 'tenancy:verify-integration-readiness
                            {--tenant= : Optional tenant id to verify tenant database tables and product activation}';

    protected $description = 'Verify workspace integration contracts, tenant runtime tables, and product activation after deployment';

    protected array $requiredContractKeys = [
        'automotive-parts',
        'automotive-accounting',
        'parts-accounting',
    ];

    protected array $requiredTenantTables = [
        'workspace_integration_handoffs',
        'accounting_events',
        'accounting_posting_groups',
        'journal_entries',
        'journal_entry_lines',
        'accounting_accounts',
        'accounting_period_locks',
        'accounting_policies',
        'accounting_audit_entries',
        'accounting_payments',
        'work_orders',
        'work_order_lines',
        'stock_movements',
        'products',
    ];

    public function __construct(
        protected WorkspaceManifestService $workspaceManifestService,
        protected WorkspaceIntegrationContractService $workspaceIntegrationContractService,
        protected TenantWorkspaceProductService $tenantWorkspaceProductService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $failures = [];
        $warnings = [];

        $contractRows = $this->contractRows($failures);
        $this->table(['Contract', 'Source', 'Target', 'Events'], $contractRows);

        $tenantId = trim((string) ($this->option('tenant') ?? ''));

        if ($tenantId === '') {
            $warnings[] = 'Tenant checks skipped. Re-run with --tenant=TENANT_ID before considering production verification complete.';
        } else {
            $this->verifyTenant($tenantId, $failures);
        }

        foreach ($warnings as $warning) {
            $this->warn($warning);
        }

        if ($failures !== []) {
            foreach ($failures as $failure) {
                $this->error($failure);
            }

            return self::FAILURE;
        }

        $this->info('Workspace integration readiness verification passed.');

        return self::SUCCESS;
    }

    protected function contractRows(array &$failures): array
    {
        $contracts = $this->workspaceIntegrationContractService->contracts();
        $keys = $contracts->pluck('key')->all();

        foreach ($this->requiredContractKeys as $key) {
            if (! in_array($key, $keys, true)) {
                $failures[] = "Missing required integration contract: {$key}.";
            }
        }

        return $contracts
            ->map(fn (array $contract): array => [
                $contract['key'],
                $contract['source_family'],
                $contract['target_family'],
                implode(', ', $contract['events']),
            ])
            ->all();
    }

    protected function verifyTenant(string $tenantId, array &$failures): void
    {
        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant) {
            $failures[] = "Tenant not found: {$tenantId}.";

            return;
        }

        tenancy()->initialize($tenant);

        try {
            $missingTables = collect($this->requiredTenantTables)
                ->reject(fn (string $table): bool => Schema::connection('tenant')->hasTable($table))
                ->values()
                ->all();

            if ($missingTables !== []) {
                $failures[] = 'Tenant is missing required runtime tables: ' . implode(', ', $missingTables) . '.';
            }

            $workspaceProducts = $this->tenantWorkspaceProductService->getWorkspaceProducts($tenantId);
            $missingFamilies = collect(['automotive_service', 'parts_inventory', 'accounting'])
                ->reject(fn (string $family): bool => $this->workspaceManifestService->hasAccessibleFamily($workspaceProducts, $family))
                ->values()
                ->all();

            if ($missingFamilies !== []) {
                $failures[] = 'Tenant is missing active workspace products for integration verification: ' . implode(', ', $missingFamilies) . '.';
            }

            $handoffCount = Schema::connection('tenant')->hasTable('workspace_integration_handoffs')
                ? (string) DB::connection('tenant')->table('workspace_integration_handoffs')->count()
                : 'unavailable';

            $this->table(['Tenant Check', 'Value'], [
                ['Tenant', $tenantId],
                ['Runtime tables', $missingTables === [] ? 'OK' : 'Missing: ' . implode(', ', $missingTables)],
                ['Workspace products', $missingFamilies === [] ? 'OK' : 'Missing: ' . implode(', ', $missingFamilies)],
                ['Recorded handoffs', $handoffCount],
            ]);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }
    }
}
