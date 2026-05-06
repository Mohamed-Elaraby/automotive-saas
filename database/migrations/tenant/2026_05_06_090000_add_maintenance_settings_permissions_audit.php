<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'maintenance_role')) {
                $column = $table->string('maintenance_role', 80)->nullable();
                Schema::hasColumn('users', 'accounting_permissions')
                    ? $column->after('accounting_permissions')
                    : $column->after('password');
            }

            if (! Schema::hasColumn('users', 'maintenance_permissions')) {
                $table->json('maintenance_permissions')->nullable()->after('maintenance_role');
            }
        });

        if (! Schema::hasTable('maintenance_settings')) {
            Schema::create('maintenance_settings', function (Blueprint $table) {
                $table->id();
                $table->string('setting_key', 120);
                $table->string('group_code', 80)->default('general');
                $table->json('setting_value')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->unique('setting_key', 'mnt_settings_key_uq');
                $table->index('group_code', 'mnt_settings_group_idx');
                $table->foreign('updated_by', 'mnt_settings_user_fk')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('maintenance_audit_entries')) {
            Schema::create('maintenance_audit_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action', 120);
                $table->string('module_code', 80);
                $table->string('auditable_type', 120)->nullable();
                $table->unsignedBigInteger('auditable_id')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 80)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->foreign('branch_id', 'mnt_audit_branch_fk')->references('id')->on('branches')->nullOnDelete();
                $table->foreign('user_id', 'mnt_audit_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->index(['module_code', 'action'], 'mnt_audit_module_action_idx');
                $table->index(['auditable_type', 'auditable_id'], 'mnt_audit_entity_idx');
                $table->index('created_at', 'mnt_audit_created_idx');
            });
        }

        if (! Schema::hasTable('maintenance_approval_requests')) {
            Schema::create('maintenance_approval_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->unsignedBigInteger('decided_by')->nullable();
                $table->string('approval_type', 80);
                $table->string('status', 40)->default('pending');
                $table->string('approvable_type', 120)->nullable();
                $table->unsignedBigInteger('approvable_id')->nullable();
                $table->text('reason')->nullable();
                $table->json('payload')->nullable();
                $table->text('decision_notes')->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->timestamps();

                $table->foreign('branch_id', 'mnt_apr_branch_fk')->references('id')->on('branches')->nullOnDelete();
                $table->foreign('requested_by', 'mnt_apr_req_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('decided_by', 'mnt_apr_dec_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->index(['status', 'approval_type'], 'mnt_apr_status_type_idx');
                $table->index(['approvable_type', 'approvable_id'], 'mnt_apr_entity_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_approval_requests');
        Schema::dropIfExists('maintenance_audit_entries');
        Schema::dropIfExists('maintenance_settings');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'maintenance_permissions')) {
                $table->dropColumn('maintenance_permissions');
            }

            if (Schema::hasColumn('users', 'maintenance_role')) {
                $table->dropColumn('maintenance_role');
            }
        });
    }
};
