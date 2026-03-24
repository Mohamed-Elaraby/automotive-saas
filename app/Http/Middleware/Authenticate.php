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
            return route('automotive.admin.login');
        }

        if ($request->routeIs('admin.*')) {
            return route('login');
        }

        return route('login');
    }
}
