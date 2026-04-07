<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('tenancy.database.central_connection') ?? config('database.default'))
            ->create('customer_portal_notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('type', 100)->index();
                $table->string('title');
                $table->text('message')->nullable();
                $table->string('severity', 20)->default('info')->index();
                $table->string('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->text('target_url')->nullable();
                $table->json('context_payload')->nullable();
                $table->boolean('is_read')->default(false)->index();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('notified_at')->nullable()->index();
                $table->timestamps();
            });
    }

    public function down(): void
    {
        Schema::connection(config('tenancy.database.central_connection') ?? config('database.default'))
            ->dropIfExists('customer_portal_notifications');
    }
};
