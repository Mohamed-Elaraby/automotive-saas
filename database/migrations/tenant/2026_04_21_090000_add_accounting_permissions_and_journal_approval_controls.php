<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('accounting_role', 80)->nullable()->after('password');
            $table->json('accounting_permissions')->nullable()->after('accounting_role');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->string('approval_status', 40)->nullable()->after('status');
            $table->string('risk_level', 40)->default('normal')->after('approval_status');
            $table->foreignId('approval_submitted_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approval_submitted_at')->nullable()->after('approval_submitted_by');
            $table->foreignId('approved_by')->nullable()->after('approval_submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->foreignId('rejected_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->text('approval_notes')->nullable()->after('rejected_at');

            $table->index(['approval_status', 'risk_level'], 'journal_entries_approval_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex('journal_entries_approval_status_index');
            $table->dropConstrainedForeignId('approval_submitted_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn([
                'approval_status',
                'risk_level',
                'approval_submitted_at',
                'approved_at',
                'rejected_at',
                'approval_notes',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['accounting_role', 'accounting_permissions']);
        });
    }
};
