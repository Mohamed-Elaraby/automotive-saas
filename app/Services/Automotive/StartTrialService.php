<?php

namespace App\Services\Automotive;

use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\TenantProductSubscriptionSyncService;
use App\Services\Billing\TrialSignupCouponService;
use App\Support\Billing\SubscriptionStatuses;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Stancl\Tenancy\Database\Models\Domain;

class StartTrialService
{
    public function __construct(
        protected TrialSignupCouponService $trialSignupCouponService,
        protected TenantProductSubscriptionSyncService $tenantProductSubscriptionSyncService
    ) {
    }

public function start(array $data): array
{
    $centralConnection = config('tenancy.database.central_connection') ?? config('database.default');

    $sub = strtolower(trim($data['subdomain']));
    $baseHost = strtolower(trim($data['base_host'] ?? 'automotive.seven-scapital.com'));
    $couponCode = strtoupper(trim((string) ($data['coupon_code'] ?? '')));

    $tenantId = $sub;
    $fullDomain = "{$sub}.{$baseHost}";

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

    $trialProduct = $this->resolveTrialProduct(isset($data['product_id']) ? (int) $data['product_id'] : null);
    $trialPlan = $this->resolveTrialPlan($trialProduct?->id);

    if (! $trialProduct || ! $trialPlan) {
        return [
            'ok' => false,
            'status' => 422,
            'message' => 'No active free trial plan is configured for the selected product.',
            'errors' => ['product_id' => ['No active free trial plan is configured for the selected product.']],
        ];
    }

    $couponValidation = $this->trialSignupCouponService->validateForTrialSignup(
        couponCode: $couponCode,
            tenantId: $tenantId,
            planId: $trialPlan?->id
        );

        if (! ($couponValidation['ok'] ?? false)) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => $couponValidation['message'] ?? 'Coupon validation failed.',
                'errors' => $couponValidation['errors'] ?? ['coupon_code' => ['Coupon validation failed.']],
            ];
        }

        $tenant = Tenant::create([
            'id' => $tenantId,
            'data' => [
                'company_name' => $data['company_name'],
                'db_name' => 'tenant_' . $tenantId,
            ],
        ]);

        try {
            DB::connection($centralConnection)->transaction(function () use (
                $tenant,
                $centralUser,
                $fullDomain,
                $centralConnection,
                $trialPlan,
                $couponValidation
            ) {
                DB::connection($centralConnection)->table('domains')->insert([
                    'domain' => $fullDomain,
                    'tenant_id' => $tenant->id,
                ]);

                $subscriptionId = DB::connection($centralConnection)->table('subscriptions')->insertGetId([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $trialPlan?->id,
                    'status' => SubscriptionStatuses::TRIALING,
                    'trial_ends_at' => Carbon::now()->addDays((int) ($trialPlan->trial_days ?: 14)),
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

                if (! empty($couponValidation['coupon'])) {
                    $this->trialSignupCouponService->attachCouponToSubscription(
                        coupon: $couponValidation['coupon'],
                        tenantId: $tenant->id,
                        subscriptionId: (int) $subscriptionId,
                        planId: $trialPlan?->id
                    );
                }

                $this->tenantProductSubscriptionSyncService->syncFromLegacySubscription((int) $subscriptionId);
            });

            Artisan::call('tenants:migrate', [
                '--tenants' => [$tenant->id],
                '--force' => true,
            ]);

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
            try {
                if (function_exists('tenancy') && tenancy()->initialized) {
                    tenancy()->end();
                }
            } catch (\Throwable) {
                //
            }

            DB::purge('tenant');

            DB::connection($centralConnection)->transaction(function () use ($tenant, $centralConnection) {
                DB::connection($centralConnection)->table('domains')
                    ->where('tenant_id', $tenant->id)
                    ->delete();

                DB::connection($centralConnection)->table('subscriptions')
                    ->where('tenant_id', $tenant->id)
                    ->delete();

                if (DB::connection($centralConnection)->getSchemaBuilder()->hasTable('tenant_product_subscriptions')) {
                    DB::connection($centralConnection)->table('tenant_product_subscriptions')
                        ->where('tenant_id', $tenant->id)
                        ->delete();
                }

                DB::connection($centralConnection)->table('tenant_users')
                    ->where('tenant_id', $tenant->id)
                    ->delete();

                if (DB::connection($centralConnection)->getSchemaBuilder()->hasTable('coupon_redemptions')) {
                    DB::connection($centralConnection)->table('coupon_redemptions')
                        ->where('tenant_id', $tenant->id)
                        ->delete();
                }

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
            'login_url' => "https://{$fullDomain}/workspace",
        ];
    }

    protected function resolveTrialProduct(?int $productId = null): ?Product
    {
        if ($productId && $productId > 0) {
            return Product::query()
                ->where('id', $productId)
                ->where('is_active', true)
                ->first();
        }

        return Product::query()
            ->where('code', 'automotive_service')
            ->where('is_active', true)
            ->first();
    }

    protected function resolveTrialPlan(?int $productId = null): ?Plan
    {
        if (! $productId) {
            return null;
        }

        return Plan::query()
            ->where('product_id', $productId)
            ->where('billing_period', 'trial')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }
}
