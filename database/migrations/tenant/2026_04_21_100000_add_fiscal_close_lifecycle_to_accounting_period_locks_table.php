<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_period_locks', function (Blueprint $table) {
            $table->json('close_checklist')->nullable()->after('notes');
            $table->boolean('lock_override')->default(false)->after('close_checklist');
            $table->text('lock_override_reason')->nullable()->after('lock_override');
            $table->foreignId('closing_started_by')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
            $table->timestamp('closing_started_at')->nullable()->after('closing_started_by');
            $table->foreignId('archived_by')->nullable()->after('closing_started_at')->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable()->after('archived_by');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_period_locks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closing_started_by');
            $table->dropConstrainedForeignId('archived_by');
            $table->dropColumn([
                'close_checklist',
                'lock_override',
                'lock_override_reason',
                'closing_started_at',
                'archived_at',
            ]);
        });
    }
};
