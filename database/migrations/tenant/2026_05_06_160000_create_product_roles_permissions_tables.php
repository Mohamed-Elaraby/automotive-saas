<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_roles')) {
            Schema::create('product_roles', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('product_key', 80);
                $table->string('name', 120);
                $table->string('slug', 140);
                $table->text('description')->nullable();
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'product_key', 'slug'], 'prod_roles_tenant_product_slug_uq');
                $table->index(['tenant_id', 'product_key', 'is_active'], 'prod_roles_lookup_idx');
            });
        }

        if (! Schema::hasTable('product_permissions')) {
            Schema::create('product_permissions', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('product_key', 80);
                $table->string('permission_key', 160);
                $table->string('name', 160);
                $table->string('group_key', 80)->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'product_key', 'permission_key'], 'prod_perms_tenant_product_key_uq');
                $table->index(['tenant_id', 'product_key', 'is_active'], 'prod_perms_lookup_idx');
            });
        }

        if (! Schema::hasTable('product_role_permission')) {
            Schema::create('product_role_permission', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_role_id');
                $table->foreignId('product_permission_id');
                $table->timestamps();

                $table->foreign('product_role_id', 'prp_role_fk')->references('id')->on('product_roles')->cascadeOnDelete();
                $table->foreign('product_permission_id', 'prp_perm_fk')->references('id')->on('product_permissions')->cascadeOnDelete();
                $table->unique(['product_role_id', 'product_permission_id'], 'prp_role_perm_uq');
            });
        }

        if (! Schema::hasTable('tenant_user_product_roles')) {
            Schema::create('tenant_user_product_roles', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->foreignId('user_id');
                $table->string('product_key', 80);
                $table->foreignId('product_role_id');
                $table->boolean('is_active')->default(true);
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('user_id', 'tupr_user_fk')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('product_role_id', 'tupr_role_fk')->references('id')->on('product_roles')->cascadeOnDelete();
                $table->unique(['tenant_id', 'user_id', 'product_key', 'product_role_id'], 'tupr_tenant_user_product_role_uq');
                $table->index(['tenant_id', 'product_key', 'is_active'], 'tupr_lookup_idx');
                $table->index(['user_id', 'product_key', 'is_active'], 'tupr_user_product_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user_product_roles');
        Schema::dropIfExists('product_role_permission');
        Schema::dropIfExists('product_permissions');
        Schema::dropIfExists('product_roles');
    }
};
