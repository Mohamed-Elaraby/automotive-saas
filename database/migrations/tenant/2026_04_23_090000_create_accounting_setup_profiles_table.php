<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounting_setup_profiles')) {
            return;
        }

        Schema::create('accounting_setup_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('base_currency', 3)->default('USD');
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(1);
            $table->unsignedTinyInteger('fiscal_year_start_day')->default(1);
            $table->string('tax_mode', 40)->default('vat_standard');
            $table->string('chart_template', 80)->default('service_business');
            $table->string('default_receivable_account', 120)->default('1100 Accounts Receivable');
            $table->string('default_payable_account', 120)->default('2000 Accounts Payable');
            $table->string('default_cash_account', 120)->default('1000 Cash On Hand');
            $table->string('default_bank_account', 120)->default('1010 Bank Account');
            $table->string('default_revenue_account', 120)->default('4100 Service Revenue');
            $table->string('default_expense_account', 120)->default('5200 Operating Expense');
            $table->string('default_input_tax_account', 120)->default('1410 VAT Input Receivable');
            $table->string('default_output_tax_account', 120)->default('2100 VAT Output Payable');
            $table->json('payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('setup_completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_setup_profiles');
    }
};
