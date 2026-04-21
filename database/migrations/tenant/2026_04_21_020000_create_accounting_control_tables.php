<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 120)->unique();
            $table->string('name');
            $table->string('type', 40)->default('asset');
            $table->string('normal_balance', 20)->default('debit');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('accounting_period_locks', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 30)->default('locked');
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'period_start', 'period_end'], 'accounting_period_locks_lookup');
        });

        Schema::create('accounting_policies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->string('currency', 3)->default('USD');
            $table->string('inventory_asset_account')->default('1300 Inventory Asset');
            $table->string('inventory_adjustment_offset_account')->default('3900 Inventory Adjustment Offset');
            $table->string('inventory_adjustment_expense_account')->default('5100 Inventory Adjustment Expense');
            $table->string('cogs_account')->default('5000 Cost Of Goods Sold');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('accounting_audit_entries', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 80);
            $table->string('auditable_type', 120)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->text('description');
            $table->json('payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['auditable_type', 'auditable_id'], 'accounting_audit_auditable_lookup');
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_audit_entries');
        Schema::dropIfExists('accounting_policies');
        Schema::dropIfExists('accounting_period_locks');
        Schema::dropIfExists('accounting_accounts');
    }
};
