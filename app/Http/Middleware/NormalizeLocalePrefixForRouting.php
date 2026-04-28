<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeLocalePrefixForRouting
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldNormalize($request)) {
            return $next($request);
        }

        $defaultLocale = (string) config('app.locale', 'en');
        $path = '/' . ltrim($request->getPathInfo(), '/');
        $query = $request->getQueryString();
        $normalizedPath = '/' . $defaultLocale . ($path === '/' ? '' : $path);
        $normalizedUri = $normalizedPath . ($query ? '?' . $query : '');

        $server = array_merge($request->server->all(), [
            'REQUEST_URI' => $normalizedUri,
            'PATH_INFO' => $normalizedPath,
        ]);

        $request = $request->duplicate(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            $request->files->all(),
            $server,
            $request->getContent()
        );

        return $next($request);
    }

    private function shouldNormalize(Request $request): bool
    {
        $path = trim($request->getPathInfo(), '/');
        $firstSegment = $path === '' ? '' : explode('/', $path, 2)[0];
        $supportedLocales = array_keys((array) config('laravellocalization.supportedLocales', []));

        if (in_array($firstSegment, $supportedLocales, true)) {
            return false;
        }

        $ignoredPrefixes = [
            'api',
            'broadcasting',
            'build',
            'css',
            'fonts',
            'images',
            'img',
            'js',
            'storage',
            'theme',
            'vendor',
        ];

        return ! in_array($firstSegment, $ignoredPrefixes, true);
    }
}
