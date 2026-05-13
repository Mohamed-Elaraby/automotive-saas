<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetMarketingLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketingPageController extends Controller
{
    public function rootRedirect(Request $request): RedirectResponse
    {
        $accept = strtolower((string) $request->header('Accept-Language', ''));
        $locale = SetMarketingLocale::DEFAULT_LOCALE;

        if (str_starts_with($accept, 'ar') || str_contains($accept, ',ar') || str_contains($accept, ';ar')) {
            $locale = 'ar';
        }

        return redirect()->route('marketing.home', ['locale' => $locale]);
    }

    public function home(string $locale): View
    {
        return view('marketing.pages.home', [
            'locale' => $locale,
            'seo' => $this->seo($locale, 'home', 'marketing.home', [], [
                'jsonld' => ['organization', 'website'],
            ]),
        ]);
    }

    public function productsIndex(string $locale): View
    {
        return view('marketing.pages.products.index', [
            'locale' => $locale,
            'seo' => $this->seo($locale, 'products_index', 'marketing.products.index', [], [
                'jsonld' => ['breadcrumb-list'],
            ]),
            'breadcrumbs' => $this->breadcrumbs($locale, [
                ['title' => __('marketing.nav.products'), 'route' => null],
            ]),
        ]);
    }

    public function productWorkshop(string $locale): View
    {
        return view('marketing.pages.products.workshop-management-software', [
            'locale' => $locale,
            'seo' => $this->seo($locale, 'product_workshop', 'marketing.products.workshop', [], [
                'jsonld' => ['software-application', 'faq-page', 'breadcrumb-list'],
            ]),
            'breadcrumbs' => $this->breadcrumbs($locale, [
                ['title' => __('marketing.nav.products'), 'route' => 'marketing.products.index'],
                ['title' => __('marketing.product_workshop.crumb'), 'route' => null],
            ]),
        ]);
    }

    public function productSpareParts(string $locale): View
    {
        return view('marketing.pages.products.spare-parts-inventory-management-software', [
            'locale' => $locale,
            'seo' => $this->seo($locale, 'product_spare_parts', 'marketing.products.spare-parts', [], [
                'jsonld' => ['software-application', 'faq-page', 'breadcrumb-list'],
            ]),
            'breadcrumbs' => $this->breadcrumbs($locale, [
                ['title' => __('marketing.nav.products'), 'route' => 'marketing.products.index'],
                ['title' => __('marketing.product_spare_parts.crumb'), 'route' => null],
            ]),
        ]);
    }

    public function productAccounting(string $locale): View
    {
        return view('marketing.pages.products.automotive-accounting-software', [
            'locale' => $locale,
            'seo' => $this->seo($locale, 'product_accounting', 'marketing.products.accounting', [], [
                'jsonld' => ['software-application', 'faq-page', 'breadcrumb-list'],
            ]),
            'breadcrumbs' => $this->breadcrumbs($locale, [
                ['title' => __('marketing.nav.products'), 'route' => 'marketing.products.index'],
                ['title' => __('marketing.product_accounting.crumb'), 'route' => null],
            ]),
        ]);
    }

    public function pricing(string $locale): View
    {
        return view('marketing.pages.pricing', [
            'locale' => $locale,
            'plans'  => config('marketing.pricing.plans', []),
            'currency' => config('marketing.pricing.currency', 'AED'),
            'seo' => $this->seo($locale, 'pricing', 'marketing.pricing', [], [
                'jsonld' => ['faq-page', 'breadcrumb-list'],
            ]),
            'breadcrumbs' => $this->breadcrumbs($locale, [
                ['title' => __('marketing.pricing.crumb'), 'route' => null],
            ]),
        ]);
    }

    public function security(string $locale): View
    {
        return view('marketing.pages.security', [
            'locale' => $locale,
            'seo' => $this->seo($locale, 'security', 'marketing.security', [], [
                'jsonld' => ['breadcrumb-list'],
            ]),
            'breadcrumbs' => $this->breadcrumbs($locale, [
                ['title' => __('marketing.security.crumb'), 'route' => null],
            ]),
        ]);
    }

    public function privacyPolicy(string $locale): View
    {
        return view('marketing.pages.privacy-policy', [
            'locale' => $locale,
            'seo' => $this->seo($locale, 'privacy', 'marketing.privacy', [], [
                'jsonld' => ['breadcrumb-list'],
            ]),
            'breadcrumbs' => $this->breadcrumbs($locale, [
                ['title' => __('marketing.privacy.crumb'), 'route' => null],
            ]),
        ]);
    }

    public function termsOfService(string $locale): View
    {
        return view('marketing.pages.terms-of-service', [
            'locale' => $locale,
            'seo' => $this->seo($locale, 'terms', 'marketing.terms', [], [
                'jsonld' => ['breadcrumb-list'],
            ]),
            'breadcrumbs' => $this->breadcrumbs($locale, [
                ['title' => __('marketing.terms.crumb'), 'route' => null],
            ]),
        ]);
    }

    /**
     * Build the SEO data array consumed by marketing.partials.seo and the layout.
     *
     * @param  array<int|string, mixed>  $routeParams
     * @param  array<string, mixed>  $extras
     * @return array<string, mixed>
     */
    protected function seo(string $locale, string $key, string $routeName, array $routeParams = [], array $extras = []): array
    {
        $params = array_merge(['locale' => $locale], $routeParams);

        return array_merge([
            'title'        => __("marketing.{$key}.meta_title"),
            'description'  => __("marketing.{$key}.meta_description"),
            'canonical'    => route($routeName, $params),
            'route_name'   => $routeName,
            'route_params' => $routeParams,
            'og_image'     => asset(config('seo.defaults.og_image', '/assets/marketing/og/default.jpg')),
            'og_type'      => 'website',
            'jsonld'       => [],
            'page_key'     => $key,
        ], $extras);
    }

    /**
     * @param  array<int, array{title: string, route: ?string, params?: array<string, mixed>}>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function breadcrumbs(string $locale, array $items): array
    {
        $crumbs = [
            ['title' => __('marketing.nav.home'), 'url' => route('marketing.home', ['locale' => $locale])],
        ];

        foreach ($items as $item) {
            $url = null;
            if (! empty($item['route'])) {
                $url = route($item['route'], array_merge(['locale' => $locale], $item['params'] ?? []));
            }
            $crumbs[] = ['title' => $item['title'], 'url' => $url];
        }

        return $crumbs;
    }
}
