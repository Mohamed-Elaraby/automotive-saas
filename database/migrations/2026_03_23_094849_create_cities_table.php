<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('tenancy.database.central_connection') ?? config('database.default'))
            ->create('cities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
                $table->foreignId('state_id')->constrained('states')->cascadeOnDelete();
                $table->string('name');
                $table->string('native_name')->nullable();
                $table->string('postal_code', 20)->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->timestamps();

                $table->unique(['state_id', 'name']);
            });
    }

    public function down(): void
    {
        Schema::connection(config('tenancy.database.central_connection') ?? config('database.default'))
            ->dropIfExists('cities');
    }
};
