<?php

namespace App\Console\Commands\Billing;

use App\Services\Billing\BillingPlanCatalogService;
use App\Services\Billing\StripePriceInspectorService;
use Illuminate\Console\Command;

class VerifyBillingPlanPricesCommand extends Command
{
    protected $signature = 'billing:verify-plan-prices';

    protected $description = 'Verify local paid plan catalog pricing against linked Stripe prices';

    public function __construct(
        protected BillingPlanCatalogService $billingPlanCatalogService,
        protected StripePriceInspectorService $stripePriceInspectorService
    ) {
        parent::__construct();
    }

public function handle(): int
{
    $plans = $this->billingPlanCatalogService->getPaidPlans();

    if ($plans->isEmpty()) {
        $this->warn('No paid plans were found.');
        return self::SUCCESS;
    }

    $rows = [];

    foreach ($plans as $plan) {
        $audit = $this->stripePriceInspectorService->auditPlan($plan);

        $rows[] = [
            'Plan' => $plan->name,
            'Slug' => $plan->slug,
            'Local Price' => number_format((float) $plan->price, 2),
            'Local Currency' => strtoupper((string) $plan->currency),
            'Local Period' => (string) $plan->billing_period,
            'Stripe Price ID' => (string) ($plan->stripe_price_id ?? '-'),
            'Stripe Amount' => $audit['stripe']['unit_amount_decimal'] !== null
                ? number_format((float) $audit['stripe']['unit_amount_decimal'], 2)
                : '-',
            'Stripe Currency' => $audit['stripe']['currency'] ?? '-',
            'Stripe Interval' => $audit['stripe']['interval'] ?? '-',
            'Aligned' => ($audit['checks']['is_aligned'] ?? false) ? 'YES' : 'NO',
        ];
    }

    $this->table([
        'Plan',
        'Slug',
        'Local Price',
        'Local Currency',
        'Local Period',
        'Stripe Price ID',
        'Stripe Amount',
        'Stripe Currency',
        'Stripe Interval',
        'Aligned',
    ], $rows);

    $mismatches = collect($rows)->where('Aligned', 'NO')->count();

    if ($mismatches > 0) {
        $this->error("Detected {$mismatches} plan pricing mismatches.");
        return self::FAILURE;
    }

    $this->info('All paid plans are aligned with Stripe.');
    return self::SUCCESS;
}
}
