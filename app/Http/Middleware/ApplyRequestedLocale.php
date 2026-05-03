<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyRequestedLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestedLocale = $request->query('_locale');

        if (is_string($requestedLocale) && app('laravellocalization')->checkLocaleInSupportedLocales($requestedLocale)) {
            session(['locale' => $requestedLocale]);
            app('laravellocalization')->setLocale($requestedLocale);

            return redirect()->to($this->localizedUrlWithoutLocaleQuery($request, $requestedLocale));
        }

        $pathLocale = $request->segment(1);

        if (is_string($pathLocale) && app('laravellocalization')->checkLocaleInSupportedLocales($pathLocale)) {
            session(['locale' => $pathLocale]);
            app('laravellocalization')->setLocale($pathLocale);
        } elseif (app('laravellocalization')->hideDefaultLocaleInURL()) {
            $defaultLocale = app('laravellocalization')->getDefaultLocale();

            session(['locale' => $defaultLocale]);
            app('laravellocalization')->setLocale($defaultLocale);
        }

        $response = $next($request);

        if (app('laravellocalization')->getCurrentLocaleDirection() === 'rtl') {
            $this->applyKanakkuRtlBodyClass($response);
        }

        return $response;
    }

    private function localizedUrlWithoutLocaleQuery(Request $request, string $locale): string
    {
        $query = $request->query();
        unset($query['_locale']);

        $url = $request->url();

        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        return app('laravellocalization')->getLocalizedURL($locale, $url);
    }

    private function applyKanakkuRtlBodyClass(Response $response): void
    {
        if (! str_contains((string) $response->headers->get('content-type'), 'text/html')) {
            return;
        }

        $content = $response->getContent();

        if (! is_string($content) || ! str_contains($content, '<body') || str_contains($content, 'layout-mode-rtl')) {
            return;
        }

        $content = preg_replace_callback('/<body\b([^>]*)>/i', function (array $matches): string {
            $attributes = $matches[1];

            if (preg_match('/\bclass=(["\'])(.*?)\1/i', $attributes)) {
                $attributes = preg_replace('/\bclass=(["\'])(.*?)\1/i', 'class=$1$2 layout-mode-rtl$1', $attributes, 1);

                return '<body'.$attributes.'>';
            }

            return '<body'.$attributes.' class="layout-mode-rtl">';
        }, $content, 1);

        if (is_string($content)) {
            $response->setContent($content);
        }
    }
}
