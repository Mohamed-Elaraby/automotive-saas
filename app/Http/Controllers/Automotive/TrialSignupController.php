<?php

namespace App\Http\Controllers\Automotive;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartTrialRequest;
use App\Models\Subscription;
use App\Models\TenantUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Stancl\Tenancy\Database\Models\Tenant;

class TrialSignupController extends Controller
{
    public function __invoke(StartTrialRequest $request)
    {
        $baseDomain = 'automotive.seven-scapital.com';

        $sub = strtolower(trim($request->input('subdomain')));
        $fullDomain = "{$sub}.{$baseDomain}";
        $tenantId = $sub;

        // ✅ Check domain availability (central domains table from stancl)
        if (DB::table('domains')->where('domain', $fullDomain)->exists()) {
            return back()->withErrors(['subdomain' => 'This subdomain is already taken.'])->withInput();
        }

        // ✅ Check tenant id availability
        if (Tenant::query()->where('id', $tenantId)->exists()) {
            return back()->withErrors(['subdomain' => 'This subdomain is not available.'])->withInput();
        }

        DB::beginTransaction();

        try {
            // 1) Create central user
            $centralUser = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
            ]);

            // 2) Create tenant (central)
            $tenant = Tenant::create([
                'id' => $tenantId,
                'data' => [
                    'company_name' => $request->input('company_name'),
                ],
            ]);

            // 3) Attach domain (central)
            $tenant->domains()->create([
                'domain' => $fullDomain,
            ]);

            // 4) Create subscription (central)
            Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => null,
                'status' => 'trialing',
                'trial_ends_at' => Carbon::now()->addDays(14),
            ]);

            // 5) Link user to tenant (central pivot)
            TenantUser::create([
                'tenant_id' => $tenant->id,
                'user_id' => $centralUser->id,
                'role' => 'owner',
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        // 6) Provision tenant DB + migrate/seed + create tenant admin in tenant users table
        $this->provisionTenant($tenant, $centralUser);

        // 7) Redirect to tenant login
//        return redirect()->to("https://{$fullDomain}/login")
//            ->with('success', 'Your trial has been created. Please login.');
        return response()->json([
            'ok' => true,
            'tenant_id' => $tenant->id,
            'domain' => $fullDomain,
            'login_url' => "https://{$fullDomain}/login",
        ], 201);
    }

    protected function provisionTenant(Tenant $tenant, User $centralUser): void
    {
        tenancy()->initialize($tenant);

        try {
            // ✅ migrate tenant DB
            \Artisan::call('tenants:migrate', [
                '--tenants' => [$tenant->id],
                '--force' => true,
            ]);

            // ✅ seed tenant DB (لو عندك seeder tenant)
            \Artisan::call('tenants:seed', [
                '--tenants' => [$tenant->id],
                '--force' => true,
            ]);

            // ✅ Create tenant admin user inside tenant database
            // هنا App\Models\User هيتعامل مع tenant connection لأن tenancy initialized
            $tenantAdmin = \App\Models\User::query()->firstOrCreate(
                ['email' => $centralUser->email],
                [
                    'name' => $centralUser->name,
                    'password' => $centralUser->password, // already hashed
                ]
            );

            // (اختياري) لو عندك flags في users داخل tenant
            // $tenantAdmin->update(['is_admin' => true]);

        } finally {
            tenancy()->end();
        }
    }
}
