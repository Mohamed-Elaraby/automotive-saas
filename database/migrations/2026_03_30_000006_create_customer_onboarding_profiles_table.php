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
        Schema::connection($this->centralConnection())->create('customer_onboarding_profiles', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->unique();
            $table->string('company_name');
            $table->string('subdomain', 50)->unique();
            $table->string('coupon_code', 100)->nullable();
            $table->string('base_host')->nullable();
            $table->longText('password_payload')->nullable();

            $table->timestamps();

            $table->index('coupon_code');
        });
    }

    public function down(): void
    {
        Schema::connection($this->centralConnection())->dropIfExists('customer_onboarding_profiles');
    }
};
