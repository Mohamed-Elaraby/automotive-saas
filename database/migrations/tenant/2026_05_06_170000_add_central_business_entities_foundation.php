<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                if (! Schema::hasColumn('customers', 'tenant_id')) {
                    $table->string('tenant_id')->nullable()->after('id');
                }

                if (! Schema::hasColumn('customers', 'display_name')) {
                    $table->string('display_name')->nullable()->after('name');
                }

                if (! Schema::hasColumn('customers', 'address')) {
                    $table->text('address')->nullable()->after('email');
                }

                if (! Schema::hasColumn('customers', 'status')) {
                    $table->string('status', 40)->default('active')->after('address');
                }

                if (! Schema::hasColumn('customers', 'metadata')) {
                    $table->json('metadata')->nullable()->after('status');
                }
            });
        }

        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                if (! Schema::hasColumn('suppliers', 'tenant_id')) {
                    $table->string('tenant_id')->nullable()->after('id');
                }

                if (! Schema::hasColumn('suppliers', 'display_name')) {
                    $table->string('display_name')->nullable()->after('name');
                }

                if (! Schema::hasColumn('suppliers', 'tax_number')) {
                    $table->string('tax_number')->nullable()->after('email');
                }

                if (! Schema::hasColumn('suppliers', 'status')) {
                    $table->string('status', 40)->default('active')->after('is_active');
                }

                if (! Schema::hasColumn('suppliers', 'metadata')) {
                    $table->json('metadata')->nullable()->after('status');
                }
            });
        }

        if (! Schema::hasTable('product_customer_profiles')) {
            Schema::create('product_customer_profiles', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->nullable();
                $table->string('product_key', 80);
                $table->foreignId('customer_id');
                $table->string('profile_type', 80)->default('default');
                $table->string('external_reference', 120)->nullable();
                $table->string('status', 40)->default('active');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('customer_id', 'pcp_customer_fk')->references('id')->on('customers')->cascadeOnDelete();
                $table->unique(['tenant_id', 'product_key', 'customer_id', 'profile_type'], 'pcp_tenant_product_customer_uq');
                $table->index(['product_key', 'status'], 'pcp_product_status_idx');
            });
        }

        if (! Schema::hasTable('product_supplier_profiles')) {
            Schema::create('product_supplier_profiles', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->nullable();
                $table->string('product_key', 80);
                $table->foreignId('supplier_id');
                $table->string('profile_type', 80)->default('default');
                $table->string('external_reference', 120)->nullable();
                $table->string('status', 40)->default('active');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('supplier_id', 'psp_supplier_fk')->references('id')->on('suppliers')->cascadeOnDelete();
                $table->unique(['tenant_id', 'product_key', 'supplier_id', 'profile_type'], 'psp_tenant_product_supplier_uq');
                $table->index(['product_key', 'status'], 'psp_product_status_idx');
            });
        }

        if (! Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->nullable();
                $table->foreignId('user_id')->nullable();
                $table->string('name');
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('job_title')->nullable();
                $table->string('employee_type', 60)->default('worker');
                $table->string('status', 40)->default('active');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('user_id', 'employees_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->index(['tenant_id', 'employee_type', 'status'], 'employees_type_status_idx');
                $table->index(['user_id', 'status'], 'employees_user_status_idx');
            });
        }

        if (! Schema::hasTable('product_employee_profiles')) {
            Schema::create('product_employee_profiles', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->nullable();
                $table->string('product_key', 80);
                $table->foreignId('employee_id');
                $table->string('profile_type', 80)->default('default');
                $table->string('external_reference', 120)->nullable();
                $table->string('status', 40)->default('active');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('employee_id', 'pep_employee_fk')->references('id')->on('employees')->cascadeOnDelete();
                $table->unique(['tenant_id', 'product_key', 'employee_id', 'profile_type'], 'pep_tenant_product_employee_uq');
                $table->index(['product_key', 'status'], 'pep_product_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_employee_profiles');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('product_supplier_profiles');
        Schema::dropIfExists('product_customer_profiles');

        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                foreach (['metadata', 'status', 'tax_number', 'display_name', 'tenant_id'] as $column) {
                    if (Schema::hasColumn('suppliers', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                foreach (['metadata', 'status', 'address', 'display_name', 'tenant_id'] as $column) {
                    if (Schema::hasColumn('customers', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
