<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounting_exchange_rates')) {
            Schema::create('accounting_exchange_rates', function (Blueprint $table) {
                $table->id();
                $table->string('base_currency', 3);
                $table->string('foreign_currency', 3);
                $table->date('rate_date');
                $table->decimal('rate_to_base', 18, 8);
                $table->string('source', 80)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['base_currency', 'foreign_currency', 'rate_date'], 'accounting_exchange_rates_unique');
                $table->index(['foreign_currency', 'rate_date'], 'accounting_exchange_rates_lookup');
            });
        }

        if (! Schema::hasTable('accounting_fx_revaluations')) {
            Schema::create('accounting_fx_revaluations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('journal_entry_id');
                $table->foreign('journal_entry_id', 'acc_fx_rev_journal_fk')
                    ->references('id')
                    ->on('journal_entries')
                    ->cascadeOnDelete();
                $table->string('account_code', 120);
                $table->string('base_currency', 3);
                $table->string('foreign_currency', 3);
                $table->date('rate_date');
                $table->decimal('exchange_rate', 18, 8);
                $table->decimal('foreign_amount', 14, 2);
                $table->decimal('carrying_base_amount', 14, 2);
                $table->decimal('revalued_base_amount', 14, 2);
                $table->decimal('gain_loss_amount', 14, 2);
                $table->string('gain_loss_direction', 20);
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['account_code', 'rate_date'], 'accounting_fx_revaluations_lookup');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_fx_revaluations');
        Schema::dropIfExists('accounting_exchange_rates');
    }
};
