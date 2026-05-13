@php
    /** @var string $locale */
    $locale = $locale ?? app()->getLocale();
    $alt = $locale === 'ar' ? 'en' : 'ar';

    $currentRouteName = request()->route()?->getName() ?? 'marketing.home';
    $currentRouteParams = array_merge(['locale' => $alt], request()->route()?->parameters() ?? []);
    $currentRouteParams['locale'] = $alt;

    try {
        $altUrl = route($currentRouteName, $currentRouteParams);
    } catch (\Throwable $e) {
        $altUrl = route('marketing.home', ['locale' => $alt]);
    }
@endphp
<header class="mkt-header" role="banner">
    <div class="mkt-container mkt-header-inner">
        <a href="{{ route('marketing.home', ['locale' => $locale]) }}" class="mkt-brand" aria-label="{{ __('marketing.brand.aria_home') }}">
            <img src="{{ asset('assets/company/logo.png') }}" alt="{{ config('marketing.company.name') }}">
            <span>{{ __('marketing.brand.short_name') }} <span class="mkt-brand-product">{{ __('marketing.brand.product_short') }}</span></span>
        </a>

        <button type="button" class="mkt-nav-toggle" data-mkt-nav-toggle aria-controls="mkt-nav" aria-expanded="false" aria-label="{{ __('marketing.nav.toggle_menu') }}">
            <span aria-hidden="true">☰</span>
        </button>

        <nav class="mkt-nav" id="mkt-nav" data-mkt-nav role="navigation" aria-label="{{ __('marketing.nav.aria_primary') }}">
            <div class="mkt-dropdown">
                <a href="{{ route('marketing.products.index', ['locale' => $locale]) }}" class="mkt-nav-link" aria-haspopup="true">
                    {{ __('marketing.nav.products') }} ▾
                </a>
                <div class="mkt-dropdown-menu" role="menu">
                    <a href="{{ route('marketing.products.workshop', ['locale' => $locale]) }}" class="mkt-dropdown-item" role="menuitem">
                        <span class="mkt-dropdown-item-title">{{ __('marketing.nav.product_workshop_title') }}</span>
                        <span class="mkt-dropdown-item-desc">{{ __('marketing.nav.product_workshop_desc') }}</span>
                    </a>
                    <a href="{{ route('marketing.products.spare-parts', ['locale' => $locale]) }}" class="mkt-dropdown-item" role="menuitem">
                        <span class="mkt-dropdown-item-title">{{ __('marketing.nav.product_spare_parts_title') }}</span>
                        <span class="mkt-dropdown-item-desc">{{ __('marketing.nav.product_spare_parts_desc') }}</span>
                    </a>
                    <a href="{{ route('marketing.products.accounting', ['locale' => $locale]) }}" class="mkt-dropdown-item" role="menuitem">
                        <span class="mkt-dropdown-item-title">{{ __('marketing.nav.product_accounting_title') }}</span>
                        <span class="mkt-dropdown-item-desc">{{ __('marketing.nav.product_accounting_desc') }}</span>
                    </a>
                </div>
            </div>

            <a href="{{ route('marketing.pricing', ['locale' => $locale]) }}" class="mkt-nav-link">{{ __('marketing.nav.pricing') }}</a>
            <a href="{{ route('marketing.security', ['locale' => $locale]) }}" class="mkt-nav-link">{{ __('marketing.nav.security') }}</a>
            <a href="{{ route('marketing.contact', ['locale' => $locale]) }}" class="mkt-nav-link">{{ __('marketing.nav.contact') }}</a>

            <div class="mkt-cta-group">
                <a href="{{ $altUrl }}" class="mkt-lang-switch" rel="alternate" hreflang="{{ $alt }}" aria-label="{{ __('marketing.lang.switch_aria') }}">
                    <span aria-hidden="true">🌐</span>
                    <span>{{ $alt === 'ar' ? 'العربية' : 'English' }}</span>
                </a>
                <a href="{{ route('marketing.book-demo', ['locale' => $locale]) }}" class="mkt-btn mkt-btn-ghost">{{ __('marketing.cta.book_demo') }}</a>
                <a href="{{ route('marketing.start-trial', ['locale' => $locale]) }}" class="mkt-btn mkt-btn-primary">{{ __('marketing.cta.start_trial') }}</a>
            </div>
        </nav>
    </div>
</header>
