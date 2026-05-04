<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createIfMissing('generated_documents', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('product_code', 80)->index();
            $table->string('module_code', 80)->index();
            $table->string('documentable_type')->nullable();
            $table->unsignedBigInteger('documentable_id')->nullable();
            $table->string('document_type', 120)->index();
            $table->string('document_number')->index();
            $table->string('document_title');
            $table->string('language', 20)->default('en');
            $table->string('direction', 10)->default('ltr');
            $table->string('file_disk', 80)->default('local');
            $table->text('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type', 120)->default('application/pdf');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('checksum', 128)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 60)->default('pending');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->string('verified_token', 120)->nullable()->unique();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['documentable_type', 'documentable_id'], 'gen_docs_documentable_idx');
            $table->unique(['document_type', 'document_number', 'version'], 'gen_docs_type_number_version_unique');
        });

        $this->createIfMissing('document_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generated_document_id')->constrained('generated_documents')->cascadeOnDelete();
            $table->json('snapshot');
            $table->timestamps();
        });

        $this->createIfMissing('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 120);
            $table->string('name');
            $table->string('view_path');
            $table->string('language', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('document_type', 'doc_templates_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('document_snapshots');
        Schema::dropIfExists('generated_documents');
    }

    protected function createIfMissing(string $table, callable $callback): void
    {
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, $callback);
    }
};
