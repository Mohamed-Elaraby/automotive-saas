<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounting_statement_notes')) {
            return;
        }

        Schema::create('accounting_statement_notes', function (Blueprint $table) {
            $table->id();
            $table->string('statement_type', 40);
            $table->string('note_key', 120);
            $table->string('title');
            $table->text('disclosure_text');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['statement_type', 'note_key'], 'accounting_statement_notes_unique');
            $table->index(['statement_type', 'is_active', 'sort_order'], 'accounting_statement_notes_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_statement_notes');
    }
};
