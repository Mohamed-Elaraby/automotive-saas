<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')
                ->nullable()
                ->constrained('subscriptions')
                ->nullOnDelete();

            $table->string('tenant_id')->nullable()->index();

            $table->string('gateway', 50)->default('stripe')->index();

            $table->string('gateway_invoice_id')->unique();
            $table->string('gateway_customer_id')->nullable()->index();
            $table->string('gateway_subscription_id')->nullable()->index();

            $table->string('invoice_number')->nullable()->index();
            $table->string('status', 50)->default('draft')->index();
            $table->string('billing_reason', 100)->nullable()->index();
            $table->string('currency', 10)->nullable()->index();

            $table->bigInteger('total_minor')->default(0);
            $table->decimal('total_decimal', 12, 2)->default(0);

            $table->bigInteger('amount_paid_minor')->default(0);
            $table->decimal('amount_paid_decimal', 12, 2)->default(0);

            $table->bigInteger('amount_due_minor')->default(0);
            $table->decimal('amount_due_decimal', 12, 2)->default(0);

            $table->text('hosted_invoice_url')->nullable();
            $table->text('invoice_pdf')->nullable();

            $table->timestamp('issued_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable()->index();

            $table->json('raw_payload')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['gateway_customer_id', 'issued_at']);
            $table->index(['gateway_subscription_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_invoices');
    }
};
