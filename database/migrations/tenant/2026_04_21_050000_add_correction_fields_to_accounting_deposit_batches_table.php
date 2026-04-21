<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_deposit_batches', function (Blueprint $table) {
            $table->foreignId('corrected_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('corrected_at')->nullable()->after('posted_at');
            $table->text('correction_reason')->nullable()->after('notes');

            $table->index(['status', 'corrected_at'], 'accounting_deposit_batches_correction_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_deposit_batches', function (Blueprint $table) {
            $table->dropIndex('accounting_deposit_batches_correction_lookup');
            $table->dropConstrainedForeignId('corrected_by');
            $table->dropColumn(['corrected_at', 'correction_reason']);
        });
    }
};
