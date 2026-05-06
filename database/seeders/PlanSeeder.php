<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Product;
use App\Services\Billing\StripePlanCatalogSyncService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($products as $index => $product) {
            $this->seedProductPlans($product, $index + 1);
        }
    }

    protected function seedProductPlans(Product $product, int $productOrder): void
    {
        $code = (string) $product->code;
        $slugPrefix = Str::slug($code ?: $product->slug ?: $product->name);
        $namePrefix = trim((string) $product->name);
        $profile = $this->productPricingProfile($code);

        $definitions = [];

        $definitions[] = [
            'name' => "{$namePrefix} Trial",
            'slug' => "{$slugPrefix}-trial",
            'price' => 0,
            'billing_period' => 'trial',
            'sort_order' => ($productOrder * 10) + 1,
            'trial_days' => $profile['trial_days'],
            'max_users' => $profile['trial_limits']['max_users'],
            'max_branches' => $profile['trial_limits']['max_branches'],
            'max_products' => $profile['trial_limits']['max_products'],
            'max_storage_mb' => $profile['trial_limits']['max_storage_mb'],
            'description' => $profile['trial_description'] ?? "Trial plan for {$namePrefix}.",
        ];

        $definitions[] = [
            'name' => "{$namePrefix} Starter",
            'slug' => "{$slugPrefix}-starter-monthly",
            'price' => $profile['monthly_starter']['price'],
            'billing_period' => 'monthly',
            'sort_order' => ($productOrder * 10) + 2,
            'trial_days' => null,
            'max_users' => $profile['monthly_starter']['max_users'],
            'max_branches' => $profile['monthly_starter']['max_branches'],
            'max_products' => $profile['monthly_starter']['max_products'],
            'max_storage_mb' => $profile['monthly_starter']['max_storage_mb'],
            'description' => $profile['monthly_starter']['description'] ?? "Starter monthly plan for {$namePrefix}.",
        ];

        $definitions[] = [
            'name' => "{$namePrefix} Growth",
            'slug' => "{$slugPrefix}-growth-monthly",
            'price' => $profile['monthly_growth']['price'],
            'billing_period' => 'monthly',
            'sort_order' => ($productOrder * 10) + 3,
            'trial_days' => null,
            'max_users' => $profile['monthly_growth']['max_users'],
            'max_branches' => $profile['monthly_growth']['max_branches'],
            'max_products' => $profile['monthly_growth']['max_products'],
            'max_storage_mb' => $profile['monthly_growth']['max_storage_mb'],
            'description' => $profile['monthly_growth']['description'] ?? "Growth monthly plan for {$namePrefix}.",
        ];

        $definitions[] = [
            'name' => "{$namePrefix} Pro",
            'slug' => "{$slugPrefix}-pro-yearly",
            'price' => $profile['yearly_pro']['price'],
            'billing_period' => 'yearly',
            'sort_order' => ($productOrder * 10) + 4,
            'trial_days' => null,
            'max_users' => $profile['yearly_pro']['max_users'],
            'max_branches' => $profile['yearly_pro']['max_branches'],
            'max_products' => $profile['yearly_pro']['max_products'],
            'max_storage_mb' => $profile['yearly_pro']['max_storage_mb'],
            'description' => $profile['yearly_pro']['description'] ?? "Pro yearly plan for {$namePrefix}.",
        ];

        foreach ($definitions as $definition) {
            $plan = Plan::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'product_id' => $product->id,
                    'name' => $definition['name'],
                    'price' => $definition['price'],
                    'currency' => 'USD',
                    'billing_period' => $definition['billing_period'],
                    'trial_days' => $definition['trial_days'],
                    'is_active' => true,
                    'sort_order' => $definition['sort_order'],
                    'max_users' => $definition['max_users'],
                    'max_branches' => $definition['max_branches'],
                    'max_products' => $definition['max_products'],
                    'max_storage_mb' => $definition['max_storage_mb'],
                    'description' => $definition['description'],
                    'stripe_product_id' => $definition['billing_period'] === 'trial' ? null : null,
                    'stripe_price_id' => $definition['billing_period'] === 'trial' ? null : null,
                ]
            );

            if ($definition['billing_period'] !== 'trial') {
                app(StripePlanCatalogSyncService::class)->syncPlan($plan);
            }

            $this->syncNormalizedPlanLimits($plan, $code);
        }
    }

    protected function syncNormalizedPlanLimits(Plan $plan, string $productKey): void
    {
        $connection = $plan->getConnectionName();

        if (! Schema::connection($connection)->hasTable('plan_limits')) {
            return;
        }

        foreach ([
            'included_seats' => $plan->max_users,
            'branch_limit' => $plan->max_branches,
            'catalog_items' => $plan->max_products,
            'storage_mb' => $plan->max_storage_mb,
        ] as $limitKey => $limitValue) {
            if ($limitValue === null) {
                continue;
            }

            DB::connection($connection)
                ->table('plan_limits')
                ->updateOrInsert(
                    [
                        'product_key' => $productKey,
                        'plan_id' => $plan->id,
                        'limit_key' => $limitKey,
                    ],
                    [
                        'limit_value' => (string) $limitValue,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
        }
    }

    protected function productPricingProfile(string $code): array
    {
        return match ($code) {
            'parts_inventory' => [
                'trial_days' => 10,
                'trial_description' => 'Trial plan for Parts Inventory Management with a small purchasing and stock setup.',
                'trial_limits' => ['max_users' => 1, 'max_branches' => 1, 'max_products' => 300, 'max_storage_mb' => 512],
                'monthly_starter' => ['price' => 129, 'max_users' => 2, 'max_branches' => 1, 'max_products' => 3000, 'max_storage_mb' => 2048, 'description' => 'Starter monthly plan for parts inventory teams.'],
                'monthly_growth' => ['price' => 259, 'max_users' => 8, 'max_branches' => 3, 'max_products' => 15000, 'max_storage_mb' => 5120, 'description' => 'Growth monthly plan for larger spare-parts operations.'],
                'yearly_pro' => ['price' => 2199, 'max_users' => 35, 'max_branches' => 8, 'max_products' => 60000, 'max_storage_mb' => 20480, 'description' => 'Pro yearly plan for full spare-parts operations.'],
            ],
            'accounting' => [
                'trial_days' => 21,
                'trial_description' => 'Trial plan for Accounting System with a guided finance setup period.',
                'trial_limits' => ['max_users' => 2, 'max_branches' => 1, 'max_products' => 500, 'max_storage_mb' => 768],
                'monthly_starter' => ['price' => 179, 'max_users' => 3, 'max_branches' => 1, 'max_products' => 5000, 'max_storage_mb' => 3072, 'description' => 'Starter monthly plan for accounting teams.'],
                'monthly_growth' => ['price' => 349, 'max_users' => 12, 'max_branches' => 4, 'max_products' => 20000, 'max_storage_mb' => 8192, 'description' => 'Growth monthly plan for expanding finance operations.'],
                'yearly_pro' => ['price' => 2899, 'max_users' => 60, 'max_branches' => 12, 'max_products' => 80000, 'max_storage_mb' => 30720, 'description' => 'Pro yearly plan for advanced accounting deployments.'],
            ],
            default => [
                'trial_days' => 14,
                'trial_description' => 'Trial plan for Automotive Service Management.',
                'trial_limits' => ['max_users' => 1, 'max_branches' => 1, 'max_products' => 200, 'max_storage_mb' => 512],
                'monthly_starter' => ['price' => 149, 'max_users' => 3, 'max_branches' => 1, 'max_products' => 2000, 'max_storage_mb' => 2048, 'description' => 'Starter monthly plan for automotive service teams.'],
                'monthly_growth' => ['price' => 299, 'max_users' => 10, 'max_branches' => 3, 'max_products' => 10000, 'max_storage_mb' => 5120, 'description' => 'Growth monthly plan for busy workshop operations.'],
                'yearly_pro' => ['price' => 2499, 'max_users' => 50, 'max_branches' => 10, 'max_products' => 50000, 'max_storage_mb' => 20480, 'description' => 'Pro yearly plan for multi-branch automotive service operations.'],
            ],
        };
    }
}
