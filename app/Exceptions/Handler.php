<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
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
            //
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
}
