<?php

namespace App\Services\Automotive;

use App\Models\CustomerOnboardingProfile;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Database\Models\Domain;

class ProvisionTenantWorkspaceService
{
    public function ensureProvisioned(string $tenantId): void
    {
        $profile = CustomerOnboardingProfile::query()
            ->where('subdomain', $tenantId)
            ->firstOrFail();

        $centralUser = User::query()->findOrFail((int) $profile->user_id);
        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant) {
            $tenant = Tenant::create([
                'id' => $tenantId,
                'data' => [
                    'company_name' => $profile->company_name,
                    'db_name' => 'tenant_' . $tenantId,
                ],
            ]);
        }

        $fullDomain = $tenantId . '.' . strtolower(trim((string) ($profile->base_host ?: request()->getHost())));

        if (! Domain::query()->where('domain', $fullDomain)->exists()) {
            Domain::query()->create([
                'domain' => $fullDomain,
                'tenant_id' => $tenant->id,
            ]);
        }

        if (! DB::table('tenant_users')->where('tenant_id', $tenant->id)->where('user_id', $centralUser->id)->exists()) {
            DB::table('tenant_users')->insert([
                'tenant_id' => $tenant->id,
                'user_id' => $centralUser->id,
                'role' => 'owner',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

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
