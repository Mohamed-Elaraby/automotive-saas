<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('tenancy.database.central_connection') ?? config('database.default'))
            ->create('admin_notifications', function (Blueprint $table) {
                $table->id();

                $table->string('type', 100)->index();
                $table->string('title');
                $table->text('message')->nullable();
                $table->string('severity', 20)->default('info')->index();

                $table->string('source_type')->nullable()->index();
                $table->unsignedBigInteger('source_id')->nullable()->index();

                $table->string('route_name')->nullable()->index();
                $table->json('route_params')->nullable();
                $table->text('target_url')->nullable();

                $table->string('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('user_email')->nullable()->index();

                $table->json('context_payload')->nullable();

                $table->boolean('is_read')->default(false)->index();
                $table->timestamp('read_at')->nullable();

                $table->boolean('is_archived')->default(false)->index();
                $table->timestamp('archived_at')->nullable();

                $table->timestamp('notified_at')->nullable()->index();

                $table->timestamps();
            });
    }

    public function down(): void
    {
        Schema::connection(config('tenancy.database.central_connection') ?? config('database.default'))
            ->dropIfExists('admin_notifications');
    }
};
