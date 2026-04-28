<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Symfony\Component\HttpFoundation\Response;

class ResolveLocalePrefixForRouting
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = trim($request->getPathInfo(), '/');
        $firstSegment = $path === '' ? '' : explode('/', $path, 2)[0];
        $supportedLocales = array_keys((array) config('laravellocalization.supportedLocales', []));
        $defaultLocale = (string) config('app.locale', 'en');

        if (! in_array($firstSegment, $supportedLocales, true)) {
            $this->setLocale($defaultLocale);

            return $next($request);
        }

        $locale = $firstSegment;
        $this->setLocale($locale);

        $remainingPath = trim(substr($path, strlen($firstSegment)), '/');
        $normalizedPath = '/' . $remainingPath;
        $query = $request->getQueryString();
        $normalizedUri = ($normalizedPath === '/' ? '/' : $normalizedPath) . ($query ? '?' . $query : '');

        $server = array_merge($request->server->all(), [
            'REQUEST_URI' => $normalizedUri,
            'PATH_INFO' => $normalizedPath,
        ]);

        $request = $request->duplicate(
            $request->query->all(),
            $request->request->all(),
            array_merge($request->attributes->all(), [
                'resolved_locale' => $locale,
                'original_locale_prefixed_path' => '/' . $path,
            ]),
            $request->cookies->all(),
            $request->files->all(),
            $server,
            $request->getContent()
        );

        return $next($request);
    }

    private function setLocale(string $locale): void
    {
        App::setLocale($locale);
        LaravelLocalization::setLocale($locale);
    }
}
