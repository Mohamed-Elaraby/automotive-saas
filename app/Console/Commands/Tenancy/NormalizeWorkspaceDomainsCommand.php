<?php

namespace App\Console\Commands\Tenancy;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Database\Models\Domain;

class NormalizeWorkspaceDomainsCommand extends Command
{
    protected $signature = 'tenancy:normalize-workspace-domains {--dry-run : Show changes without writing aliases}';

    protected $description = 'Create DNS-safe hyphen aliases for tenant domains that contain underscores';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $legacyDomains = Domain::query()
            ->orderBy('domain')
            ->get(['domain', 'tenant_id'])
            ->filter(fn ($domain): bool => str_contains((string) $domain->domain, '_'));

        if ($legacyDomains->isEmpty()) {
            $this->info('No underscore tenant domains were found.');

            return self::SUCCESS;
        }

        $created = 0;
        $skipped = 0;

        foreach ($legacyDomains as $legacyDomain) {
            $canonicalDomain = str_replace('_', '-', (string) $legacyDomain->domain);

            if ($canonicalDomain === $legacyDomain->domain) {
                $skipped++;
                continue;
            }

            if (Domain::query()->where('domain', $canonicalDomain)->exists()) {
                $this->line("exists: {$legacyDomain->domain} -> {$canonicalDomain}");
                $skipped++;
                continue;
            }

            $this->line(($dryRun ? 'would create' : 'create') . ": {$legacyDomain->domain} -> {$canonicalDomain}");

            if (! $dryRun) {
                DB::table('domains')->insert([
                    'domain' => $canonicalDomain,
                    'tenant_id' => $legacyDomain->tenant_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $created++;
        }

        $this->info(($dryRun ? 'Would create' : 'Created') . " {$created} domain alias(es); skipped {$skipped}.");

        return self::SUCCESS;
    }
}
