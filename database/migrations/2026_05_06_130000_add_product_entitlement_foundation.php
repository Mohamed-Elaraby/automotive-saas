<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected function centralConnection(): string
    {
        return Config::get('tenancy.database.central_connection') ?: Config::get('database.default');
    }

    public function up(): void
    {
        $connection = $this->centralConnection();

        if (Schema::connection($connection)->hasTable('tenant_product_subscriptions')) {
            Schema::connection($connection)->table('tenant_product_subscriptions', function (Blueprint $table) use ($connection) {
                if (! Schema::connection($connection)->hasColumn('tenant_product_subscriptions', 'product_key')) {
                    $table->string('product_key', 80)->nullable()->after('product_id');
                    $table->index(['tenant_id', 'product_key'], 'tps_tenant_product_key_idx');
                    $table->index(['product_key', 'status'], 'tps_product_key_status_idx');
                }

                if (! Schema::connection($connection)->hasColumn('tenant_product_subscriptions', 'included_seats')) {
                    $table->unsignedInteger('included_seats')->nullable()->after('status');
                }

                if (! Schema::connection($connection)->hasColumn('tenant_product_subscriptions', 'extra_seats')) {
                    $table->unsignedInteger('extra_seats')->default(0)->after('included_seats');
                }

                if (! Schema::connection($connection)->hasColumn('tenant_product_subscriptions', 'branch_limit')) {
                    $table->unsignedInteger('branch_limit')->nullable()->after('extra_seats');
                }

                if (! Schema::connection($connection)->hasColumn('tenant_product_subscriptions', 'usage_limits')) {
                    $table->json('usage_limits')->nullable()->after('branch_limit');
                }

                if (! Schema::connection($connection)->hasColumn('tenant_product_subscriptions', 'current_period_start')) {
                    $table->timestamp('current_period_start')->nullable()->after('trial_ends_at');
                }

                if (! Schema::connection($connection)->hasColumn('tenant_product_subscriptions', 'current_period_end')) {
                    $table->timestamp('current_period_end')->nullable()->after('current_period_start');
                }
            });

            $this->backfillProductKeys($connection);
            $this->backfillCommercialLimits($connection);
        }

        if (! Schema::connection($connection)->hasTable('plan_limits')) {
            Schema::connection($connection)->create('plan_limits', function (Blueprint $table) {
                $table->id();
                $table->string('product_key', 80);
                $table->foreignId('plan_id');
                $table->string('limit_key', 80);
                $table->string('limit_value')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('plan_id', 'plan_limits_plan_fk')->references('id')->on('plans')->cascadeOnDelete();
                $table->unique(['product_key', 'plan_id', 'limit_key'], 'plan_limits_product_plan_key_uq');
                $table->index(['product_key', 'limit_key'], 'plan_limits_product_key_idx');
            });

            $this->seedPlanLimitsFromLegacyColumns($connection);
        }

        if (! Schema::connection($connection)->hasTable('subscription_addons')) {
            Schema::connection($connection)->create('subscription_addons', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('product_key', 80);
                $table->string('addon_key', 80);
                $table->unsignedInteger('quantity')->default(1);
                $table->string('status', 40)->default('active');
                $table->json('metadata')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'product_key'], 'sub_addons_tenant_product_idx');
                $table->index(['product_key', 'addon_key', 'status'], 'sub_addons_lookup_idx');
            });
        }
    }

    public function down(): void
    {
        $connection = $this->centralConnection();

        Schema::connection($connection)->dropIfExists('subscription_addons');
        Schema::connection($connection)->dropIfExists('plan_limits');

        if (! Schema::connection($connection)->hasTable('tenant_product_subscriptions')) {
            return;
        }

        Schema::connection($connection)->table('tenant_product_subscriptions', function (Blueprint $table) use ($connection) {
            foreach ([
                'current_period_end',
                'current_period_start',
                'usage_limits',
                'branch_limit',
                'extra_seats',
                'included_seats',
                'product_key',
            ] as $column) {
                if (Schema::connection($connection)->hasColumn('tenant_product_subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    protected function backfillProductKeys(string $connection): void
    {
        if (
            ! Schema::connection($connection)->hasColumn('tenant_product_subscriptions', 'product_key')
            || ! Schema::connection($connection)->hasTable('products')
        ) {
            return;
        }

        DB::connection($connection)
            ->table('tenant_product_subscriptions')
            ->whereNull('product_key')
            ->orderBy('id')
            ->select(['id', 'product_id'])
            ->chunkById(200, function ($subscriptions) use ($connection): void {
                $productCodes = DB::connection($connection)
                    ->table('products')
                    ->whereIn('id', $subscriptions->pluck('product_id')->filter()->unique()->all())
                    ->pluck('code', 'id');

                foreach ($subscriptions as $subscription) {
                    $productKey = $productCodes->get($subscription->product_id);

                    if ($productKey) {
                        DB::connection($connection)
                            ->table('tenant_product_subscriptions')
                            ->where('id', $subscription->id)
                            ->update(['product_key' => $productKey]);
                    }
                }
            });
    }

    protected function backfillCommercialLimits(string $connection): void
    {
        if (
            ! Schema::connection($connection)->hasTable('plans')
            || ! Schema::connection($connection)->hasColumn('tenant_product_subscriptions', 'included_seats')
            || ! Schema::connection($connection)->hasColumn('tenant_product_subscriptions', 'branch_limit')
        ) {
            return;
        }

        DB::connection($connection)
            ->table('tenant_product_subscriptions')
            ->where(function ($query): void {
                $query->whereNull('included_seats')
                    ->orWhereNull('branch_limit');
            })
            ->orderBy('id')
            ->select(['id', 'plan_id'])
            ->chunkById(200, function ($subscriptions) use ($connection): void {
                $plans = DB::connection($connection)
                    ->table('plans')
                    ->whereIn('id', $subscriptions->pluck('plan_id')->filter()->unique()->all())
                    ->get(['id', 'max_users', 'max_branches'])
                    ->keyBy('id');

                foreach ($subscriptions as $subscription) {
                    $plan = $plans->get($subscription->plan_id);

                    if (! $plan) {
                        continue;
                    }

                    DB::connection($connection)
                        ->table('tenant_product_subscriptions')
                        ->where('id', $subscription->id)
                        ->update([
                            'included_seats' => $plan->max_users,
                            'branch_limit' => $plan->max_branches,
                        ]);
                }
            });
    }

    protected function seedPlanLimitsFromLegacyColumns(string $connection): void
    {
        if (
            ! Schema::connection($connection)->hasTable('plans')
            || ! Schema::connection($connection)->hasTable('products')
        ) {
            return;
        }

        DB::connection($connection)
            ->table('plans')
            ->leftJoin('products', 'products.id', '=', 'plans.product_id')
            ->orderBy('plans.id')
            ->select([
                'plans.id',
                'plans.max_users',
                'plans.max_branches',
                'plans.max_products',
                'plans.max_storage_mb',
                'products.code as product_key',
            ])
            ->chunkById(200, function ($plans) use ($connection): void {
                foreach ($plans as $plan) {
                    $productKey = (string) ($plan->product_key ?? '');

                    if ($productKey === '') {
                        continue;
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
            }, 'plans.id', 'id');
    }
};
