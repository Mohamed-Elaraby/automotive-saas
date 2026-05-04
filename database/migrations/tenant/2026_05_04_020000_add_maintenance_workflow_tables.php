<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_inspection_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_number')->nullable()->unique();
            $table->string('name');
            $table->string('inspection_type', 60)->default('initial');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['inspection_type', 'is_active'], 'mi_templates_type_active_idx');
        });

        Schema::create('maintenance_inspection_template_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('template_id');

            $table->string('section')->nullable();
            $table->string('label');
            $table->string('default_result', 60)->default('not_checked');
            $table->boolean('requires_photo')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('template_id', 'mit_items_template_fk')
                ->references('id')
                ->on('maintenance_inspection_templates')
                ->cascadeOnDelete();

            $table->index(['template_id', 'sort_order'], 'mit_items_template_sort_idx');
        });

        Schema::create('maintenance_inspections', function (Blueprint $table) {
            $table->id();
            $table->string('inspection_number')->unique();

            $table->foreignId('branch_id');
            $table->foreignId('work_order_id')->nullable();
            $table->foreignId('check_in_id')->nullable();
            $table->foreignId('vehicle_id');
            $table->foreignId('customer_id')->nullable();
            $table->foreignId('template_id')->nullable();

            $table->string('inspection_type', 60)->default('initial');
            $table->string('status', 60)->default('draft');

            $table->foreignId('assigned_to')->nullable();
            $table->foreignId('started_by')->nullable();
            $table->foreignId('completed_by')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('summary')->nullable();
            $table->text('customer_visible_notes')->nullable();
            $table->text('internal_notes')->nullable();

            $table->foreignId('created_by')->nullable();

            $table->timestamps();

            $table->foreign('branch_id', 'mi_branch_fk')
                ->references('id')
                ->on('branches')
                ->cascadeOnDelete();

            $table->foreign('work_order_id', 'mi_work_order_fk')
                ->references('id')
                ->on('work_orders')
                ->nullOnDelete();

            $table->foreign('check_in_id', 'mi_check_in_fk')
                ->references('id')
                ->on('vehicle_check_ins')
                ->nullOnDelete();

            $table->foreign('vehicle_id', 'mi_vehicle_fk')
                ->references('id')
                ->on('vehicles')
                ->cascadeOnDelete();

            $table->foreign('customer_id', 'mi_customer_fk')
                ->references('id')
                ->on('customers')
                ->nullOnDelete();

            $table->foreign('template_id', 'mi_template_fk')
                ->references('id')
                ->on('maintenance_inspection_templates')
                ->nullOnDelete();

            $table->foreign('assigned_to', 'mi_assigned_to_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('started_by', 'mi_started_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('completed_by', 'mi_completed_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('created_by', 'mi_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['branch_id', 'status'], 'mi_branch_status_idx');
            $table->index(['work_order_id', 'inspection_type'], 'mi_work_order_type_idx');
        });

        Schema::create('maintenance_inspection_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inspection_id');
            $table->foreignId('template_item_id')->nullable();

            $table->string('section')->nullable();
            $table->string('label');
            $table->string('result', 60)->default('not_checked');
            $table->text('note')->nullable();
            $table->text('recommendation')->nullable();
            $table->decimal('estimated_cost', 12, 2)->nullable();
            $table->string('customer_approval_status', 60)->default('pending');

            $table->foreignId('photo_id')->nullable();

            $table->timestamps();

            $table->foreign('inspection_id', 'mi_items_inspection_fk')
                ->references('id')
                ->on('maintenance_inspections')
                ->cascadeOnDelete();

            $table->foreign('template_item_id', 'mi_items_template_item_fk')
                ->references('id')
                ->on('maintenance_inspection_template_items')
                ->nullOnDelete();

            $table->foreign('photo_id', 'mi_items_photo_fk')
                ->references('id')
                ->on('maintenance_attachments')
                ->nullOnDelete();

            $table->index(['inspection_id', 'result'], 'mi_items_inspection_result_idx');
        });

        Schema::create('maintenance_diagnosis_records', function (Blueprint $table) {
            $table->id();
            $table->string('diagnosis_number')->unique();

            $table->foreignId('branch_id');
            $table->foreignId('work_order_id')->nullable();
            $table->foreignId('inspection_id')->nullable();
            $table->foreignId('vehicle_id');
            $table->foreignId('customer_id')->nullable();

            $table->text('complaint')->nullable();
            $table->text('symptoms')->nullable();
            $table->text('scanner_report')->nullable();
            $table->json('fault_codes')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('recommended_repair')->nullable();
            $table->decimal('estimated_labor_hours', 8, 2)->nullable();
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->string('priority', 40)->default('normal');
            $table->text('technician_notes')->nullable();

            $table->foreignId('diagnosed_by')->nullable();
            $table->foreignId('created_by')->nullable();

            $table->timestamps();

            $table->foreign('branch_id', 'mdr_branch_fk')
                ->references('id')
                ->on('branches')
                ->cascadeOnDelete();

            $table->foreign('work_order_id', 'mdr_work_order_fk')
                ->references('id')
                ->on('work_orders')
                ->nullOnDelete();

            $table->foreign('inspection_id', 'mdr_inspection_fk')
                ->references('id')
                ->on('maintenance_inspections')
                ->nullOnDelete();

            $table->foreign('vehicle_id', 'mdr_vehicle_fk')
                ->references('id')
                ->on('vehicles')
                ->cascadeOnDelete();

            $table->foreign('customer_id', 'mdr_customer_fk')
                ->references('id')
                ->on('customers')
                ->nullOnDelete();

            $table->foreign('diagnosed_by', 'mdr_diagnosed_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('created_by', 'mdr_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['work_order_id', 'priority'], 'mdr_work_order_priority_idx');
        });

        Schema::create('maintenance_work_order_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_number')->unique();

            $table->foreignId('work_order_id');
            $table->foreignId('service_catalog_item_id')->nullable();
            $table->foreignId('assigned_technician_id')->nullable();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 60)->default('pending');
            $table->string('priority', 40)->default('normal');
            $table->unsignedInteger('estimated_minutes')->default(0);
            $table->unsignedInteger('actual_minutes')->default(0);
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('qc_status', 60)->default('pending');
            $table->text('customer_visible_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('blocker_note')->nullable();

            $table->foreignId('created_by')->nullable();

            $table->timestamps();

            $table->foreign('work_order_id', 'mwo_jobs_work_order_fk')
                ->references('id')
                ->on('work_orders')
                ->cascadeOnDelete();

            $table->foreign('service_catalog_item_id', 'mwo_jobs_service_item_fk')
                ->references('id')
                ->on('maintenance_service_catalog_items')
                ->nullOnDelete();

            $table->foreign('assigned_technician_id', 'mwo_jobs_technician_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('created_by', 'mwo_jobs_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['work_order_id', 'status'], 'mwo_jobs_work_order_status_idx');
            $table->index(['assigned_technician_id', 'status'], 'mwo_jobs_technician_status_idx');
        });

        Schema::create('maintenance_job_time_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('job_id');
            $table->foreignId('technician_id')->nullable();

            $table->string('action', 40);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('job_id', 'mjtl_job_fk')
                ->references('id')
                ->on('maintenance_work_order_jobs')
                ->cascadeOnDelete();

            $table->foreign('technician_id', 'mjtl_technician_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['job_id', 'created_at'], 'mjtl_job_created_idx');
        });

        Schema::create('maintenance_qc_records', function (Blueprint $table) {
            $table->id();
            $table->string('qc_number')->unique();

            $table->foreignId('branch_id');
            $table->foreignId('work_order_id');
            $table->foreignId('vehicle_id')->nullable();

            $table->string('status', 60)->default('draft');
            $table->string('result', 60)->nullable();

            $table->foreignId('qc_inspector_id')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('final_notes')->nullable();
            $table->text('rework_reason')->nullable();

            $table->foreignId('created_by')->nullable();

            $table->timestamps();

            $table->foreign('branch_id', 'mqc_branch_fk')
                ->references('id')
                ->on('branches')
                ->cascadeOnDelete();

            $table->foreign('work_order_id', 'mqc_work_order_fk')
                ->references('id')
                ->on('work_orders')
                ->cascadeOnDelete();

            $table->foreign('vehicle_id', 'mqc_vehicle_fk')
                ->references('id')
                ->on('vehicles')
                ->nullOnDelete();

            $table->foreign('qc_inspector_id', 'mqc_inspector_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('created_by', 'mqc_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['branch_id', 'status'], 'mqc_branch_status_idx');
            $table->index(['work_order_id', 'result'], 'mqc_work_order_result_idx');
        });

        Schema::create('maintenance_qc_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('qc_record_id');

            $table->string('label');
            $table->boolean('passed')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('qc_record_id', 'mqc_items_record_fk')
                ->references('id')
                ->on('maintenance_qc_records')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_qc_items');
        Schema::dropIfExists('maintenance_qc_records');
        Schema::dropIfExists('maintenance_job_time_logs');
        Schema::dropIfExists('maintenance_work_order_jobs');
        Schema::dropIfExists('maintenance_diagnosis_records');
        Schema::dropIfExists('maintenance_inspection_items');
        Schema::dropIfExists('maintenance_inspections');
        Schema::dropIfExists('maintenance_inspection_template_items');
        Schema::dropIfExists('maintenance_inspection_templates');
    }
};
