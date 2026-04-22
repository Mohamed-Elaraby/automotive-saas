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
                            {--tenant= : Optional tenant id to verify tenant database tables and product activation}
                            {--stale-handoff-days=2 : Warn when pending integration handoffs are older than this many days}';

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
        'accounting_tax_rates',
        'accounting_audit_entries',
        'accounting_payments',
        'accounting_deposit_batches',
        'accounting_vendor_bills',
        'accounting_vendor_bill_adjustments',
        'accounting_vendor_bill_payments',
        'accounting_bank_accounts',
        'accounting_invoices',
        'accounting_invoice_lines',
        'work_orders',
        'work_order_lines',
        'stock_movements',
        'products',
    ];

    protected array $requiredAccountingAccounts = [
        '1000 Cash On Hand',
        '1010 Bank Account',
        '1100 Accounts Receivable',
        '1300 Inventory Asset',
        '1410 VAT Input Receivable',
        '2000 Accounts Payable',
        '2100 VAT Output Payable',
        '3900 Inventory Adjustment Offset',
        '4100 Service Labor Revenue',
        '4100 Service Revenue',
        '4200 Parts Revenue',
        '5000 Cost Of Goods Sold',
        '5100 Inventory Adjustment Expense',
        '5200 Operating Expense',
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
            $this->verifyTenant($tenantId, $failures, $warnings);
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

    protected function verifyTenant(string $tenantId, array &$failures, array &$warnings): void
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

            if (! $this->workspaceManifestService->hasAccessibleFamily($workspaceProducts, 'accounting')) {
                $failures[] = 'Tenant is missing an active accounting workspace product.';
            }

            $accountingChecks = $this->verifyAccountingConfiguration($missingTables, $failures, $warnings);
            $handoffChecks = $this->verifyHandoffHealth($missingTables, $warnings);

            $handoffCount = Schema::connection('tenant')->hasTable('workspace_integration_handoffs')
                ? (string) DB::connection('tenant')->table('workspace_integration_handoffs')->count()
                : 'unavailable';

            $this->table(['Tenant Check', 'Value'], [
                ['Tenant', $tenantId],
                ['Runtime tables', $missingTables === [] ? 'OK' : 'Missing: ' . implode(', ', $missingTables)],
                ['Workspace products', $missingFamilies === [] ? 'OK' : 'Missing: ' . implode(', ', $missingFamilies)],
                ['Default accounts', $accountingChecks['accounts']],
                ['Default posting group', $accountingChecks['posting_group']],
                ['Default accounting policy', $accountingChecks['policy']],
                ['Default tax rate', $accountingChecks['tax_rate']],
                ['Open period lock overlaps', $accountingChecks['period_overlaps']],
                ['Stale handoffs', $handoffChecks['stale_handoffs']],
                ['Recorded handoffs', $handoffCount],
            ]);
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }
    }

    protected function verifyAccountingConfiguration(array $missingTables, array &$failures, array &$warnings): array
    {
        $checks = [
            'accounts' => 'Skipped',
            'posting_group' => 'Skipped',
            'policy' => 'Skipped',
            'tax_rate' => 'Skipped',
            'period_overlaps' => 'Skipped',
        ];

        if (in_array('accounting_accounts', $missingTables, true)) {
            return $checks;
        }

        $accounts = DB::connection('tenant')
            ->table('accounting_accounts')
            ->whereIn('code', $this->requiredAccountingAccounts)
            ->get(['code', 'is_active'])
            ->keyBy('code');

        $missingAccounts = collect($this->requiredAccountingAccounts)
            ->reject(fn (string $code): bool => $accounts->has($code))
            ->values()
            ->all();

        $inactiveAccounts = $accounts
            ->filter(fn (object $account): bool => ! (bool) $account->is_active)
            ->keys()
            ->values()
            ->all();

        if ($missingAccounts !== []) {
            $failures[] = 'Tenant accounting setup is missing required default accounts: ' . implode(', ', $missingAccounts) . '.';
        }

        if ($inactiveAccounts !== []) {
            $warnings[] = 'Tenant accounting setup has inactive required accounts: ' . implode(', ', $inactiveAccounts) . '.';
        }

        $checks['accounts'] = $missingAccounts === []
            ? 'OK' . ($inactiveAccounts === [] ? '' : '; inactive: ' . implode(', ', $inactiveAccounts))
            : 'Missing: ' . implode(', ', $missingAccounts);

        $checks['posting_group'] = $this->verifyDefaultRecord(
            'accounting_posting_groups',
            'Tenant accounting setup is missing an active default posting group.',
            $missingTables,
            $failures
        );

        $checks['policy'] = $this->verifyDefaultRecord(
            'accounting_policies',
            'Tenant accounting setup is missing an active default accounting policy.',
            $missingTables,
            $failures
        );

        $checks['tax_rate'] = $this->verifyDefaultRecord(
            'accounting_tax_rates',
            'Tenant accounting setup is missing an active default tax rate.',
            $missingTables,
            $failures
        );

        $checks['period_overlaps'] = $this->verifyPeriodLockOverlaps($missingTables, $warnings);

        return $checks;
    }

    protected function verifyDefaultRecord(string $table, string $failureMessage, array $missingTables, array &$failures): string
    {
        if (in_array($table, $missingTables, true)) {
            return 'Skipped';
        }

        $exists = DB::connection('tenant')
            ->table($table)
            ->where('is_active', true)
            ->where('is_default', true)
            ->exists();

        if (! $exists) {
            $failures[] = $failureMessage;

            return 'Missing active default';
        }

        return 'OK';
    }

    protected function verifyPeriodLockOverlaps(array $missingTables, array &$warnings): string
    {
        if (in_array('accounting_period_locks', $missingTables, true)) {
            return 'Skipped';
        }

        $locks = DB::connection('tenant')
            ->table('accounting_period_locks')
            ->whereIn('status', ['locked', 'archived'])
            ->orderBy('period_start')
            ->orderBy('period_end')
            ->get(['id', 'period_start', 'period_end']);

        $overlaps = [];
        $previous = null;

        foreach ($locks as $lock) {
            if ($previous && $lock->period_start <= $previous->period_end) {
                $overlaps[] = "#{$previous->id} overlaps #{$lock->id}";
            }

            if (! $previous || $lock->period_end > $previous->period_end) {
                $previous = $lock;
            }
        }

        if ($overlaps !== []) {
            $warnings[] = 'Tenant accounting period locks overlap: ' . implode(', ', $overlaps) . '.';

            return 'Overlaps: ' . count($overlaps);
        }

        return 'OK';
    }

    protected function verifyHandoffHealth(array $missingTables, array &$warnings): array
    {
        $checks = ['stale_handoffs' => 'Skipped'];

        if (in_array('workspace_integration_handoffs', $missingTables, true)) {
            return $checks;
        }

        $days = max(1, (int) $this->option('stale-handoff-days'));
        $staleCount = DB::connection('tenant')
            ->table('workspace_integration_handoffs')
            ->whereIn('status', ['pending', 'failed', 'retrying'])
            ->where('created_at', '<', now()->subDays($days))
            ->count();

        if ($staleCount > 0) {
            $warnings[] = "Tenant has {$staleCount} uncompleted integration handoff(s) older than {$days} day(s).";
            $checks['stale_handoffs'] = (string) $staleCount;
        } else {
            $checks['stale_handoffs'] = 'OK';
        }

        return $checks;
    }
}
