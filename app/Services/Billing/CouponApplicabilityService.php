<?php

namespace App\Services\Billing;

use App\Models\Coupon;

class CouponApplicabilityService
{
    public function __construct(
        protected CouponEligibilityService $eligibilityService
    ) {
    }

    public function evaluate(
        Coupon $coupon,
        ?string $tenantId = null,
        ?int $planId = null,
        bool $isFirstBillingCycle = true
    ): array {
        return $this->eligibilityService->evaluate(
            coupon: $coupon,
            tenantId: $tenantId,
            planId: $planId,
            isFirstBillingCycle: $isFirstBillingCycle
        );
    }
}
