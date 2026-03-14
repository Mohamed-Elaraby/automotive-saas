<?php

namespace App\Services\Billing;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BillingPlanCatalogService
{
    public function getPaidPlans(): Collection
    {
        return $this->plansTable()
            ->where('is_active', true)
            ->where('billing_period', '!=', 'trial')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($plan) {
                $plan->features_array = $this->decodeFeatures($plan->features ?? null);

                return $plan;
            });
    }

    public function findPaidPlanById(int|string $planId): ?object
    {
        $plan = $this->plansTable()
            ->where('is_active', true)
            ->where('billing_period', '!=', 'trial')
            ->where('id', $planId)
            ->first();

        if (! $plan) {
            return null;
        }

$plan->features_array = $this->decodeFeatures($plan->features ?? null);

return $plan;
}

protected function plansTable()
{
    return DB::connection($this->centralConnection())
        ->table('plans');
}

protected function centralConnection(): string
{
    return (string) config('tenancy.database.central_connection', 'central');
}

protected function decodeFeatures(mixed $features): array
{
    if (is_array($features)) {
        return $features;
    }

    if (is_string($features) && $features !== '') {
        $decoded = json_decode($features, true);

        return is_array($decoded) ? $decoded : [];
    }

    return [];
}
}
