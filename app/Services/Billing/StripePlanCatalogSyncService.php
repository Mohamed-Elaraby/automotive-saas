<?php

namespace App\Services\Billing;

use App\Models\Plan;
use Illuminate\Support\Collection;
use Stripe\StripeClient;
use Throwable;

class StripePlanCatalogSyncService
{
    protected StripeClient $stripe;

    public function __construct(
        protected BillingPlanCatalogService $billingPlanCatalogService,
        protected StripePriceInspectorService $stripePriceInspectorService
    ) {
        $secret = (string) config('billing.gateways.stripe.secret');

        if ($secret === '') {
            throw new \RuntimeException('Stripe secret key is not configured.');
        }

$this->stripe = new StripeClient($secret);
}

public function syncPaidPlans(bool $apply = false, ?string $slug = null): Collection
{
    $plans = $this->billingPlanCatalogService->getPaidPlans();

    if ($slug) {
        $plans = $plans->where('slug', $slug)->values();
    }

    return $plans->map(function ($plan) use ($apply) {
        return $this->syncSinglePlan($plan, $apply);
    });
}

protected function syncSinglePlan(object $plan, bool $apply): array
{
    $beforeAudit = $this->stripePriceInspectorService->auditPlan($plan);

    if ($beforeAudit['checks']['is_aligned'] ?? false) {
        return [
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'slug' => $plan->slug,
            'local_price' => (float) $plan->price,
            'currency' => strtoupper((string) $plan->currency),
            'billing_period' => (string) $plan->billing_period,
            'old_price_id' => $plan->stripe_price_id ?: '-',
            'new_price_id' => $plan->stripe_price_id ?: '-',
            'product_id' => $beforeAudit['stripe']['product_id'] ?? '-',
            'action' => 'ALREADY_ALIGNED',
            'aligned_before' => true,
            'aligned_after' => true,
            'message' => 'Plan already aligned with Stripe.',
        ];
    }

    $product = $this->findExistingProductForPlan($plan);

    if (! $product && $apply) {
        $product = $this->createProductForPlan($plan);
    }

    $newPriceId = null;

    if ($apply) {
        $newPrice = $this->createRecurringPriceForPlan($plan, $product?->id);
            $newPriceId = $newPrice->id ?? null;

            if ($newPriceId) {
                $planModel = Plan::query()->find($plan->id);

                if ($planModel) {
                    $planModel->update([
                        'stripe_price_id' => $newPriceId,
                    ]);
                }

                $plan->stripe_price_id = $newPriceId;
            }
        }

    $afterAudit = $this->stripePriceInspectorService->auditPlan($plan);

    return [
        'plan_id' => $plan->id,
        'plan_name' => $plan->name,
        'slug' => $plan->slug,
        'local_price' => (float) $plan->price,
        'currency' => strtoupper((string) $plan->currency),
        'billing_period' => (string) $plan->billing_period,
        'old_price_id' => $beforeAudit['stripe']['price_id'] ?? ($plan->stripe_price_id ?: '-'),
        'new_price_id' => $newPriceId ?: ($plan->stripe_price_id ?: '-'),
        'product_id' => $product->id ?? '-',
        'action' => $apply ? 'UPDATED_STRIPE_PRICE' : 'WOULD_CREATE_NEW_STRIPE_PRICE',
        'aligned_before' => (bool) ($beforeAudit['checks']['is_aligned'] ?? false),
        'aligned_after' => (bool) ($afterAudit['checks']['is_aligned'] ?? false),
        'message' => $apply
            ? 'Created/linked a new Stripe price for this plan.'
            : 'Plan is mismatched. A new Stripe price would be created and linked on apply.',
    ];
}

protected function findExistingProductForPlan(object $plan): ?object
{
    $products = $this->stripe->products->all([
        'limit' => 100,
        'active' => true,
    ]);

    foreach ($products->data as $product) {
        $metadata = $product->metadata ?? null;

        if (! $metadata) {
            continue;
        }

        $localSlug = $metadata['local_plan_slug'] ?? null;
        $productScope = $metadata['product_scope'] ?? null;

        if ($localSlug === (string) $plan->slug && $productScope === 'automotive') {
            return $product;
        }
    }

    return null;
}

protected function createProductForPlan(object $plan): object
{
    return $this->stripe->products->create([
        'name' => (string) $plan->name,
        'description' => (string) ($plan->description ?? ''),
        'metadata' => [
            'local_plan_id' => (string) $plan->id,
            'local_plan_slug' => (string) $plan->slug,
            'product_scope' => 'automotive',
        ],
    ]);
}

protected function createRecurringPriceForPlan(object $plan, ?string $productId = null): object
{
    $interval = $this->mapBillingPeriodToStripeInterval((string) $plan->billing_period);

    if (! $interval) {
        throw new \RuntimeException("Unsupported billing period [{$plan->billing_period}] for Stripe recurring pricing.");
    }

    $unitAmount = (int) round(((float) $plan->price) * 100);

    $payload = [
        'currency' => strtolower((string) $plan->currency),
        'unit_amount' => $unitAmount,
        'recurring' => [
            'interval' => $interval,
        ],
        'metadata' => [
            'local_plan_id' => (string) $plan->id,
            'local_plan_slug' => (string) $plan->slug,
            'product_scope' => 'automotive',
        ],
        'nickname' => sprintf(
            '%s %s %s',
            (string) $plan->name,
            number_format((float) $plan->price, 2),
            strtoupper((string) $plan->currency)
        ),
    ];

    if ($productId) {
        $payload['product'] = $productId;
    } else {
        $payload['product_data'] = [
            'name' => (string) $plan->name,
            'description' => (string) ($plan->description ?? ''),
            'metadata' => [
                'local_plan_id' => (string) $plan->id,
                'local_plan_slug' => (string) $plan->slug,
                'product_scope' => 'automotive',
            ],
        ];
    }

    return $this->stripe->prices->create($payload);
}

protected function mapBillingPeriodToStripeInterval(string $billingPeriod): ?string
{
    return match (strtolower($billingPeriod)) {
    'monthly' => 'month',
            'yearly' => 'year',
            default => null,
        };
    }
}
