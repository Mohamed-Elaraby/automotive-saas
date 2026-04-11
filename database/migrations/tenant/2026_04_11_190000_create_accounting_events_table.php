<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 100);
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');
            $table->string('status', 50)->default('posted');
            $table->timestamp('event_date')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('labor_amount', 12, 2)->default(0);
            $table->decimal('parts_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->json('payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_type', 'reference_type', 'reference_id'], 'accounting_events_reference_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_events');
    }
};
