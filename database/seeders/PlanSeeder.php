<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Product;
use App\Services\Billing\StripePlanCatalogSyncService;
use Illuminate\Database\Seeder;
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

        $definitions = [];

        if ($code === 'automotive_service') {
            $definitions[] = [
                'name' => "{$namePrefix} Trial",
                'slug' => "{$slugPrefix}-trial",
                'price' => 0,
                'billing_period' => 'trial',
                'sort_order' => ($productOrder * 10) + 1,
                'max_users' => 1,
                'max_branches' => 1,
                'max_products' => 200,
                'max_storage_mb' => 512,
                'description' => "Trial plan for {$namePrefix}.",
            ];
        }

        $definitions[] = [
            'name' => "{$namePrefix} Starter",
            'slug' => "{$slugPrefix}-starter-monthly",
            'price' => 149,
            'billing_period' => 'monthly',
            'sort_order' => ($productOrder * 10) + 2,
            'max_users' => 3,
            'max_branches' => 1,
            'max_products' => 2000,
            'max_storage_mb' => 2048,
            'description' => "Starter monthly plan for {$namePrefix}.",
        ];

        $definitions[] = [
            'name' => "{$namePrefix} Growth",
            'slug' => "{$slugPrefix}-growth-monthly",
            'price' => 299,
            'billing_period' => 'monthly',
            'sort_order' => ($productOrder * 10) + 3,
            'max_users' => 10,
            'max_branches' => 3,
            'max_products' => 10000,
            'max_storage_mb' => 5120,
            'description' => "Growth monthly plan for {$namePrefix}.",
        ];

        $definitions[] = [
            'name' => "{$namePrefix} Pro",
            'slug' => "{$slugPrefix}-pro-yearly",
            'price' => 2499,
            'billing_period' => 'yearly',
            'sort_order' => ($productOrder * 10) + 4,
            'max_users' => 50,
            'max_branches' => 10,
            'max_products' => 50000,
            'max_storage_mb' => 20480,
            'description' => "Pro yearly plan for {$namePrefix}.",
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
        }
    }
}
