<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_vendor_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('bill_number', 80)->unique();
            $table->date('bill_date');
            $table->date('due_date')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('reference', 120)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('amount', 12, 2);
            $table->string('expense_account')->default('5200 Operating Expense');
            $table->string('payable_account')->default('2000 Accounts Payable');
            $table->string('status', 40)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'bill_date'], 'accounting_vendor_bills_status_date');
            $table->index(['supplier_id', 'status'], 'accounting_vendor_bills_supplier_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_vendor_bills');
    }
};
