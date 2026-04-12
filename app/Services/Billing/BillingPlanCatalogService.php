<?php

namespace App\Services\Billing;

use App\Models\Plan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class BillingPlanCatalogService
{
    public function paidPlanCountsByProductCode(): Collection
    {
        return Plan::query()
            ->join('products', 'products.id', '=', 'plans.product_id')
            ->where('plans.is_active', true)
            ->where('plans.billing_period', '!=', 'trial')
            ->selectRaw('products.code, COUNT(*) as aggregate')
            ->groupBy('products.code')
            ->pluck('aggregate', 'products.code');
    }

    public function getPaidPlans(?string $productCode = null): Collection
    {
        $query = Plan::query()
            ->where('is_active', true)
            ->where('billing_period', '!=', 'trial')
            ->orderBy('sort_order');

        if ($this->supportsBillingFeatures()) {
            $query->with('billingFeatures');
        }

        if (filled($productCode)) {
            $query->whereHas('product', function ($productQuery) use ($productCode) {
                $productQuery->where('code', $productCode);
            });
        }

        return $query
            ->get()
            ->map(function ($plan) {
                return $this->hydratePlan($plan);
            });
    }

    public function findPaidPlanById(int|string $planId, ?string $productCode = null): ?object
    {
        $query = Plan::query()
            ->where('is_active', true)
            ->where('billing_period', '!=', 'trial')
            ->where('id', $planId);

        if ($this->supportsBillingFeatures()) {
            $query->with('billingFeatures');
        }

        if (filled($productCode)) {
            $query->whereHas('product', function ($productQuery) use ($productCode) {
                $productQuery->where('code', $productCode);
            });
        }

        $plan = $query->first();

        if (! $plan) {
            return null;
        }

        return $this->hydratePlan($plan);
    }

    protected function hydratePlan(object $plan): object
    {
        $plan->features_array = $this->normalizeFeatureTitles($plan);
        $plan->limits_array = $this->buildLimits($plan);
        $plan->price_decimal = isset($plan->price) ? (float) $plan->price : 0.0;
        $plan->currency_code = strtoupper((string) ($plan->currency ?? 'USD'));
        $plan->billing_period_label = $this->billingPeriodLabel((string) ($plan->billing_period ?? 'monthly'));
        $plan->display_price = number_format((float) ($plan->price ?? 0), 2) . ' ' . $plan->currency_code;

        return $plan;
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

    protected function normalizeFeatureTitles(object $plan): array
    {
        if (! method_exists($plan, 'relationLoaded') || ! $plan->relationLoaded('billingFeatures')) {
            return [];
        }

        return $plan->billingFeatures
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function supportsBillingFeatures(): bool
    {
        $connection = (string) (config('tenancy.database.central_connection') ?? config('database.default'));

        return Schema::connection($connection)->hasTable('billing_features')
            && Schema::connection($connection)->hasTable('billing_feature_plan');
    }
}
