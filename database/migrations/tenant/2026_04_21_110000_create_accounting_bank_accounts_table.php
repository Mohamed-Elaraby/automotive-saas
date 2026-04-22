<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounting_bank_accounts')) {
            Schema::create('accounting_bank_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('type', 40)->default('bank');
                $table->string('account_code', 120);
                $table->string('currency', 3)->default('USD');
                $table->string('reference', 120)->nullable();
                $table->boolean('is_default_receipt')->default(false);
                $table->boolean('is_default_payment')->default(false);
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique('account_code');
                $table->index(['type', 'is_active'], 'accounting_bank_accounts_type_active');
            });
        }

        $this->addBankAccountForeignIdIfMissing('accounting_payments', 'deposit_batch_id');
        $this->addBankAccountForeignIdIfMissing('accounting_deposit_batches', 'id');
        $this->addBankAccountForeignIdIfMissing('accounting_vendor_bill_payments', 'journal_entry_id');
    }

    public function down(): void
    {
        Schema::table('accounting_vendor_bill_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accounting_bank_account_id');
        });

        Schema::table('accounting_deposit_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accounting_bank_account_id');
        });

        Schema::table('accounting_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accounting_bank_account_id');
        });

        Schema::dropIfExists('accounting_bank_accounts');
    }

    protected function addBankAccountForeignIdIfMissing(string $tableName, string $afterColumn): void
    {
        if (
            ! Schema::hasTable($tableName)
            || Schema::hasColumn($tableName, 'accounting_bank_account_id')
        ) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($afterColumn) {
            $table->foreignId('accounting_bank_account_id')
                ->nullable()
                ->after($afterColumn)
                ->constrained('accounting_bank_accounts')
                ->nullOnDelete();
        });
    }
};
