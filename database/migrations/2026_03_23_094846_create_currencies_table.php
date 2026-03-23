<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('tenancy.database.central_connection') ?? config('database.default'))
            ->create('currencies', function (Blueprint $table) {
                $table->id();
                $table->string('code', 3)->unique();
                $table->string('name');
                $table->string('symbol', 10)->nullable();
                $table->string('native_symbol', 20)->nullable();
                $table->unsignedTinyInteger('decimal_places')->default(2);
                $table->string('thousands_separator', 5)->default(',');
                $table->string('decimal_separator', 5)->default('.');
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->timestamps();
            });
    }

    public function down(): void
    {
        Schema::connection(config('tenancy.database.central_connection') ?? config('database.default'))
            ->dropIfExists('currencies');
    }
};
