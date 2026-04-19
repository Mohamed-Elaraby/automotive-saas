<?php

use App\Support\Billing\SubscriptionStatuses;
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

        if (! Schema::connection($connection)->hasTable('tenant_product_subscriptions')) {
            return;
        }

        Schema::connection($connection)->table('tenant_product_subscriptions', function (Blueprint $table) use ($connection) {
            if (! Schema::connection($connection)->hasColumn('tenant_product_subscriptions', 'activation_status')) {
                $table->string('activation_status')->default('pending')->after('status');
            }

            if (! Schema::connection($connection)->hasColumn('tenant_product_subscriptions', 'provisioning_status')) {
                $table->string('provisioning_status')->default('pending')->after('activation_status');
            }

            if (! Schema::connection($connection)->hasColumn('tenant_product_subscriptions', 'provisioning_started_at')) {
                $table->timestamp('provisioning_started_at')->nullable()->after('provisioning_status');
            }

            if (! Schema::connection($connection)->hasColumn('tenant_product_subscriptions', 'provisioning_completed_at')) {
                $table->timestamp('provisioning_completed_at')->nullable()->after('provisioning_started_at');
            }

            if (! Schema::connection($connection)->hasColumn('tenant_product_subscriptions', 'provisioning_failed_at')) {
                $table->timestamp('provisioning_failed_at')->nullable()->after('provisioning_completed_at');
            }

            if (! Schema::connection($connection)->hasColumn('tenant_product_subscriptions', 'activated_at')) {
                $table->timestamp('activated_at')->nullable()->after('provisioning_failed_at');
            }

            if (! Schema::connection($connection)->hasColumn('tenant_product_subscriptions', 'activation_error')) {
                $table->text('activation_error')->nullable()->after('activated_at');
            }

            if (! Schema::connection($connection)->hasColumn('tenant_product_subscriptions', 'activation_source')) {
                $table->string('activation_source')->nullable()->after('activation_error');
            }
        });

        DB::connection($connection)
            ->table('tenant_product_subscriptions')
            ->whereIn('status', SubscriptionStatuses::accessAllowedStatuses())
            ->update([
                'activation_status' => 'active',
                'provisioning_status' => 'active',
                'provisioning_completed_at' => now(),
                'activated_at' => now(),
                'activation_source' => 'migration_backfill',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $connection = $this->centralConnection();

        if (! Schema::connection($connection)->hasTable('tenant_product_subscriptions')) {
            return;
        }

        Schema::connection($connection)->table('tenant_product_subscriptions', function (Blueprint $table) use ($connection) {
            foreach ([
                'activation_source',
                'activation_error',
                'activated_at',
                'provisioning_failed_at',
                'provisioning_completed_at',
                'provisioning_started_at',
                'provisioning_status',
                'activation_status',
            ] as $column) {
                if (Schema::connection($connection)->hasColumn('tenant_product_subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
