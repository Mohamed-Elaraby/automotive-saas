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
        Schema::connection($this->centralConnection())->create('admin_activity_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('admin_user_id')->nullable();
            $table->string('admin_email')->nullable();

            $table->string('action', 150);
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->string('tenant_id')->nullable();

            $table->json('context_payload')->nullable();

            $table->timestamps();

            $table->index('admin_user_id');
            $table->index('action');
            $table->index('subject_type');
            $table->index('subject_id');
            $table->index('tenant_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection($this->centralConnection())->dropIfExists('admin_activity_logs');
    }
};
