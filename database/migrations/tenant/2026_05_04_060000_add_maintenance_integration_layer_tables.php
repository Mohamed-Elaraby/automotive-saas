<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createIfMissing('maintenance_parts_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->unsignedBigInteger('job_id')->nullable();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('stock_movement_id')->nullable();
            $table->unsignedBigInteger('handoff_id')->nullable();
            $table->string('status', 60)->default('requested');
            $table->string('source', 60)->default('manual');
            $table->string('part_name');
            $table->string('part_number')->nullable();
            $table->string('supplier_name')->nullable();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);
            $table->date('needed_by')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique('request_number', 'maint_part_req_number_unique');
            $table->index(['branch_id', 'status'], 'maint_part_req_branch_status_idx');
            $table->index(['work_order_id', 'status'], 'maint_part_req_wo_status_idx');
            $table->index(['job_id', 'status'], 'maint_part_req_job_status_idx');

            $table->foreign('branch_id', 'maint_part_req_branch_fk')
                ->references('id')->on('branches')->nullOnDelete();
            $table->foreign('work_order_id', 'maint_part_req_wo_fk')
                ->references('id')->on('work_orders')->nullOnDelete();
            $table->foreign('job_id', 'maint_part_req_job_fk')
                ->references('id')->on('maintenance_work_order_jobs')->nullOnDelete();
            $table->foreign('vehicle_id', 'maint_part_req_vehicle_fk')
                ->references('id')->on('vehicles')->nullOnDelete();
            $table->foreign('customer_id', 'maint_part_req_customer_fk')
                ->references('id')->on('customers')->nullOnDelete();
            $table->foreign('product_id', 'maint_part_req_product_fk')
                ->references('id')->on('products')->nullOnDelete();
            $table->foreign('stock_movement_id', 'maint_part_req_stock_move_fk')
                ->references('id')->on('stock_movements')->nullOnDelete();
            $table->foreign('handoff_id', 'maint_part_req_handoff_fk')
                ->references('id')->on('workspace_integration_handoffs')->nullOnDelete();
            $table->foreign('requested_by', 'maint_part_req_requested_by_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by', 'maint_part_req_approved_by_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_parts_requests');
    }

    protected function createIfMissing(string $table, callable $callback): void
    {
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, $callback);
    }
};
