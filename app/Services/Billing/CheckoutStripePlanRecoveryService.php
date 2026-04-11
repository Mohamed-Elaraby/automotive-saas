<?php

namespace App\Services\Billing;

use App\Models\Plan;

class CheckoutStripePlanRecoveryService
{
    public function __construct(
        protected BillingPlanCatalogService $billingPlanCatalogService,
        protected StripePlanCatalogSyncService $stripePlanCatalogSyncService
    ) {
    }

    public function recoverPaidPlan(int $planId, string $productCode): ?object
    {
        $plan = $this->billingPlanCatalogService->findPaidPlanById($planId, $productCode);

        if ($plan && filled($plan->stripe_price_id)) {
            return $plan;
        }

        $eloquentPlan = Plan::query()->find($planId);

        if (! $eloquentPlan) {
            return $plan;
        }

        $sync = $this->stripePlanCatalogSyncService->syncPlan($eloquentPlan);

        if (! ($sync['ok'] ?? false)) {
            return $plan;
        }

        return $this->billingPlanCatalogService->findPaidPlanById($planId, $productCode);
    }

    public function retryIfStripePriceNeedsRepair(
        object $plan,
        string $productCode,
        callable $attempt
    ): array {
        $firstAttempt = $attempt($plan);

        if (($firstAttempt['success'] ?? false) === true) {
            return $firstAttempt;
        }

        $message = (string) ($firstAttempt['message'] ?? '');
        $needsRepair = str_contains($message, 'inactive Stripe price')
            || str_contains($message, 'does not match the linked Stripe price')
            || str_contains($message, 'not linked to a Stripe price yet');

        if (! $needsRepair) {
            return $firstAttempt;
        }

        $eloquentPlan = Plan::query()->find((int) $plan->id);

        if (! $eloquentPlan) {
            return $firstAttempt;
        }

        $sync = $this->stripePlanCatalogSyncService->syncPlan($eloquentPlan);

        if (! ($sync['ok'] ?? false)) {
            return $firstAttempt;
        }

        $refreshedPlan = $this->billingPlanCatalogService->findPaidPlanById((int) $plan->id, $productCode);

        if (! $refreshedPlan || blank($refreshedPlan->stripe_price_id)) {
            return $firstAttempt;
        }

        return $attempt($refreshedPlan);
    }
}
