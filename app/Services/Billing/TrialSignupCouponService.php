<?php

namespace App\Services\Billing;

use App\Models\Coupon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TrialSignupCouponService
{
    public function __construct(
        protected CouponEligibilityService $eligibilityService
    ) {
    }

    public function centralConnectionName(): string
    {
        return (string) (Config::get('tenancy.database.central_connection') ?: Config::get('database.default'));
    }

    public function validateForTrialSignup(?string $couponCode, string $tenantId, ?int $planId): array
    {
        $couponCode = strtoupper(trim((string) $couponCode));

        if ($couponCode === '') {
            return [
                'ok' => true,
                'coupon' => null,
                'eligibility' => null,
            ];
        }

        if (! $this->couponTablesReady()) {
            return [
                'ok' => false,
                'message' => 'Coupon system is not fully available yet.',
                'errors' => [
                    'coupon_code' => ['Coupon system is not fully available yet.'],
                ],
            ];
        }

        $coupon = Coupon::query()
            ->where('code', $couponCode)
            ->first();

        if (! $coupon) {
            return [
                'ok' => false,
                'message' => 'The coupon code is invalid.',
                'errors' => [
                    'coupon_code' => ['The coupon code is invalid.'],
                ],
            ];
        }

        $eligibility = $this->eligibilityService->evaluate(
            coupon: $coupon,
            tenantId: $tenantId,
            planId: null,
            isFirstBillingCycle: true
        );

        $reasons = collect($eligibility['reasons'] ?? [])
            ->reject(fn (string $reason) => $reason === 'Coupon is restricted to selected plans and no plan was supplied.')
            ->values()
            ->all();

        $eligibleForTrialReservation = count($reasons) === 0;

        if (! $eligibleForTrialReservation) {
            return [
                'ok' => false,
                'message' => 'This coupon cannot be reserved for the new trial.',
                'errors' => [
                    'coupon_code' => $reasons,
                ],
                'coupon' => $coupon,
                'eligibility' => [
                    ...$eligibility,
                    'eligible' => false,
                    'reasons' => $reasons,
                    'summary' => 'This coupon cannot be reserved for the new trial.',
                ],
            ];
        }

        return [
            'ok' => true,
            'coupon' => $coupon,
            'eligibility' => [
                ...$eligibility,
                'eligible' => true,
                'reasons' => [],
                'summary' => 'Coupon is valid for reservation during trial signup. Plan-specific validation will run later when the paid plan is selected.',
                'meta' => [
                    ...($eligibility['meta'] ?? []),
                    'reserved_during_trial_signup' => true,
                    'plan_validation_deferred' => true,
                    'trial_plan_id' => $planId,
                ],
            ],
        ];
    }

    public function attachCouponToSubscription(
        Coupon $coupon,
        string $tenantId,
        int $subscriptionId,
        ?int $planId
    ): void {
        if (! $this->couponTablesReady()) {
            return;
        }

        DB::connection($this->centralConnectionName())
            ->table('coupon_redemptions')
            ->insert([
                'coupon_id' => $coupon->id,
                'tenant_id' => $tenantId,
                'subscription_id' => $subscriptionId,
                'plan_id' => $planId,
                'status' => 'applied',
                'discount_amount' => null,
                'currency_code' => $coupon->currency_code,
                'context_payload' => json_encode([
                    'source' => 'automotive_trial_signup',
                    'coupon_code' => $coupon->code,
                    'discount_type' => $coupon->discount_type,
                    'discount_value' => $coupon->discount_value,
                    'first_billing_cycle_only' => (bool) $coupon->first_billing_cycle_only,
                    'applies_to_all_plans' => (bool) $coupon->applies_to_all_plans,
                    'reserved_at' => now()->toDateTimeString(),
                    'plan_validation_deferred_until_paid_selection' => true,
                ], JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        DB::connection($this->centralConnectionName())
            ->table('coupons')
            ->where('id', $coupon->id)
            ->increment('times_redeemed');
    }

    protected function couponTablesReady(): bool
    {
        $connection = $this->centralConnectionName();

        return Schema::connection($connection)->hasTable('coupons')
            && Schema::connection($connection)->hasTable('coupon_redemptions');
    }
}
