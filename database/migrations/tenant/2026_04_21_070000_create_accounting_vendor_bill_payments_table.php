<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_vendor_bill_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_vendor_bill_id')->constrained('accounting_vendor_bills')->cascadeOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('payment_number', 80)->unique();
            $table->date('payment_date');
            $table->string('method', 40)->default('bank_transfer');
            $table->string('reference', 120)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('amount', 12, 2);
            $table->string('cash_account')->default('1010 Bank Account');
            $table->string('payable_account')->default('2000 Accounts Payable');
            $table->string('status', 40)->default('posted');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['accounting_vendor_bill_id', 'status'], 'vendor_bill_payments_bill_status');
            $table->index(['payment_date', 'status'], 'vendor_bill_payments_date_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_vendor_bill_payments');
    }
};
