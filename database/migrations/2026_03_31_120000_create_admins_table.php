<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected function centralConnection(): string
    {
        return (string) (Config::get('tenancy.database.central_connection') ?? Config::get('database.default'));
    }

    public function up(): void
    {
        Schema::connection($this->centralConnection())->create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->centralConnection())->dropIfExists('admins');
    }
};
