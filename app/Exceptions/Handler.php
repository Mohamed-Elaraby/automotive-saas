<?php

namespace App\Exceptions;

use App\Services\Notifications\AdminNotificationService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            $this->storeExceptionInDatabase($e);
            $this->storeExceptionNotification($e);
        });
    }

    protected function context(): array
    {
        $request = request();

        $user = null;

        try {
            $user = auth()->user();
        } catch (Throwable $exception) {
            $user = null;
        }

        return array_filter([
            'app_env' => config('app.env'),
            'app_url' => config('app.url'),
            'request_id' => $request?->headers->get('X-Request-Id'),
            'request_method' => $request?->method(),
            'request_url' => $request?->fullUrl(),
            'request_path' => $request?->path(),
            'route_name' => optional($request?->route())->getName(),
            'route_action' => optional($request?->route())->getActionName(),
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'tenant_id' => $user->tenant_id ?? null,
            'input' => $this->safeInput($request),
        ], function ($value) {
        return ! is_null($value);
    });
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        return parent::unauthenticated($request, $exception);
    }

    protected function safeInput(?Request $request): array
    {
        if (! $request) {
            return [];
        }

        return collect($request->except([
            'password',
            'password_confirmation',
            'current_password',
            '_token',
        ]))
            ->map(function ($value) {
                if (is_array($value)) {
                    return '[array]';
                }

                if (is_object($value)) {
                    return '[object]';
                }

                $stringValue = (string) $value;

                return mb_strlen($stringValue) > 500
                    ? mb_substr($stringValue, 0, 500) . '...[truncated]'
                    : $stringValue;
            })
            ->toArray();
    }

    protected function storeExceptionInDatabase(Throwable $e): void
    {
        try {
            if ($this->shouldntReport($e) || $this->shouldIgnoreAdministrativeNoise($e)) {
                return;
            }

            $connection = (string) (config('tenancy.database.central_connection') ?? config('database.default'));

            if (! Schema::connection($connection)->hasTable('system_error_logs')) {
                return;
            }

            $request = request();
            $user = null;

            try {
                $user = auth()->user();
            } catch (Throwable $exception) {
                $user = null;
            }

            $context = $this->context();

            DB::connection($connection)
                ->table('system_error_logs')
                ->insert([
                    'occurred_at' => now(),
                    'level' => 'error',
                    'exception_class' => get_class($e),
                    'message' => Str::limit((string) $e->getMessage(), 5000, '...[truncated]'),
                    'file_path' => $e->getFile(),
                    'file_line' => $e->getLine(),
                    'trace_excerpt' => Str::limit($e->getTraceAsString(), 20000, '...[truncated]'),
                    'app_env' => config('app.env'),
                    'app_url' => config('app.url'),
                    'request_method' => $request?->method(),
                    'request_url' => $request?->fullUrl(),
                    'request_path' => $request?->path(),
                    'route_name' => optional($request?->route())->getName(),
                    'route_action' => optional($request?->route())->getActionName(),
                    'ip' => $request?->ip(),
                    'user_agent' => $request?->userAgent(),
                    'user_id' => $user?->id,
                    'user_email' => $user?->email,
                    'tenant_id' => $user->tenant_id ?? null,
                    'input_payload' => json_encode($this->safeInput($request), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'context_payload' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'is_read' => false,
                    'read_at' => null,
                    'is_resolved' => false,
                    'resolved_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        } catch (Throwable $loggingException) {
        }
    }

    protected function storeExceptionNotification(Throwable $e): void
    {
        try {
            if ($this->shouldntReport($e) || $this->shouldIgnoreAdministrativeNoise($e)) {
                return;
            }

            $request = request();
            $user = null;

            try {
                $user = auth()->user();
            } catch (Throwable $exception) {
                $user = null;
            }

            app(AdminNotificationService::class)->createSystemErrorNotification(
                message: Str::limit((string) $e->getMessage(), 1000, '...[truncated]'),
                exceptionClass: get_class($e),
                tenantId: $user->tenant_id ?? null,
                userId: $user?->id,
                userEmail: $user?->email,
                contextPayload: [
                'exception_class' => get_class($e),
                'request_url' => $request?->fullUrl(),
                    'request_method' => $request?->method(),
                    'route_name' => optional($request?->route())->getName(),
                    'route_action' => optional($request?->route())->getActionName(),
                    'ip' => $request?->ip(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            );
        } catch (Throwable $notificationException) {
        }
    }

    protected function shouldIgnoreAdministrativeNoise(Throwable $e): bool
    {
        if (! $e instanceof TenantCouldNotBeIdentifiedOnDomainException) {
            return false;
        }

        $host = strtolower((string) request()?->getHost());

        if ($host === '') {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        if ($host === 'localhost') {
            return true;
        }

        return ! $this->looksLikeDnsHost($host);
    }

    protected function looksLikeDnsHost(string $host): bool
    {
        if (! str_contains($host, '.')) {
            return false;
        }

        foreach (explode('.', $host) as $label) {
            if ($label === '' || strlen($label) > 63) {
                return false;
            }

            if ($label[0] === '-' || substr($label, -1) === '-') {
                return false;
            }

            if (! preg_match('/^[a-z0-9-]+$/', $label)) {
                return false;
            }
        }

        return true;
    }
}
