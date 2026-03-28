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
        Schema::connection($this->centralConnection())->create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group_key', 100)->default('general');
            $table->string('key', 150)->unique();
            $table->longText('value')->nullable();
            $table->string('value_type', 30)->default('string');
            $table->timestamps();

            $table->index('group_key');
            $table->index('key');
        });
    }

    public function down(): void
    {
        Schema::connection($this->centralConnection())->dropIfExists('app_settings');
    }
};
