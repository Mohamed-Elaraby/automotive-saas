<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('maintenance_api_tokens')) {
            Schema::create('maintenance_api_tokens', function (Blueprint $table) {
                $table->id();
                $table->string('token_name');
                $table->string('token_hash', 64);
                $table->string('status', 40)->default('active');
                $table->json('scopes')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->string('last_used_ip')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->unique('token_hash', 'mnt_api_token_hash_uq');
                $table->index(['status', 'last_used_at'], 'mnt_api_status_used_idx');
                $table->foreign('created_by', 'mnt_api_created_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('maintenance_api_request_logs')) {
            Schema::create('maintenance_api_request_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('token_id')->nullable();
                $table->string('route_name')->nullable();
                $table->string('method', 20);
                $table->string('path');
                $table->string('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->string('idempotency_key')->nullable();
                $table->unsignedSmallInteger('status_code')->nullable();
                $table->json('request_summary')->nullable();
                $table->json('response_summary')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index('token_id', 'mnt_api_log_token_idx');
                $table->index('idempotency_key', 'mnt_api_log_idem_idx');
                $table->foreign('token_id', 'mnt_api_log_token_fk')
                    ->references('id')->on('maintenance_api_tokens')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('maintenance_payment_requests')) {
            Schema::create('maintenance_payment_requests', function (Blueprint $table) {
                $table->id();
                $table->string('request_number');
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->unsignedBigInteger('invoice_id');
                $table->unsignedBigInteger('work_order_id')->nullable();
                $table->unsignedBigInteger('customer_id');
                $table->unsignedBigInteger('vehicle_id')->nullable();
                $table->string('status', 40)->default('pending');
                $table->decimal('amount', 12, 2);
                $table->string('currency', 10)->default('USD');
                $table->string('provider')->nullable();
                $table->string('payment_token', 80);
                $table->text('payment_url')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->json('payload')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique('request_number', 'mnt_pay_req_number_uq');
                $table->unique('payment_token', 'mnt_pay_req_token_uq');
                $table->index(['invoice_id', 'status'], 'mnt_pay_req_inv_status_idx');
                $table->index(['customer_id', 'status'], 'mnt_pay_req_cust_status_idx');
                $table->foreign('branch_id', 'mnt_pay_req_branch_fk')
                    ->references('id')->on('branches')->nullOnDelete();
                $table->foreign('invoice_id', 'mnt_pay_req_invoice_fk')
                    ->references('id')->on('maintenance_invoices')->cascadeOnDelete();
                $table->foreign('work_order_id', 'mnt_pay_req_wo_fk')
                    ->references('id')->on('work_orders')->nullOnDelete();
                $table->foreign('customer_id', 'mnt_pay_req_customer_fk')
                    ->references('id')->on('customers')->cascadeOnDelete();
                $table->foreign('vehicle_id', 'mnt_pay_req_vehicle_fk')
                    ->references('id')->on('vehicles')->nullOnDelete();
                $table->foreign('created_by', 'mnt_pay_req_user_fk')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_payment_requests');
        Schema::dropIfExists('maintenance_api_request_logs');
        Schema::dropIfExists('maintenance_api_tokens');
    }
};
