<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_estimates', function (Blueprint $table) {
            if (! Schema::hasColumn('maintenance_estimates', 'approval_token')) {
                $table->string('approval_token', 120)->nullable()->after('approval_method');
                $table->unique('approval_token', 'maint_est_approval_token_unique');
            }
        });

        Schema::table('work_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('work_orders', 'customer_tracking_token')) {
                $table->string('customer_tracking_token', 120)->nullable()->after('payment_status');
                $table->unique('customer_tracking_token', 'work_orders_tracking_token_unique');
            }
        });

        Schema::create('maintenance_approval_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('estimate_id')->nullable()->constrained('maintenance_estimates')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->string('approval_type', 80)->default('estimate');
            $table->string('status', 60)->default('pending');
            $table->string('method', 80)->nullable();
            $table->decimal('approved_amount', 12, 2)->default(0);
            $table->json('approved_items')->nullable();
            $table->json('rejected_items')->nullable();
            $table->text('reason')->nullable();
            $table->text('terms_snapshot')->nullable();
            $table->boolean('terms_accepted')->default(false);
            $table->string('ip_address', 80)->nullable();
            $table->string('device_summary')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['estimate_id', 'status'], 'maint_appr_est_status_idx');
            $table->index(['work_order_id', 'approval_type'], 'maint_appr_wo_type_idx');
        });

        Schema::create('maintenance_lost_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('estimate_id')->nullable()->constrained('maintenance_estimates')->nullOnDelete();
            $table->foreignId('estimate_line_id')->nullable()->constrained('maintenance_estimate_lines')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->string('item_description');
            $table->string('reason', 80)->default('other');
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('follow_up_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('advisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'reason'], 'maint_lost_branch_reason_idx');
            $table->index(['follow_up_date'], 'maint_lost_followup_idx');
        });

        Schema::create('maintenance_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_number')->unique();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->string('status', 60)->default('draft');
            $table->json('checklist')->nullable();
            $table->string('payment_status', 60)->default('unpaid');
            $table->text('customer_signature')->nullable();
            $table->text('advisor_signature')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('customer_visible_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status'], 'maint_del_branch_status_idx');
            $table->index(['work_order_id', 'status'], 'maint_del_wo_status_idx');
        });

        Schema::create('maintenance_warranties', function (Blueprint $table) {
            $table->id();
            $table->string('warranty_number')->unique();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('service_catalog_item_id')->nullable();
            $table->foreign('service_catalog_item_id', 'maint_warranties_service_item_fk')
                ->references('id')
                ->on('maintenance_service_catalog_items')
                ->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->string('warranty_type', 80)->default('labor');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->unsignedInteger('mileage_limit')->nullable();
            $table->string('status', 60)->default('active');
            $table->text('terms')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vehicle_id', 'status'], 'maint_war_vehicle_status_idx');
            $table->index(['work_order_id', 'warranty_type'], 'maint_war_wo_type_idx');
        });

        Schema::create('maintenance_warranty_claims', function (Blueprint $table) {
            $table->id();
            $table->string('claim_number')->unique();
            $table->foreignId('warranty_id')->nullable()->constrained('maintenance_warranties')->nullOnDelete();
            $table->foreignId('original_work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('rework_work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->string('status', 60)->default('pending');
            $table->string('comeback_reason', 120)->nullable();
            $table->text('customer_complaint')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('resolution')->nullable();
            $table->decimal('cost_impact', 12, 2)->default(0);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'status'], 'maint_wclaim_vehicle_status_idx');
        });

        Schema::create('maintenance_complaints', function (Blueprint $table) {
            $table->id();
            $table->string('complaint_number')->unique();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->string('source', 80)->default('in_branch');
            $table->string('status', 60)->default('open');
            $table->string('severity', 60)->default('medium');
            $table->text('customer_visible_note')->nullable();
            $table->text('internal_note')->nullable();
            $table->text('resolution')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'status'], 'maint_cmp_branch_status_idx');
            $table->index(['customer_id', 'status'], 'maint_cmp_customer_status_idx');
        });

        Schema::create('maintenance_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 80)->default('branch');
            $table->string('event_type', 120)->index();
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('severity', 30)->default('info');
            $table->string('notifiable_type')->nullable();
            $table->unsignedBigInteger('notifiable_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'created_at'], 'maint_notif_branch_created_idx');
            $table->index(['user_id', 'read_at'], 'maint_notif_user_read_idx');
            $table->index(['notifiable_type', 'notifiable_id'], 'maint_notif_notifiable_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_notifications');
        Schema::dropIfExists('maintenance_complaints');
        Schema::dropIfExists('maintenance_warranty_claims');
        Schema::dropIfExists('maintenance_warranties');
        Schema::dropIfExists('maintenance_deliveries');
        Schema::dropIfExists('maintenance_lost_sales');
        Schema::dropIfExists('maintenance_approval_records');

        Schema::table('work_orders', function (Blueprint $table) {
            if (Schema::hasColumn('work_orders', 'customer_tracking_token')) {
                $table->dropColumn('customer_tracking_token');
            }
        });

        Schema::table('maintenance_estimates', function (Blueprint $table) {
            if (Schema::hasColumn('maintenance_estimates', 'approval_token')) {
                $table->dropColumn('approval_token');
            }
        });
    }
};
