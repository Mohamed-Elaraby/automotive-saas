<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('tenancy.database.central_connection') ?? config('database.default'))
            ->create('system_error_logs', function (Blueprint $table) {
                $table->id();

                $table->timestamp('occurred_at')->index();
                $table->string('level', 20)->default('error')->index();

                $table->string('exception_class')->index();
                $table->text('message');

                $table->text('file_path')->nullable();
                $table->unsignedInteger('file_line')->nullable();

                $table->longText('trace_excerpt')->nullable();

                $table->string('app_env', 50)->nullable()->index();
                $table->text('app_url')->nullable();

                $table->string('request_method', 20)->nullable()->index();
                $table->text('request_url')->nullable();
                $table->text('request_path')->nullable();

                $table->string('route_name')->nullable()->index();
                $table->text('route_action')->nullable();

                $table->string('ip', 45)->nullable()->index();
                $table->text('user_agent')->nullable();

                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('user_email')->nullable()->index();
                $table->string('tenant_id')->nullable()->index();

                $table->json('input_payload')->nullable();
                $table->json('context_payload')->nullable();

                $table->boolean('is_read')->default(false)->index();
                $table->timestamp('read_at')->nullable();

                $table->boolean('is_resolved')->default(false)->index();
                $table->timestamp('resolved_at')->nullable();

                $table->timestamps();
            });
    }

    public function down(): void
    {
        Schema::connection(config('tenancy.database.central_connection') ?? config('database.default'))
            ->dropIfExists('system_error_logs');
    }

};
