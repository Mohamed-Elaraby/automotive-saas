<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Product;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $automotiveProductId = Product::query()
            ->where('code', 'automotive_service')
            ->value('id');

        $plans = [
            [
                'product_id' => $automotiveProductId,
                'name' => 'Trial',
                'slug' => 'trial',
                'price' => 0,
                'currency' => 'USD',
                'billing_period' => 'trial',
                'stripe_price_id' => '',
                'is_active' => true,
                'sort_order' => 1,
                'max_users' => 1,
                'max_branches' => 1,
                'max_products' => 200,
                'max_storage_mb' => 512,
                'description' => '14-day free trial plan.',
            ],
            [
                'product_id' => $automotiveProductId,
                'name' => 'Starter',
                'slug' => 'starter',
                'price' => 199,
                'currency' => 'USD',
                'billing_period' => 'monthly',
                'stripe_price_id' => 'price_1TBZeKA8nNgf0yeTf8v4hxFd',
                'is_active' => true,
                'sort_order' => 2,
                'max_users' => 3,
                'max_branches' => 1,
                'max_products' => 2000,
                'max_storage_mb' => 2048,
                'description' => 'Starter plan for small businesses.',
            ],
            [
                'product_id' => $automotiveProductId,
                'name' => 'Growth',
                'slug' => 'growth',
                'price' => 399,
                'currency' => 'USD',
                'billing_period' => 'monthly',
                'stripe_price_id' => 'price_1TBZeLA8nNgf0yeT8RdINXou',
                'is_active' => true,
                'sort_order' => 3,
                'max_users' => 10,
                'max_branches' => 3,
                'max_products' => 10000,
                'max_storage_mb' => 5120,
                'description' => 'Growth plan for expanding businesses.',
            ],
            [
                'product_id' => $automotiveProductId,
                'name' => 'Pro',
                'slug' => 'pro',
                'price' => 799,
                'currency' => 'USD',
                'billing_period' => 'monthly',
                'stripe_price_id' => 'price_1TBZeLA8nNgf0yeTSLJ97ZXy',
                'is_active' => true,
                'sort_order' => 4,
                'max_users' => 50,
                'max_branches' => 10,
                'max_products' => 50000,
                'max_storage_mb' => 20480,
                'description' => 'Advanced plan for larger operations.',
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
