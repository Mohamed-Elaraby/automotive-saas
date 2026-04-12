<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        if ($request->routeIs('automotive.admin.*')) {
            return url('/workspace');
        }

        if ($request->routeIs('automotive.*')) {
            return url('/workspace/login');
        }

        if ($request->routeIs('admin.*')) {
            return url('/admin/login');
        }

        return url('/login');
    }
}
