<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetMarketingLocale
{
    public const SUPPORTED_LOCALES = ['en', 'ar'];

    public const DEFAULT_LOCALE = 'en';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) $request->route('locale', self::DEFAULT_LOCALE);

        if (! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            abort(404);
        }

        App::setLocale($locale);
        $request->attributes->set('marketing_locale', $locale);

        view()->share('locale', $locale);
        view()->share('marketing_locale', $locale);

        return $next($request);
    }
}
