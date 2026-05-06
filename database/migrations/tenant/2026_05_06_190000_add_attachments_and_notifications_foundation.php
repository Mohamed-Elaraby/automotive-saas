<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_attachments')) {
            Schema::create('tenant_attachments', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->nullable();
                $table->string('product_key', 80);
                $table->foreignId('branch_id')->nullable();
                $table->morphs('attachable');
                $table->string('original_name')->nullable();
                $table->string('stored_name')->nullable();
                $table->string('mime_type', 120)->nullable();
                $table->string('extension', 40)->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->string('disk', 80)->default('public');
                $table->text('storage_path');
                $table->string('visibility', 40)->default('private');
                $table->foreignId('uploaded_by')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('branch_id', 'tatt_branch_fk')->references('id')->on('branches')->nullOnDelete();
                $table->foreign('uploaded_by', 'tatt_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->index(['tenant_id', 'product_key'], 'tatt_tenant_product_idx');
                $table->index(['branch_id', 'product_key'], 'tatt_branch_product_idx');
            });
        }

        if (! Schema::hasTable('notification_templates')) {
            Schema::create('notification_templates', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->nullable();
                $table->string('product_key', 80);
                $table->string('event_key', 160);
                $table->string('channel', 40)->default('in_app');
                $table->string('language', 20)->nullable();
                $table->string('subject')->nullable();
                $table->text('body');
                $table->boolean('is_active')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'product_key', 'event_key', 'channel', 'language'], 'ntpl_scope_uq');
                $table->index(['product_key', 'event_key', 'channel'], 'ntpl_product_event_idx');
            });
        }

        if (! Schema::hasTable('tenant_notifications')) {
            Schema::create('tenant_notifications', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->nullable();
                $table->string('product_key', 80);
                $table->foreignId('branch_id')->nullable();
                $table->string('event_key', 160);
                $table->string('channel', 40)->default('in_app');
                $table->string('recipient_type', 80)->nullable();
                $table->unsignedBigInteger('recipient_id')->nullable();
                $table->string('recipient_contact')->nullable();
                $table->string('title')->nullable();
                $table->text('body')->nullable();
                $table->string('status', 40)->default('pending');
                $table->json('metadata')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();

                $table->foreign('branch_id', 'tnot_branch_fk')->references('id')->on('branches')->nullOnDelete();
                $table->index(['tenant_id', 'product_key', 'event_key'], 'tnot_event_idx');
                $table->index(['recipient_type', 'recipient_id', 'read_at'], 'tnot_recipient_read_idx');
                $table->index(['channel', 'status', 'created_at'], 'tnot_channel_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_notifications');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('tenant_attachments');
    }
};
