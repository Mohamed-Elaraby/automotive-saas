<?php

namespace App\Http\Controllers\Automotive;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartTrialRequest;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Stancl\Tenancy\Database\Models\Domain;

class TrialSignupController extends Controller
{
    public function __invoke(StartTrialRequest $request)
    {
        $baseDomain = 'automotive.seven-scapital.com';

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

        // مهم: خارج أي transaction
        $tenant = Tenant::create([
            'id' => $tenantId,
            'data' => [
                'company_name' => $request->input('company_name'),
                'db_name' => 'tenant_' . $tenantId,
            ],
        ]);

        try {
            DB::transaction(function () use ($tenant, $centralUser, $fullDomain) {
                Domain::create([
                    'domain' => $fullDomain,
                    'tenant_id' => $tenant->id,
                ]);

                Subscription::create([
                    'tenant_id' => $tenant->id,
                    'plan_id' => null,
                    'status' => 'trialing',
                    'trial_ends_at' => Carbon::now()->addDays(14),
                ]);

                TenantUser::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $centralUser->id,
                    'role' => 'owner',
                ]);
            });

            // ✅ إنشاء نفس اليوزر داخل tenant DB
            tenancy()->initialize($tenant);

            try {
                \App\Models\User::query()->firstOrCreate(
                    ['email' => $centralUser->email],
                    [
                        'name' => $centralUser->name,
                        'password' => $centralUser->password, // already hashed
                    ]
                );
            } finally {
                tenancy()->end();
            }

        } catch (\Throwable $e) {
            // cleanup لو أي حاجة فشلت
            Domain::query()->where('tenant_id', $tenant->id)->delete();
            Subscription::query()->where('tenant_id', $tenant->id)->delete();
            TenantUser::query()->where('tenant_id', $tenant->id)->delete();
            Tenant::query()->where('id', $tenant->id)->delete();

            throw $e;
        }

        return response()->json([
            'ok' => true,
            'tenant_id' => $tenant->id,
            'domain' => $fullDomain,
            'login_url' => "https://{$fullDomain}/login",
        ], 201);
    }
}
