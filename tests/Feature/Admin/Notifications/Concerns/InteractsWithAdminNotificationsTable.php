<?php

namespace Tests\Feature\Admin\Notifications\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

trait InteractsWithAdminNotificationsTable
{
    protected function ensureCentralAdminNotificationsTable(): void
    {
        $this->useCanonicalCentralAdminHost();

        $connection = $this->centralConnectionName();

        if (! Schema::connection($connection)->hasTable('admin_notifications')) {
            Schema::connection($connection)->create('admin_notifications', function (Blueprint $table) {
                $table->id();
                $table->string('type');
                $table->string('title');
                $table->text('message')->nullable();
                $table->string('severity')->default('info');
                $table->string('source_type')->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('route_name')->nullable();
                $table->json('route_params')->nullable();
                $table->text('target_url')->nullable();
                $table->string('tenant_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_email')->nullable();
                $table->json('context_payload')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->boolean('is_archived')->default(false);
                $table->timestamp('archived_at')->nullable();
                $table->timestamp('notified_at')->nullable();
                $table->timestamps();
            });
        }
    }

    protected function dropCentralAdminNotificationsTable(): void
    {
        $connection = $this->centralConnectionName();

        if (Schema::connection($connection)->hasTable('admin_notifications')) {
            Schema::connection($connection)->drop('admin_notifications');
        }
    }

    protected function centralConnectionName(): string
    {
        return (string) (Config::get('tenancy.database.central_connection') ?: Config::get('database.default'));
    }

    protected function useCanonicalCentralAdminHost(): void
    {
        $canonicalHost = 'https://seven-scapital.com';

        Config::set('app.url', $canonicalHost);
        URL::forceRootUrl($canonicalHost);
    }
}
