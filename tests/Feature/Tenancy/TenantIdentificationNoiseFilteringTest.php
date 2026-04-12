<?php

namespace Tests\Feature\Tenancy;

use App\Exceptions\Handler;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;
use Tests\TestCase;

class TenantIdentificationNoiseFilteringTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('tenancy.database.central_connection', 'sqlite');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->ensureCentralAdminNotificationsTable();
        $this->ensureCentralSystemErrorLogsTable();
    }

    protected function tearDown(): void
    {
        $this->dropCentralAdminNotificationsTable();
        $this->dropCentralSystemErrorLogsTable();

        parent::tearDown();
    }

    public function test_it_ignores_raw_ip_tenant_identification_noise(): void
    {
        $this->reportExceptionForHost('216.128.148.123');

        $connection = $this->centralConnectionName();

        $this->assertSame(0, DB::connection($connection)->table('system_error_logs')->count());
        $this->assertSame(0, DB::connection($connection)->table('admin_notifications')->count());
    }

    public function test_it_still_records_real_hostname_tenant_identification_failures(): void
    {
        $this->reportExceptionForHost('missing-tenant.example.com');

        $connection = $this->centralConnectionName();

        $this->assertSame(1, DB::connection($connection)->table('system_error_logs')->count());
        $this->assertSame(1, DB::connection($connection)->table('admin_notifications')->count());
    }

    protected function reportExceptionForHost(string $host): void
    {
        $request = Request::create("http://{$host}/workspace/admin", 'GET');
        $request->server->set('HTTP_HOST', $host);
        $request->headers->set('host', $host);

        $this->app->instance('request', $request);

        app(Handler::class)->report(new TenantCouldNotBeIdentifiedOnDomainException($host));
    }

    protected function ensureCentralAdminNotificationsTable(): void
    {
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

    protected function ensureCentralSystemErrorLogsTable(): void
    {
        $connection = $this->centralConnectionName();

        if (! Schema::connection($connection)->hasTable('system_error_logs')) {
            Schema::connection($connection)->create('system_error_logs', function (Blueprint $table) {
                $table->id();
                $table->timestamp('occurred_at');
                $table->string('level', 20)->default('error');
                $table->string('exception_class');
                $table->text('message');
                $table->text('file_path')->nullable();
                $table->unsignedInteger('file_line')->nullable();
                $table->longText('trace_excerpt')->nullable();
                $table->string('app_env', 50)->nullable();
                $table->text('app_url')->nullable();
                $table->string('request_method', 20)->nullable();
                $table->text('request_url')->nullable();
                $table->text('request_path')->nullable();
                $table->string('route_name')->nullable();
                $table->text('route_action')->nullable();
                $table->string('ip', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_email')->nullable();
                $table->string('tenant_id')->nullable();
                $table->json('input_payload')->nullable();
                $table->json('context_payload')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->boolean('is_resolved')->default(false);
                $table->timestamp('resolved_at')->nullable();
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

    protected function dropCentralSystemErrorLogsTable(): void
    {
        $connection = $this->centralConnectionName();

        if (Schema::connection($connection)->hasTable('system_error_logs')) {
            Schema::connection($connection)->drop('system_error_logs');
        }
    }

    protected function centralConnectionName(): string
    {
        return (string) (Config::get('tenancy.database.central_connection') ?: Config::get('database.default'));
    }
}
