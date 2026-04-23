<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounting_period_close_adjustments')) {
            return;
        }

        Schema::create('accounting_period_close_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_period_lock_id')->nullable();
            $table->foreign('accounting_period_lock_id', 'acc_pca_period_fk')
                ->references('id')
                ->on('accounting_period_locks')
                ->nullOnDelete();
            $table->foreignId('journal_entry_id');
            $table->foreign('journal_entry_id', 'acc_pca_journal_fk')
                ->references('id')
                ->on('journal_entries')
                ->cascadeOnDelete();
            $table->string('adjustment_type', 40)->default('closing_entry');
            $table->date('target_period_start');
            $table->date('target_period_end');
            $table->text('rationale');
            $table->string('review_status', 30)->default('pending');
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable();
            $table->foreign('reviewed_by', 'acc_pca_reviewed_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreign('created_by', 'acc_pca_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique('journal_entry_id', 'accounting_period_close_adjustments_journal_unique');
            $table->index(['target_period_start', 'target_period_end'], 'accounting_period_close_adjustments_period_lookup');
            $table->index(['review_status', 'adjustment_type'], 'accounting_period_close_adjustments_review_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_period_close_adjustments');
    }
};
