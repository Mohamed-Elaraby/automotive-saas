<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $entries = [];
        $supportedLocales = config('seo.supported_locales', ['en', 'ar']);
        $defaultLocale    = config('seo.default_locale', 'en');

        foreach (config('marketing.sitemap_paths', []) as $page) {
            $alternates = [];
            foreach ($supportedLocales as $alt) {
                $alternates[$alt] = route($page['route'], ['locale' => $alt]);
            }

            foreach ($supportedLocales as $loc) {
                $entries[] = [
                    'loc'        => route($page['route'], ['locale' => $loc]),
                    'changefreq' => $page['changefreq'] ?? 'monthly',
                    'priority'   => $page['priority'] ?? '0.5',
                    'alternates' => $alternates,
                    'x_default'  => $alternates[$defaultLocale] ?? array_values($alternates)[0],
                ];
            }
        }

        $xml = view('marketing.sitemap', ['entries' => $entries])->render();

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
