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
                return $this->hydratePlan($plan);
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

return $this->hydratePlan($plan);
}

protected function hydratePlan(object $plan): object
{
    $plan->features_array = $this->decodeFeatures($plan->features ?? null);
    $plan->limits_array = $this->buildLimits($plan);
    $plan->price_decimal = isset($plan->price) ? (float) $plan->price : 0.0;
    $plan->currency_code = strtoupper((string) ($plan->currency ?? 'USD'));
    $plan->billing_period_label = $this->billingPeriodLabel((string) ($plan->billing_period ?? 'monthly'));
    $plan->display_price = number_format((float) ($plan->price ?? 0), 2) . ' ' . $plan->currency_code;

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

protected function buildLimits(object $plan): array
{
    $limits = [];

    foreach ([
        'max_users' => 'Users',
        'max_branches' => 'Branches',
        'max_products' => 'Products',
        'max_storage_mb' => 'Storage',
    ] as $field => $label) {
        $value = $plan->{$field} ?? null;

        if ($value === null || $value === '') {
            continue;
        }

        $limits[] = [
            'label' => $label,
            'value' => $field === 'max_storage_mb'
                ? ((int) $value . ' MB')
                : (string) (int) $value,
        ];
    }

    return $limits;
}

protected function billingPeriodLabel(string $period): string
{
    return match ($period) {
        'trial' => 'Trial',
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
        'one_time' => 'One Time',
        default => ucfirst($period),
    };
}
}
