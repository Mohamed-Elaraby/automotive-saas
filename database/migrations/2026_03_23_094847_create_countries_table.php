<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('tenancy.database.central_connection') ?? config('database.default'))
            ->create('countries', function (Blueprint $table) {
                $table->id();
                $table->string('iso2', 2)->unique();
                $table->string('iso3', 3)->unique();
                $table->string('name');
                $table->string('native_name')->nullable();
                $table->string('phone_code', 10)->nullable();
                $table->string('capital')->nullable();
                $table->string('currency_code', 3)->nullable()->index();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->timestamps();

                $table->foreign('currency_code')
                    ->references('code')
                    ->on('currencies')
                    ->nullOnDelete();
            });
    }

    public function down(): void
    {
        Schema::connection(config('tenancy.database.central_connection') ?? config('database.default'))
            ->dropIfExists('countries');
    }
};
