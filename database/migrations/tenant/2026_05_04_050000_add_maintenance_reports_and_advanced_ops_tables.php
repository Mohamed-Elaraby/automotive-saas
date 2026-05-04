<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_sla_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('stage_code', 80);
            $table->string('name');
            $table->unsignedInteger('target_minutes')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'stage_code'], 'maint_sla_branch_stage_unique');
            $table->index(['stage_code', 'is_active'], 'maint_sla_stage_active_idx');
        });

        Schema::create('maintenance_delay_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->string('stage_code', 80);
            $table->unsignedInteger('target_minutes')->default(0);
            $table->unsignedInteger('elapsed_minutes')->default(0);
            $table->string('status', 60)->default('open');
            $table->text('message')->nullable();
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status'], 'maint_delay_branch_status_idx');
            $table->index(['work_order_id', 'stage_code'], 'maint_delay_wo_stage_idx');
        });

        Schema::create('maintenance_preventive_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('service_catalog_item_id')->nullable();
            $table->foreign('service_catalog_item_id', 'maint_prev_rules_service_item_fk')
                ->references('id')
                ->on('maintenance_service_catalog_items')
                ->nullOnDelete();
            $table->string('name');
            $table->string('vehicle_make')->nullable();
            $table->string('vehicle_model')->nullable();
            $table->unsignedInteger('mileage_interval')->nullable();
            $table->unsignedInteger('months_interval')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'is_active'], 'maint_prev_branch_active_idx');
            $table->index(['vehicle_make', 'vehicle_model'], 'maint_prev_vehicle_idx');
        });

        Schema::create('maintenance_preventive_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('maintenance_preventive_rules')->nullOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('service_catalog_item_id')->nullable();
            $table->foreign('service_catalog_item_id', 'maint_prev_rem_service_item_fk')
                ->references('id')
                ->on('maintenance_service_catalog_items')
                ->nullOnDelete();
            $table->string('status', 60)->default('upcoming');
            $table->date('due_date')->nullable();
            $table->unsignedInteger('due_mileage')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'status'], 'maint_prev_rem_vehicle_status_idx');
            $table->index(['due_date', 'status'], 'maint_prev_rem_due_status_idx');
        });

        Schema::create('maintenance_vehicle_health_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->unsignedTinyInteger('overall_score')->default(100);
            $table->unsignedTinyInteger('engine_score')->default(100);
            $table->unsignedTinyInteger('brakes_score')->default(100);
            $table->unsignedTinyInteger('suspension_score')->default(100);
            $table->unsignedTinyInteger('ac_score')->default(100);
            $table->unsignedTinyInteger('electrical_score')->default(100);
            $table->unsignedTinyInteger('tires_score')->default(100);
            $table->json('signals')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'calculated_at'], 'maint_health_vehicle_calc_idx');
        });

        Schema::create('maintenance_service_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('service_catalog_item_id')->nullable();
            $table->foreign('service_catalog_item_id', 'maint_rec_service_item_fk')
                ->references('id')
                ->on('maintenance_service_catalog_items')
                ->nullOnDelete();
            $table->string('source', 80)->default('system');
            $table->string('priority', 60)->default('normal');
            $table->string('status', 60)->default('open');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->unsignedInteger('due_mileage')->nullable();
            $table->json('signals')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'status'], 'maint_rec_vehicle_status_idx');
            $table->index(['branch_id', 'priority'], 'maint_rec_branch_priority_idx');
        });

        Schema::create('maintenance_technician_skill_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->constrained('users')->cascadeOnDelete();
            $table->string('skill_code', 80);
            $table->unsignedTinyInteger('level')->default(1);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['technician_id', 'skill_code'], 'maint_skill_tech_skill_unique');
            $table->index(['skill_code', 'level'], 'maint_skill_code_level_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_technician_skill_profiles');
        Schema::dropIfExists('maintenance_service_recommendations');
        Schema::dropIfExists('maintenance_vehicle_health_scores');
        Schema::dropIfExists('maintenance_preventive_reminders');
        Schema::dropIfExists('maintenance_preventive_rules');
        Schema::dropIfExists('maintenance_delay_alerts');
        Schema::dropIfExists('maintenance_sla_policies');
    }
};
