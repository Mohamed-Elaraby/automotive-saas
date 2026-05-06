<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_user_product_access')) {
            return;
        }

        Schema::create('tenant_user_product_access', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('user_id');
            $table->string('product_key', 80);
            $table->string('status', 40)->default('active');
            $table->unsignedBigInteger('role_id')->nullable();
            $table->foreignId('granted_by')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('user_id', 'tupa_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('granted_by', 'tupa_granted_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique(['tenant_id', 'user_id', 'product_key'], 'tupa_tenant_user_product_uq');
            $table->index(['tenant_id', 'product_key', 'status'], 'tupa_tenant_product_status_idx');
            $table->index(['user_id', 'status'], 'tupa_user_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user_product_access');
    }
};
