<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_posting_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->string('receivable_account')->default('1100 Accounts Receivable');
            $table->string('labor_revenue_account')->default('4100 Service Labor Revenue');
            $table->string('parts_revenue_account')->default('4200 Parts Revenue');
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_event_id')->nullable()->constrained('accounting_events')->nullOnDelete();
            $table->foreignId('posting_group_id')->nullable()->constrained('accounting_posting_groups')->nullOnDelete();
            $table->string('journal_number', 80)->unique();
            $table->string('source_type', 120)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('status', 50)->default('posted');
            $table->date('entry_date');
            $table->string('currency', 3)->default('USD');
            $table->decimal('debit_total', 12, 2)->default(0);
            $table->decimal('credit_total', 12, 2)->default(0);
            $table->text('memo')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id'], 'journal_entries_source_lookup');
            $table->index(['status', 'entry_date'], 'journal_entries_status_date_index');
        });

        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->string('account_code', 120);
            $table->string('account_name')->nullable();
            $table->string('line_type', 20);
            $table->decimal('debit', 12, 2)->default(0);
            $table->decimal('credit', 12, 2)->default(0);
            $table->text('memo')->nullable();
            $table->timestamps();

            $table->index(['journal_entry_id', 'line_type'], 'journal_entry_lines_entry_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounting_posting_groups');
    }
};
