<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_deposit_batches', function (Blueprint $table) {
            $table->id();
            $table->string('deposit_number', 80)->unique();
            $table->date('deposit_date');
            $table->string('deposit_account')->default('1010 Bank Account');
            $table->string('currency', 3)->default('USD');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->unsignedInteger('payments_count')->default(0);
            $table->string('status', 40)->default('posted');
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['deposit_date', 'status'], 'accounting_deposit_batches_date_status');
        });

        Schema::table('accounting_payments', function (Blueprint $table) {
            $table->foreignId('deposit_batch_id')
                ->nullable()
                ->after('journal_entry_id')
                ->constrained('accounting_deposit_batches')
                ->nullOnDelete();
            $table->string('reconciliation_status', 40)->default('pending')->after('status');
            $table->foreignId('reconciled_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable()->after('posted_at');

            $table->index(['reconciliation_status', 'deposit_batch_id'], 'accounting_payments_reconciliation_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_payments', function (Blueprint $table) {
            $table->dropIndex('accounting_payments_reconciliation_lookup');
            $table->dropConstrainedForeignId('deposit_batch_id');
            $table->dropConstrainedForeignId('reconciled_by');
            $table->dropColumn(['reconciliation_status', 'reconciled_at']);
        });

        Schema::dropIfExists('accounting_deposit_batches');
    }
};
