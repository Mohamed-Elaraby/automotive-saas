<?php

namespace App\Http\Controllers\Automotive;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartTrialRequest;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\Jobs\CreateDatabase;

class TrialSignupController extends Controller
{
    public function __invoke(StartTrialRequest $request): JsonResponse
    {
        // الأفضل لاحقًا نخليه في config/saas.php
        $baseDomain = 'automotive.seven-scapital.com';

        $sub = strtolower(trim((string) $request->input('subdomain')));
        $fullDomain = "{$sub}.{$baseDomain}";
        $tenantId = $sub;

        // ✅ Hard validation (حتى لو StartTrialRequest بيعمل validation)
        if (!$this->isValidSubdomain($sub)) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid subdomain format.',
                'errors' => ['subdomain' => ['Invalid subdomain format.']],
            ], 422);
        }

        // كلمات محجوزة (عدّل براحتك)
        $reserved = ['www', 'api', 'admin', 'dashboard', 'app', 'static', 'assets', 'login', 'register'];
        if (in_array($sub, $reserved, true)) {
            return response()->json([
                'ok' => false,
                'message' => 'This subdomain is reserved.',
                'errors' => ['subdomain' => ['This subdomain is reserved.']],
            ], 422);
        }

        // ✅ Check domain availability
        if (Domain::query()->where('domain', $fullDomain)->exists()) {
            return response()->json([
                'ok' => false,
                'message' => 'This subdomain is already taken.',
                'errors' => ['subdomain' => ['This subdomain is already taken.']],
            ], 422);
        }

        // ✅ Check tenant id availability
        if (Tenant::query()->where('id', $tenantId)->exists()) {
            return response()->json([
                'ok' => false,
                'message' => 'This subdomain is not available.',
                'errors' => ['subdomain' => ['This subdomain is not available.']],
            ], 422);
        }

        $centralUser = null;
        $tenant = null;

        // عشان لو user اتعمل قبل كده بالإيميل ده، ما نمسحوش بالغلط
        $userExistedBefore = User::query()->where('email', $request->input('email'))->exists();

        // 1) Central records in ONE transaction
        [$tenant, $centralUser] = DB::transaction(function () use ($request, $tenantId, $fullDomain) {

            // Central user (لو الايميل موجود، هنجيب نفس اليوزر بدل ما نفشل)
            $centralUser = User::query()->firstOrCreate(
                ['email' => $request->input('email')],
                [
                    'name' => $request->input('name'),
                    'password' => Hash::make($request->input('password')),
                ]
            );

            // Tenant (central)
            $tenant = Tenant::create([
                'id' => $tenantId,
                'data' => [
                    'company_name' => $request->input('company_name'),
                ],
            ]);

            // Domain (central)
            Domain::create([
                'domain' => $fullDomain,
                'tenant_id' => $tenant->id,
            ]);

            // Subscription (central)
            Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => null,
                'status' => 'trialing',
                'trial_ends_at' => Carbon::now()->addDays(14),
            ]);

            // tenant_users (central pivot)
            TenantUser::create([
                'tenant_id' => $tenant->id,
                'user_id' => $centralUser->id,
                'role' => 'owner',
            ]);

            return [$tenant, $centralUser];
        });

        // 2) Provision (outside central transaction)
        try {
            $this->provisionTenant($tenant, $centralUser);
        } catch (\Throwable $e) {
            // ✅ Cleanup فوري عشان ما يسيبش tenants ميتة
            $this->cleanupFailedProvision($tenant, $centralUser, $userExistedBefore);

            // رجّع JSON واضح (مهم في الـ API)
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Failed to provision tenant. Please try again.',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'tenant_id' => $tenant->id,
            'domain' => $fullDomain,
            'login_url' => "https://{$fullDomain}/login",
        ], 201);
    }

    protected function provisionTenant(Tenant $tenant, User $centralUser): void
    {
        /**
         * ✅ أهم نقطة:
         * في stancl/tenancy v3 (multi-database) لازم نعمل CreateDatabase قبل migrate/seed.
         * ومش هنستخدم Tenancy::create() pipeline هنا — هننفذ خطوات واضحة.
         */

        // 1) create database
        dispatch_sync(new CreateDatabase($tenant));

        // 2) migrate + seed للـ tenant
        \Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->id],
            '--force' => true,
        ]);

        \Artisan::call('tenants:seed', [
            '--tenants' => [$tenant->id],
            '--force' => true,
        ]);

        // 3) Create tenant admin داخل tenant DB
        tenancy()->initialize($tenant);

        try {
            /** @var \App\Models\User $tenantUser */
            $tenantUser = \App\Models\User::query()->firstOrCreate(
                ['email' => $centralUser->email],
                [
                    'name' => $centralUser->name,
                    'password' => $centralUser->password, // hashed already
                ]
            );

            // لو عندك عمود is_admin في tenant users table
            if (SchemaHasColumn('users', 'is_admin')) {
                $tenantUser->forceFill(['is_admin' => true])->save();
            }

        } finally {
            tenancy()->end();
        }
    }

    protected function cleanupFailedProvision(Tenant $tenant, User $centralUser, bool $userExistedBefore): void
    {
        DB::transaction(function () use ($tenant, $centralUser, $userExistedBefore) {

            // delete central records created
            TenantUser::query()->where('tenant_id', $tenant->id)->delete();
            Subscription::query()->where('tenant_id', $tenant->id)->delete();
            Domain::query()->where('tenant_id', $tenant->id)->delete();

            // tenant record
            Tenant::query()->where('id', $tenant->id)->delete();

            // delete user only if it was created now (not existing before)
            if (!$userExistedBefore) {
                User::query()->where('id', $centralUser->id)->delete();
            }
        });

        // ⚠️ حذف DB نفسها (لو اتعملت)
        // لو عندك DropDatabase job في stancl:
        // dispatch_sync(new \Stancl\Tenancy\Jobs\DeleteDatabase($tenant));
        // هنسيبه مؤقتًا لحد ما نركب Cleanup Command في Step 3 مضبوط.
    }

    protected function isValidSubdomain(string $sub): bool
    {
        // 3-30 chars, letters/numbers/hyphen, no leading/trailing hyphen
        if (strlen($sub) < 3 || strlen($sub) > 30) {
            return false;
        }

        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])$/', $sub)) {
            return false;
        }

        return true;
    }
}

/**
 * Helpers صغيرة عشان ما نجيب Illuminate\Support\Facades\Schema هنا ونكتر use
 * (وتفضل بسيطة)
 */
function SchemaHasColumn(string $table, string $column): bool
{
    try {
        return \Illuminate\Support\Facades\Schema::hasColumn($table, $column);
    } catch (\Throwable) {
        return false;
    }
}
