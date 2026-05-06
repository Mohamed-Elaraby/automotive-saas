<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('maintenance_receipts')) {
            return;
        }

        Schema::create('maintenance_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->string('payment_method', 60)->default('cash');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('USD');
            $table->string('reference_number')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('branch_id', 'mnt_rcpt_branch_fk')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('invoice_id', 'mnt_rcpt_invoice_fk')->references('id')->on('maintenance_invoices')->nullOnDelete();
            $table->foreign('customer_id', 'mnt_rcpt_customer_fk')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('vehicle_id', 'mnt_rcpt_vehicle_fk')->references('id')->on('vehicles')->nullOnDelete();
            $table->foreign('work_order_id', 'mnt_rcpt_wo_fk')->references('id')->on('work_orders')->nullOnDelete();
            $table->foreign('created_by', 'mnt_rcpt_user_fk')->references('id')->on('users')->nullOnDelete();

            $table->index(['invoice_id', 'received_at'], 'mnt_rcpt_invoice_date_idx');
            $table->index(['customer_id', 'received_at'], 'mnt_rcpt_customer_date_idx');
            $table->index(['work_order_id'], 'mnt_rcpt_wo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_receipts');
    }
};
