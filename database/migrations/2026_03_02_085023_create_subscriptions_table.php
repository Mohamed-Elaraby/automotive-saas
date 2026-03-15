<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            // stancl tenant id (string)
            $table->string('tenant_id');

            $table->foreignId('plan_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->enum('status', [
                'trialing',
                'active',
                'past_due',
                'suspended',
                'canceled',
                'expired',
            ])->default('trialing');

            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('last_payment_failed_at')->nullable();
            $table->timestamp('past_due_started_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->unsignedInteger('payment_failures_count')->default(0);

            // Generic gateway columns for Stripe / future gateways
            $table->string('external_id')->nullable();
            $table->string('gateway')->nullable();
            $table->string('gateway_customer_id')->nullable();
            $table->string('gateway_subscription_id')->nullable();
            $table->string('gateway_checkout_session_id')->nullable();
            $table->string('gateway_price_id')->nullable();

            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status'], 'subscriptions_tenant_id_status_index');
            $table->index('gateway_subscription_id', 'subscriptions_gateway_subscription_id_index');
            $table->index(['status', 'grace_ends_at'], 'subscriptions_status_grace_ends_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
