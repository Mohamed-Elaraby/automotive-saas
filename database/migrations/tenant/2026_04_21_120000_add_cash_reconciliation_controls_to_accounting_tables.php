<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_payments', function (Blueprint $table) {
            $table->date('bank_reconciliation_date')->nullable()->after('reconciled_at');
            $table->string('bank_reference', 120)->nullable()->after('bank_reconciliation_date');
        });

        Schema::table('accounting_deposit_batches', function (Blueprint $table) {
            $table->string('reconciliation_status', 40)->default('pending')->after('status');
            $table->date('bank_reconciliation_date')->nullable()->after('posted_at');
            $table->string('bank_reference', 120)->nullable()->after('bank_reconciliation_date');
            $table->foreignId('reconciled_by')->nullable()->after('corrected_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable()->after('bank_reference');

            $table->index(['reconciliation_status', 'deposit_date'], 'deposit_batches_reconciliation_lookup');
        });

        Schema::table('accounting_vendor_bill_payments', function (Blueprint $table) {
            $table->string('reconciliation_status', 40)->default('pending')->after('status');
            $table->date('bank_reconciliation_date')->nullable()->after('posted_at');
            $table->string('bank_reference', 120)->nullable()->after('bank_reconciliation_date');
            $table->foreignId('reconciled_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable()->after('bank_reference');

            $table->index(['reconciliation_status', 'payment_date'], 'vendor_payments_reconciliation_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_vendor_bill_payments', function (Blueprint $table) {
            $table->dropIndex('vendor_payments_reconciliation_lookup');
            $table->dropConstrainedForeignId('reconciled_by');
            $table->dropColumn([
                'reconciliation_status',
                'bank_reconciliation_date',
                'bank_reference',
                'reconciled_at',
            ]);
        });

        Schema::table('accounting_deposit_batches', function (Blueprint $table) {
            $table->dropIndex('deposit_batches_reconciliation_lookup');
            $table->dropConstrainedForeignId('reconciled_by');
            $table->dropColumn([
                'reconciliation_status',
                'bank_reconciliation_date',
                'bank_reference',
                'reconciled_at',
            ]);
        });

        Schema::table('accounting_payments', function (Blueprint $table) {
            $table->dropColumn(['bank_reconciliation_date', 'bank_reference']);
        });
    }
};
