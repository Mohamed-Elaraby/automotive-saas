<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounting_tax_filings')) {
            return;
        }

        Schema::create('accounting_tax_filings', function (Blueprint $table) {
            $table->id();
            $table->string('filing_number', 80)->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 30)->default('draft');
            $table->string('return_type', 40)->default('vat_return');
            $table->decimal('input_tax_total', 12, 2)->default(0);
            $table->decimal('output_tax_total', 12, 2)->default(0);
            $table->decimal('net_tax_due', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'period_start', 'period_end'], 'accounting_tax_filings_status_period_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_tax_filings');
    }
};
