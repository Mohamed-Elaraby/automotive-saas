<?php

namespace App\Console\Commands\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\WorkspaceOwnerAccessService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GrantTenantOwnerCommand extends Command
{
    protected $signature = 'tenant:grant-owner {tenant} {email} {--sync-access}';

    protected $description = 'Create or update the primary tenant workspace owner and optionally sync explicit owner access.';

    public function handle(WorkspaceOwnerAccessService $ownerAccess): int
    {
        $tenant = Tenant::query()->find((string) $this->argument('tenant'));

        if (! $tenant) {
            $this->error('Tenant was not found.');

            return self::FAILURE;
        }

        tenancy()->initialize($tenant);

        try {
            $email = (string) $this->argument('email');
            $owner = User::query()->updateOrCreate(
                ['id' => 1],
                [
                    'name' => Str::headline(Str::before($email, '@')) ?: 'Workspace Owner',
                    'email' => $email,
                    'password' => Hash::make(Str::random(32)),
                ]
            );

            $summary = $this->option('sync-access')
                ? $ownerAccess->syncOwnerAccess($owner)
                : ['products_synced' => 0, 'branches_synced' => 0, 'skipped_inactive_products' => 0];

            $this->info('Workspace owner granted.');
            $this->line('Products synced: ' . $summary['products_synced']);
            $this->line('Branches synced: ' . $summary['branches_synced']);
        } finally {
            tenancy()->end();
        }

        return self::SUCCESS;
    }
}
