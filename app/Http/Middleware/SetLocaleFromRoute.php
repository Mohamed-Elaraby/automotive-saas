<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromRoute
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) $request->route('locale', '');
        $supportedLocales = array_keys((array) config('laravellocalization.supportedLocales', []));
        $defaultLocale = (string) config('app.locale', 'en');

        if ($locale === '' || ! in_array($locale, $supportedLocales, true)) {
            $locale = $defaultLocale;
        }

        App::setLocale($locale);
        LaravelLocalization::setLocale($locale);

        if ($locale !== $defaultLocale) {
            URL::defaults(['locale' => $locale]);
        }

        return $next($request);
    }
}
