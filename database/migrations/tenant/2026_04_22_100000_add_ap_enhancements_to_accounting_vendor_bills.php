<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addVendorBillAttachmentColumns();

        if (Schema::hasTable('accounting_vendor_bill_adjustments')) {
            return;
        }

        Schema::create('accounting_vendor_bill_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('accounting_vendor_bill_id');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->string('adjustment_number', 80)->unique();
            $table->string('type', 40)->default('credit_note');
            $table->date('adjustment_date');
            $table->decimal('amount', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->string('expense_account', 120);
            $table->string('payable_account', 120);
            $table->string('tax_account', 120)->nullable();
            $table->string('status', 40)->default('posted');
            $table->string('reference', 120)->nullable();
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->foreign('accounting_vendor_bill_id', 'avba_bill_fk')
                ->references('id')
                ->on('accounting_vendor_bills')
                ->cascadeOnDelete();
            $table->foreign('journal_entry_id', 'avba_journal_fk')
                ->references('id')
                ->on('journal_entries')
                ->nullOnDelete();
            $table->foreign('created_by', 'avba_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->index(['accounting_vendor_bill_id', 'status'], 'vendor_bill_adjustments_bill_status');
            $table->index(['adjustment_date', 'status'], 'vendor_bill_adjustments_date_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_vendor_bill_adjustments');

        Schema::table('accounting_vendor_bills', function (Blueprint $table) {
            $table->dropColumn(['attachment_name', 'attachment_reference', 'attachment_url']);
        });
    }

    protected function addVendorBillAttachmentColumns(): void
    {
        if (! Schema::hasTable('accounting_vendor_bills')) {
            return;
        }

        if (! Schema::hasColumn('accounting_vendor_bills', 'attachment_name')) {
            Schema::table('accounting_vendor_bills', function (Blueprint $table) {
                $table->string('attachment_name')->nullable()->after('notes');
            });
        }

        if (! Schema::hasColumn('accounting_vendor_bills', 'attachment_reference')) {
            Schema::table('accounting_vendor_bills', function (Blueprint $table) {
                $table->string('attachment_reference', 160)->nullable()->after('attachment_name');
            });
        }

        if (! Schema::hasColumn('accounting_vendor_bills', 'attachment_url')) {
            Schema::table('accounting_vendor_bills', function (Blueprint $table) {
                $table->string('attachment_url', 500)->nullable()->after('attachment_reference');
            });
        }
    }
};
