<?php

namespace App\Services\Billing;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use Illuminate\Support\Carbon;

class CouponEligibilityService
{
    public function evaluate(
        Coupon $coupon,
        ?string $tenantId = null,
        ?int $planId = null,
        bool $isFirstBillingCycle = true
    ): array {
        $reasons = [];

        if (! $coupon->is_active) {
            $reasons[] = 'Coupon is inactive.';
        }

        $now = now();

        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            $reasons[] = 'Coupon has not started yet.';
        }

        if ($coupon->ends_at && $coupon->ends_at->isPast()) {
            $reasons[] = 'Coupon has expired.';
        }

        if ($coupon->max_redemptions !== null && $this->totalActiveRedemptions($coupon) >= (int) $coupon->max_redemptions) {
            $reasons[] = 'Coupon reached its maximum total redemptions.';
        }

        if ($tenantId !== null && $coupon->max_redemptions_per_tenant !== null) {
            if ($this->tenantActiveRedemptions($coupon, $tenantId) >= (int) $coupon->max_redemptions_per_tenant) {
                $reasons[] = 'Tenant reached the maximum number of redemptions for this coupon.';
            }
        }

        if (! $coupon->applies_to_all_plans) {
            if ($planId === null) {
                $reasons[] = 'Coupon is restricted to selected plans and no plan was supplied.';
            } else {
                $allowedPlanIds = $coupon->plans()->pluck('plans.id')->map(fn ($id) => (int) $id)->all();

                if (! in_array((int) $planId, $allowedPlanIds, true)) {
                    $reasons[] = 'Coupon does not apply to the selected plan.';
                }
            }
        }

        if ($coupon->first_billing_cycle_only && ! $isFirstBillingCycle) {
            $reasons[] = 'Coupon is valid only for the first billing cycle.';
        }

        return [
            'eligible' => count($reasons) === 0,
            'reasons' => $reasons,
            'summary' => count($reasons) === 0
                ? 'Coupon is eligible for this scenario.'
                : 'Coupon is not eligible for this scenario.',
            'meta' => [
                'coupon_code' => $coupon->code,
                'discount_type' => $coupon->discount_type,
                'discount_value' => $coupon->discount_value,
                'currency_code' => $coupon->currency_code,
                'tenant_id' => $tenantId,
                'plan_id' => $planId,
                'is_first_billing_cycle' => $isFirstBillingCycle,
                'starts_at' => $coupon->starts_at?->format('Y-m-d H:i:s'),
                'ends_at' => $coupon->ends_at?->format('Y-m-d H:i:s'),
                'total_redemptions' => $this->totalActiveRedemptions($coupon),
                'tenant_redemptions' => $tenantId ? $this->tenantActiveRedemptions($coupon, $tenantId) : null,
                'max_redemptions' => $coupon->max_redemptions,
                'max_redemptions_per_tenant' => $coupon->max_redemptions_per_tenant,
            ],
        ];
    }

    protected function totalActiveRedemptions(Coupon $coupon): int
    {
        return CouponRedemption::query()
            ->where('coupon_id', $coupon->id)
            ->whereIn('status', ['applied', 'consumed'])
            ->count();
    }

    protected function tenantActiveRedemptions(Coupon $coupon, string $tenantId): int
    {
        return CouponRedemption::query()
            ->where('coupon_id', $coupon->id)
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['applied', 'consumed'])
            ->count();
    }
}
