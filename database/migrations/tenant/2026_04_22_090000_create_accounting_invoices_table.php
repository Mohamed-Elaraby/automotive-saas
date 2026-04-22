<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_event_id')->nullable()->constrained('accounting_events')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('invoice_number', 80)->unique();
            $table->string('customer_name');
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('receivable_account', 120)->default('1100 Accounts Receivable');
            $table->string('revenue_account', 120)->default('4100 Service Labor Revenue');
            $table->string('tax_account', 120)->nullable();
            $table->string('status', 40)->default('draft');
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'issue_date'], 'accounting_invoices_status_issue_date');
            $table->index(['customer_name', 'status'], 'accounting_invoices_customer_status');
        });

        Schema::create('accounting_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_invoice_id')->constrained('accounting_invoices')->cascadeOnDelete();
            $table->string('description');
            $table->string('account_code', 120);
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_invoice_lines');
        Schema::dropIfExists('accounting_invoices');
    }
};
