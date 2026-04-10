<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
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

        if (! Schema::connection($connection)->hasTable('tenant_product_subscriptions')) {
            return;
        }

        Schema::connection($connection)->table('tenant_product_subscriptions', function (Blueprint $table) {
            $table->timestamp('last_synced_from_stripe_at')->nullable()->after('gateway_price_id');
            $table->string('last_sync_status', 50)->nullable()->after('last_synced_from_stripe_at');
            $table->text('last_sync_error')->nullable()->after('last_sync_status');
        });
    }

    public function down(): void
    {
        $connection = $this->centralConnection();

        if (! Schema::connection($connection)->hasTable('tenant_product_subscriptions')) {
            return;
        }

        Schema::connection($connection)->table('tenant_product_subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'last_synced_from_stripe_at',
                'last_sync_status',
                'last_sync_error',
            ]);
        });
    }
};
