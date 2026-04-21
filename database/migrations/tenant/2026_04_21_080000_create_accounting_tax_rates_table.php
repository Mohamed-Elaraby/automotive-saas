<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->decimal('rate', 8, 4)->default(0);
            $table->string('input_tax_account')->default('1410 VAT Input Receivable');
            $table->string('output_tax_account')->default('2100 VAT Output Payable');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('accounting_vendor_bills', function (Blueprint $table) {
            $table->foreignId('accounting_tax_rate_id')->nullable()->after('journal_entry_id')->constrained('accounting_tax_rates')->nullOnDelete();
            $table->decimal('tax_amount', 12, 2)->default(0)->after('amount');
            $table->string('tax_account')->nullable()->after('payable_account');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_vendor_bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accounting_tax_rate_id');
            $table->dropColumn(['tax_amount', 'tax_account']);
        });

        Schema::dropIfExists('accounting_tax_rates');
    }
};
