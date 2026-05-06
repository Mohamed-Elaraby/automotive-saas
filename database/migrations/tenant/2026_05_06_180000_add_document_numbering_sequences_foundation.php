<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('generated_documents')) {
            Schema::table('generated_documents', function (Blueprint $table) {
                if (! Schema::hasColumn('generated_documents', 'product_key')) {
                    $table->string('product_key', 80)->nullable()->index('gen_docs_product_key_idx');
                }

                if (! Schema::hasColumn('generated_documents', 'metadata')) {
                    $table->json('metadata')->nullable();
                }
            });
        }

        if (Schema::hasTable('document_templates')) {
            Schema::table('document_templates', function (Blueprint $table) {
                if (! Schema::hasColumn('document_templates', 'tenant_id')) {
                    $table->string('tenant_id')->nullable()->index('doc_tpl_tenant_idx');
                }

                if (! Schema::hasColumn('document_templates', 'product_key')) {
                    $table->string('product_key', 80)->nullable()->index('doc_tpl_product_idx');
                }

                if (! Schema::hasColumn('document_templates', 'document_key')) {
                    $table->string('document_key', 120)->nullable()->index('doc_tpl_key_idx');
                }
            });
        }

        if (! Schema::hasTable('numbering_sequences')) {
            Schema::create('numbering_sequences', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->nullable();
                $table->string('product_key', 80);
                $table->string('document_type', 120);
                $table->foreignId('branch_id')->nullable();
                $table->unsignedSmallInteger('year')->nullable();
                $table->string('prefix', 80);
                $table->unsignedBigInteger('next_number')->default(1);
                $table->unsignedSmallInteger('padding')->default(4);
                $table->string('reset_strategy', 40)->default('yearly');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('branch_id', 'numseq_branch_fk')->references('id')->on('branches')->nullOnDelete();
                $table->unique(['tenant_id', 'product_key', 'document_type', 'branch_id', 'year'], 'numseq_scope_uq');
                $table->index(['product_key', 'document_type'], 'numseq_product_doc_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('numbering_sequences');

        if (Schema::hasTable('document_templates')) {
            Schema::table('document_templates', function (Blueprint $table) {
                foreach (['document_key', 'product_key', 'tenant_id'] as $column) {
                    if (Schema::hasColumn('document_templates', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('generated_documents')) {
            Schema::table('generated_documents', function (Blueprint $table) {
                foreach (['metadata', 'product_key'] as $column) {
                    if (Schema::hasColumn('generated_documents', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
