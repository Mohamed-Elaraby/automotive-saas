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
        Schema::connection($this->centralConnection())->create('coupons', function (Blueprint $table) {
            $table->id();

            $table->string('code')->unique();
            $table->string('name');
            $table->string('discount_type', 20); // percentage | fixed
            $table->decimal('discount_value', 12, 2);

            $table->string('currency_code', 10)->nullable(); // used for fixed discounts when needed
            $table->boolean('is_active')->default(true);

            $table->boolean('applies_to_all_plans')->default(true);
            $table->boolean('first_billing_cycle_only')->default(false);

            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('max_redemptions_per_tenant')->nullable();
            $table->unsignedInteger('times_redeemed')->default(0);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('code');
            $table->index('discount_type');
            $table->index('is_active');
            $table->index('starts_at');
            $table->index('ends_at');
        });
    }

    public function down(): void
    {
        Schema::connection($this->centralConnection())->dropIfExists('coupons');
    }
};
