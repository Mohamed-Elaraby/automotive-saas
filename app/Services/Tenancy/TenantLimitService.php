<?php

namespace App\Services\Tenancy;

class TenantLimitService
{
    public function __construct(
        protected TenantPlanService $tenantPlanService
    ) {
    }

public function getDecision(string $tenantId, string $limitField, int $currentUsage): array
{
    $plan = $this->tenantPlanService->getCurrentPlan($tenantId);
    $limit = $this->tenantPlanService->getLimit($tenantId, $limitField);

    if ($limit === null) {
        return [
            'allowed' => true,
            'reason' => 'no_limit',
            'current' => $currentUsage,
            'limit' => null,
            'remaining' => null,
            'plan' => $plan,
            'field' => $limitField,
        ];
    }

    $allowed = $currentUsage < $limit;

    return [
        'allowed' => $allowed,
        'reason' => $allowed ? 'within_limit' : 'limit_reached',
        'current' => $currentUsage,
        'limit' => $limit,
        'remaining' => max($limit - $currentUsage, 0),
        'plan' => $plan,
        'field' => $limitField,
    ];
}
}
