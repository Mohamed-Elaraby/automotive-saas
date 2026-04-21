<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_integration_handoffs', function (Blueprint $table) {
            $table->id();
            $table->string('integration_key', 120);
            $table->string('event_name', 120);
            $table->string('source_product', 120);
            $table->string('target_product', 120)->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('status', 50)->default('pending');
            $table->string('idempotency_key', 160)->unique();
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['integration_key', 'status'], 'workspace_handoffs_integration_status_index');
            $table->index(['source_type', 'source_id'], 'workspace_handoffs_source_index');
            $table->index(['target_type', 'target_id'], 'workspace_handoffs_target_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_integration_handoffs');
    }
};
