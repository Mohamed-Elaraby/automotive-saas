<?php

namespace App\Http\Controllers\Automotive;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartTrialRequest;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Stancl\Tenancy\Database\Models\Domain;

class TrialSignupController extends Controller
{
    public function __invoke(StartTrialRequest $request)
    {
        $baseDomain = 'automotive.seven-scapital.com';
        $centralConnection = config('database.default');

        $sub = strtolower(trim($request->input('subdomain')));
        $tenantId = $sub;
        $fullDomain = "{$sub}.{$baseDomain}";

        if (Domain::query()->where('domain', $fullDomain)->exists()) {
            return response()->json([
                'message' => 'This subdomain is already taken.',
                'errors' => ['subdomain' => ['This subdomain is already taken.']],
            ], 422);
        }

        if (Tenant::query()->where('id', $tenantId)->exists()) {
            return response()->json([
                'message' => 'This subdomain is not available.',
                'errors' => ['subdomain' => ['This subdomain is not available.']],
            ], 422);
        }

        $centralUser = User::query()->firstOrCreate(
            ['email' => $request->input('email')],
            [
                'name' => $request->input('name'),
                'password' => Hash::make($request->input('password')),
            ]
        );

        // IMPORTANT: Tenant::create stays OUTSIDE any DB transaction
        $tenant = Tenant::create([
            'id' => $tenantId,
            'data' => [
                'company_name' => $request->input('company_name'),
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

            // Seed disabled for now until everything stabilizes
            // Artisan::call('tenants:seed', [
            //     '--tenants' => [$tenant->id],
            //     '--force' => true,
            // ]);

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

            return response()->json([
                'message' => 'Provisioning failed.',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'tenant_id' => $tenant->id,
            'domain' => $fullDomain,
            'login_url' => "https://{$fullDomain}/login",
        ], 201);
    }
}
