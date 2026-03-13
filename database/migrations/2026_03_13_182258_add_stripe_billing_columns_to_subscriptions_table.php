<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'gateway')) {
                $table->string('gateway')->nullable()->after('status');
            }

            if (! Schema::hasColumn('subscriptions', 'gateway_customer_id')) {
                $table->string('gateway_customer_id')->nullable()->after('gateway');
            }

            if (! Schema::hasColumn('subscriptions', 'gateway_subscription_id')) {
                $table->string('gateway_subscription_id')->nullable()->after('gateway_customer_id');
            }

            if (! Schema::hasColumn('subscriptions', 'gateway_price_id')) {
                $table->string('gateway_price_id')->nullable()->after('gateway_subscription_id');
            }

            if (! Schema::hasColumn('subscriptions', 'gateway_checkout_session_id')) {
                $table->string('gateway_checkout_session_id')->nullable()->after('gateway_price_id');
            }

            if (! Schema::hasColumn('subscriptions', 'grace_ends_at')) {
                $table->timestamp('grace_ends_at')->nullable()->after('trial_ends_at');
            }

            if (! Schema::hasColumn('subscriptions', 'last_payment_failed_at')) {
                $table->timestamp('last_payment_failed_at')->nullable()->after('grace_ends_at');
            }

            if (! Schema::hasColumn('subscriptions', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('last_payment_failed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $columns = [
                'gateway',
                'gateway_customer_id',
                'gateway_subscription_id',
                'gateway_price_id',
                'gateway_checkout_session_id',
                'grace_ends_at',
                'last_payment_failed_at',
                'cancelled_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
