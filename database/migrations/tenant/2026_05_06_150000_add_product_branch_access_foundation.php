<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (! Schema::hasColumn('branches', 'manager_user_id')) {
                $table->unsignedBigInteger('manager_user_id')->nullable()->after('email');
                $table->foreign('manager_user_id', 'branches_manager_fk')->references('id')->on('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('branches', 'emirate')) {
                $table->string('emirate', 80)->nullable()->after('address');
            }

            if (! Schema::hasColumn('branches', 'city')) {
                $table->string('city', 120)->nullable()->after('emirate');
            }

            if (! Schema::hasColumn('branches', 'country')) {
                $table->string('country', 120)->nullable()->after('city');
            }

            if (! Schema::hasColumn('branches', 'timezone')) {
                $table->string('timezone', 120)->nullable()->after('country');
            }
        });

        if (! Schema::hasTable('tenant_product_branches')) {
            Schema::create('tenant_product_branches', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('product_key', 80);
                $table->foreignId('branch_id');
                $table->boolean('is_enabled')->default(true);
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('deactivated_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('branch_id', 'tpb_branch_fk')->references('id')->on('branches')->cascadeOnDelete();
                $table->unique(['tenant_id', 'product_key', 'branch_id'], 'tpb_tenant_product_branch_uq');
                $table->index(['tenant_id', 'product_key', 'is_enabled'], 'tpb_tenant_product_enabled_idx');
            });
        }

        if (! Schema::hasTable('tenant_user_product_branches')) {
            Schema::create('tenant_user_product_branches', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->foreignId('user_id');
                $table->string('product_key', 80);
                $table->foreignId('branch_id');
                $table->string('access_level', 40)->default('member');
                $table->boolean('is_enabled')->default(true);
                $table->timestamp('granted_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('user_id', 'tupb_user_fk')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('branch_id', 'tupb_branch_fk')->references('id')->on('branches')->cascadeOnDelete();
                $table->unique(['tenant_id', 'user_id', 'product_key', 'branch_id'], 'tupb_tenant_user_product_branch_uq');
                $table->index(['tenant_id', 'product_key', 'branch_id'], 'tupb_product_branch_idx');
                $table->index(['user_id', 'product_key', 'is_enabled'], 'tupb_user_product_enabled_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user_product_branches');
        Schema::dropIfExists('tenant_product_branches');

        Schema::table('branches', function (Blueprint $table) {
            if (Schema::hasColumn('branches', 'manager_user_id')) {
                $table->dropForeign('branches_manager_fk');
                $table->dropColumn('manager_user_id');
            }

            foreach (['timezone', 'country', 'city', 'emirate'] as $column) {
                if (Schema::hasColumn('branches', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
