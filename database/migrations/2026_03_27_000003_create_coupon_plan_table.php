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
        Schema::connection($this->centralConnection())->create('coupon_plan', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('coupon_id');
            $table->unsignedBigInteger('plan_id');

            $table->timestamps();

            $table->unique(['coupon_id', 'plan_id']);

            $table->index('coupon_id');
            $table->index('plan_id');
        });
    }

    public function down(): void
    {
        Schema::connection($this->centralConnection())->dropIfExists('coupon_plan');
    }
};
