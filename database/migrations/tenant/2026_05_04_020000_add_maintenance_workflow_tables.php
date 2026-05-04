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

            $table->index(['inspection_type', 'is_active'], 'maint_insp_tpl_type_active_idx');
        });

        Schema::create('maintenance_inspection_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('maintenance_inspection_templates')->cascadeOnDelete();
            $table->string('section')->nullable();
            $table->string('label');
            $table->string('default_result', 60)->default('not_checked');
            $table->boolean('requires_photo')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['template_id', 'sort_order'], 'maint_insp_tpl_items_tpl_sort_idx');
        });

        Schema::create('maintenance_inspections', function (Blueprint $table) {
            $table->id();
            $table->string('inspection_number')->unique();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('check_in_id')->nullable()->constrained('vehicle_check_ins')->nullOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('maintenance_inspection_templates')->nullOnDelete();
            $table->string('inspection_type', 60)->default('initial');
            $table->string('status', 60)->default('draft');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('summary')->nullable();
            $table->text('customer_visible_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'status'], 'maint_insp_branch_status_idx');
            $table->index(['work_order_id', 'inspection_type'], 'maint_insp_wo_type_idx');
        });

        Schema::create('maintenance_inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('maintenance_inspections')->cascadeOnDelete();
            $table->foreignId('template_item_id')->nullable()->constrained('maintenance_inspection_template_items')->nullOnDelete();
            $table->string('section')->nullable();
            $table->string('label');
            $table->string('result', 60)->default('not_checked');
            $table->text('note')->nullable();
            $table->text('recommendation')->nullable();
            $table->decimal('estimated_cost', 12, 2)->nullable();
            $table->string('customer_approval_status', 60)->default('pending');
            $table->foreignId('photo_id')->nullable()->constrained('maintenance_attachments')->nullOnDelete();
            $table->timestamps();

            $table->index(['inspection_id', 'result'], 'maint_insp_items_insp_result_idx');
        });

        Schema::create('maintenance_diagnosis_records', function (Blueprint $table) {
            $table->id();
            $table->string('diagnosis_number')->unique();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('inspection_id')->nullable()->constrained('maintenance_inspections')->nullOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
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
            $table->foreignId('diagnosed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['work_order_id', 'priority'], 'maint_diag_wo_priority_idx');
        });

        Schema::create('maintenance_work_order_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_number')->unique();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('service_catalog_item_id')->nullable();
            $table->foreign('service_catalog_item_id', 'maint_jobs_service_item_fk')
                ->references('id')
                ->on('maintenance_service_catalog_items')
                ->nullOnDelete();
            $table->foreignId('assigned_technician_id')->nullable()->constrained('users')->nullOnDelete();
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
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['work_order_id', 'status'], 'maint_jobs_wo_status_idx');
            $table->index(['assigned_technician_id', 'status'], 'maint_jobs_tech_status_idx');
        });

        Schema::create('maintenance_job_time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('maintenance_work_order_jobs')->cascadeOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['job_id', 'created_at'], 'maint_job_logs_job_created_idx');
        });

        Schema::create('maintenance_qc_records', function (Blueprint $table) {
            $table->id();
            $table->string('qc_number')->unique();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->string('status', 60)->default('draft');
            $table->string('result', 60)->nullable();
            $table->foreignId('qc_inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('final_notes')->nullable();
            $table->text('rework_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'status'], 'maint_qc_branch_status_idx');
            $table->index(['work_order_id', 'result'], 'maint_qc_wo_result_idx');
        });

        Schema::create('maintenance_qc_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qc_record_id')->constrained('maintenance_qc_records')->cascadeOnDelete();
            $table->string('label');
            $table->boolean('passed')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();
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
