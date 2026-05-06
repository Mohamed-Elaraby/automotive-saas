<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('maintenance_customer_feedback')) {
            return;
        }

        Schema::create('maintenance_customer_feedback', function (Blueprint $table) {
            $table->id();
            $table->string('feedback_number')->unique();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->string('feedback_type', 60)->default('delivery');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('status', 40)->default('submitted');
            $table->text('customer_visible_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->string('ip_address', 80)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->foreign('branch_id', 'mnt_fb_branch_fk')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('work_order_id', 'mnt_fb_wo_fk')->references('id')->on('work_orders')->nullOnDelete();
            $table->foreign('customer_id', 'mnt_fb_customer_fk')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('vehicle_id', 'mnt_fb_vehicle_fk')->references('id')->on('vehicles')->nullOnDelete();
            $table->index(['work_order_id', 'feedback_type'], 'mnt_fb_wo_type_idx');
            $table->index(['customer_id', 'submitted_at'], 'mnt_fb_customer_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_customer_feedback');
    }
};
