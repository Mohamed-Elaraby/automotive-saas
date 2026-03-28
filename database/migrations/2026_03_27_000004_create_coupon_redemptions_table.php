<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected function centralConnection(): string
    {
        return (string) (Config::get('tenancy.database.central_connection') ?: Config::get('database.default'));
    }

    public function up(): void
    {
        Schema::connection($this->centralConnection())->create('coupon_redemptions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('coupon_id');
            $table->string('tenant_id')->nullable();
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->unsignedBigInteger('plan_id')->nullable();

            $table->string('status', 30)->default('applied'); // applied | consumed | reversed
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->string('currency_code', 10)->nullable();

            $table->json('context_payload')->nullable();

            $table->timestamps();

            $table->index('coupon_id');
            $table->index('tenant_id');
            $table->index('subscription_id');
            $table->index('plan_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection($this->centralConnection())->dropIfExists('coupon_redemptions');
    }
};
