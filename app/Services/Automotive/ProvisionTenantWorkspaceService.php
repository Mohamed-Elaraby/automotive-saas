<?php

namespace App\Services\Automotive;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ProvisionTenantWorkspaceService
{
    public function ensureProvisioned(string $tenantId, int $userId): void
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $centralUser = User::query()->findOrFail($userId);

        Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->id],
            '--force' => true,
        ]);

        tenancy()->initialize($tenant);

        try {
            User::query()->firstOrCreate(
                ['email' => $centralUser->email],
                [
                    'name' => $centralUser->name,
                    'password' => $centralUser->password,
                ]
            );
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }
    }
}
