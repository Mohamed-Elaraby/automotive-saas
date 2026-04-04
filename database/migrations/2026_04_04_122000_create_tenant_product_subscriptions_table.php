<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected function centralConnection(): ?string
    {
        return Config::get('tenancy.database.central_connection') ?: Config::get('database.default');
    }

    public function up(): void
    {
        $connection = $this->centralConnection();

        Schema::connection($connection)->create('tenant_product_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('plan_id')
                ->nullable()
                ->constrained('plans')
                ->nullOnDelete();
            $table->foreignId('legacy_subscription_id')
                ->nullable()
                ->constrained('subscriptions')
                ->nullOnDelete();
            $table->string('status')->default('trialing');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('last_payment_failed_at')->nullable();
            $table->timestamp('past_due_started_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('payment_failures_count')->default(0);
            $table->string('external_id')->nullable();
            $table->string('gateway')->nullable();
            $table->string('gateway_customer_id')->nullable();
            $table->string('gateway_subscription_id')->nullable();
            $table->string('gateway_checkout_session_id')->nullable();
            $table->string('gateway_price_id')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'product_id'], 'tenant_product_subscriptions_tenant_product_index');
            $table->index(['status', 'grace_ends_at'], 'tenant_product_subscriptions_status_grace_index');
            $table->unique(['tenant_id', 'product_id', 'legacy_subscription_id'], 'tenant_product_subscriptions_unique_legacy_index');
        });

        if (! Schema::connection($connection)->hasTable('subscriptions')) {
            return;
        }

        $subscriptions = DB::connection($connection)
            ->table('subscriptions')
            ->leftJoin('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->whereNotNull('plans.product_id')
            ->select([
                'subscriptions.id',
                'subscriptions.tenant_id',
                'subscriptions.plan_id',
                'plans.product_id',
                'subscriptions.status',
                'subscriptions.trial_ends_at',
                'subscriptions.grace_ends_at',
                'subscriptions.last_payment_failed_at',
                'subscriptions.past_due_started_at',
                'subscriptions.suspended_at',
                'subscriptions.cancelled_at',
                'subscriptions.ends_at',
                'subscriptions.payment_failures_count',
                'subscriptions.external_id',
                'subscriptions.gateway',
                'subscriptions.gateway_customer_id',
                'subscriptions.gateway_subscription_id',
                'subscriptions.gateway_checkout_session_id',
                'subscriptions.gateway_price_id',
                'subscriptions.created_at',
                'subscriptions.updated_at',
            ])
            ->orderBy('subscriptions.id')
            ->get();

        foreach ($subscriptions as $subscription) {
            DB::connection($connection)
                ->table('tenant_product_subscriptions')
                ->updateOrInsert(
                    [
                        'tenant_id' => $subscription->tenant_id,
                        'product_id' => $subscription->product_id,
                        'legacy_subscription_id' => $subscription->id,
                    ],
                    [
                        'plan_id' => $subscription->plan_id,
                        'status' => $subscription->status,
                        'trial_ends_at' => $subscription->trial_ends_at,
                        'grace_ends_at' => $subscription->grace_ends_at,
                        'last_payment_failed_at' => $subscription->last_payment_failed_at,
                        'past_due_started_at' => $subscription->past_due_started_at,
                        'suspended_at' => $subscription->suspended_at,
                        'cancelled_at' => $subscription->cancelled_at,
                        'ends_at' => $subscription->ends_at,
                        'payment_failures_count' => $subscription->payment_failures_count ?? 0,
                        'external_id' => $subscription->external_id,
                        'gateway' => $subscription->gateway,
                        'gateway_customer_id' => $subscription->gateway_customer_id,
                        'gateway_subscription_id' => $subscription->gateway_subscription_id,
                        'gateway_checkout_session_id' => $subscription->gateway_checkout_session_id,
                        'gateway_price_id' => $subscription->gateway_price_id,
                        'created_at' => $subscription->created_at ?? now(),
                        'updated_at' => $subscription->updated_at ?? now(),
                    ]
                );
        }
    }

    public function down(): void
    {
        Schema::connection($this->centralConnection())->dropIfExists('tenant_product_subscriptions');
    }
};
