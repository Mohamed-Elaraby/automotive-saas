<?php

namespace App\Services\Automotive;

use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Stancl\Tenancy\Database\Models\Domain;

class StartTrialService
{
    public function start(array $data): array
    {
        $baseDomain = 'automotive.seven-scapital.com';
        $centralConnection = config('tenancy.database.central_connection') ?? config('database.default');

        $sub = strtolower(trim($data['subdomain']));
        $tenantId = $sub;
        $fullDomain = "{$sub}.{$baseDomain}";

        // Validate domain uniqueness
        if (Domain::query()->where('domain', $fullDomain)->exists()) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'This subdomain is already taken.',
                'errors' => ['subdomain' => ['This subdomain is already taken.']],
            ];
        }

        if (Tenant::query()->where('id', $tenantId)->exists()) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'This subdomain is not available.',
                'errors' => ['subdomain' => ['This subdomain is not available.']],
            ];
        }

        $centralUser = User::query()->firstOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'],
                'password' => Hash::make($data['password']),
            ]
        );

        // IMPORTANT: Tenant::create stays OUTSIDE any DB transaction
        $tenant = Tenant::create([
            'id' => $tenantId,
            'data' => [
                'company_name' => $data['company_name'],
                'db_name' => 'tenant_' . $tenantId,
            ],
        ]);

        try {
            // Central records ONLY, explicitly on central connection
            DB::connection($centralConnection)->transaction(function () use ($tenant, $centralUser, $fullDomain, $centralConnection) {
                DB::connection($centralConnection)->table('domains')->insert([
                    'domain' => $fullDomain,
                    'tenant_id' => $tenant->id,
                ]);

                DB::connection($centralConnection)->table('subscriptions')->insert([
                    'tenant_id' => $tenant->id,
                    'plan_id' => null,
                    'status' => 'trialing',
                    'trial_ends_at' => Carbon::now()->addDays(14),
                    'ends_at' => null,
                    'external_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::connection($centralConnection)->table('tenant_users')->insert([
                    'tenant_id' => $tenant->id,
                    'user_id' => $centralUser->id,
                    'role' => 'owner',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            // Tenant DB migrations FIRST
            Artisan::call('tenants:migrate', [
                '--tenants' => [$tenant->id],
                '--force' => true,
            ]);

            // Create tenant admin inside tenant DB
            tenancy()->initialize($tenant);

            try {
                \App\Models\User::query()->firstOrCreate(
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

        } catch (\Throwable $e) {
            // Always leave tenant context before touching central DB
            try {
                if (function_exists('tenancy') && tenancy()->initialized) {
                    tenancy()->end();
                }
            } catch (\Throwable) {
                //
            }

            DB::purge('tenant');

            // Central cleanup ONLY, explicitly on central connection
            DB::connection($centralConnection)->transaction(function () use ($tenant, $centralConnection) {
                DB::connection($centralConnection)->table('domains')
                    ->where('tenant_id', $tenant->id)
                    ->delete();

                DB::connection($centralConnection)->table('subscriptions')
                    ->where('tenant_id', $tenant->id)
                    ->delete();

                DB::connection($centralConnection)->table('tenant_users')
                    ->where('tenant_id', $tenant->id)
                    ->delete();

                DB::connection($centralConnection)->table('tenants')
                    ->where('id', $tenant->id)
                    ->delete();
            });

            report($e);

            return [
                'ok' => false,
                'status' => 500,
                'message' => 'Provisioning failed.',
                'errors' => [],
            ];
        }

        return [
            'ok' => true,
            'status' => 201,
            'tenant_id' => $tenant->id,
            'domain' => $fullDomain,
            // ✅ مهم: ده اللينك الصحيح حسب الراوتس المنظمة
            'login_url' => "https://{$fullDomain}/automotive/admin/login",
        ];
    }
}
