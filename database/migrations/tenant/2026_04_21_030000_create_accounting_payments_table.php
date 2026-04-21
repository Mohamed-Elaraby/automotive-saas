<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_event_id')->nullable()->constrained('accounting_events')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('payment_number', 80)->unique();
            $table->date('payment_date');
            $table->string('payer_name')->nullable();
            $table->string('method', 40)->default('cash');
            $table->string('reference', 120)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('amount', 12, 2);
            $table->string('cash_account')->default('1000 Cash On Hand');
            $table->string('receivable_account')->default('1100 Accounts Receivable');
            $table->string('status', 40)->default('posted');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['accounting_event_id', 'status'], 'accounting_payments_event_status_index');
            $table->index(['payment_date', 'status'], 'accounting_payments_date_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_payments');
    }
};
