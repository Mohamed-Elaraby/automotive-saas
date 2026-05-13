@php
    /** @var string $locale */
    $locale = $locale ?? app()->getLocale();
    $alt = $locale === 'ar' ? 'en' : 'ar';
@endphp
<footer class="mkt-footer" role="contentinfo">
    <div class="mkt-container">
        <div class="mkt-footer-grid">
            <div>
                <a href="{{ route('marketing.home', ['locale' => $locale]) }}" class="mkt-brand" style="color:#fff;">
                    <img src="{{ asset('assets/company/logo.png') }}" alt="{{ config('marketing.company.name') }}">
                    <span>{{ __('marketing.brand.short_name') }} <span style="color:#93c5fd;">{{ __('marketing.brand.product_short') }}</span></span>
                </a>
                <p style="margin: 1rem 0 0; color: rgba(255,255,255,0.7); font-size: 0.9rem; max-width: 28rem;">
                    {{ __('marketing.footer.tagline') }}
                </p>
                <p style="margin-top: 1rem; font-size: 0.85rem; color: rgba(255,255,255,0.6);">
                    {{ __('marketing.footer.contact_email') }}: <a href="mailto:{{ config('marketing.company.email') }}">{{ config('marketing.company.email') }}</a>
                </p>
            </div>

            <div>
                <h4>{{ __('marketing.footer.products_heading') }}</h4>
                <ul>
                    <li><a href="{{ route('marketing.products.workshop', ['locale' => $locale]) }}">{{ __('marketing.nav.product_workshop_title') }}</a></li>
                    <li><a href="{{ route('marketing.products.spare-parts', ['locale' => $locale]) }}">{{ __('marketing.nav.product_spare_parts_title') }}</a></li>
                    <li><a href="{{ route('marketing.products.accounting', ['locale' => $locale]) }}">{{ __('marketing.nav.product_accounting_title') }}</a></li>
                    <li><a href="{{ route('marketing.products.index', ['locale' => $locale]) }}">{{ __('marketing.footer.all_products') }}</a></li>
                </ul>
            </div>

            <div>
                <h4>{{ __('marketing.footer.company_heading') }}</h4>
                <ul>
                    <li><a href="{{ route('marketing.pricing', ['locale' => $locale]) }}">{{ __('marketing.nav.pricing') }}</a></li>
                    <li><a href="{{ route('marketing.security', ['locale' => $locale]) }}">{{ __('marketing.nav.security') }}</a></li>
                    <li><a href="{{ route('marketing.contact', ['locale' => $locale]) }}">{{ __('marketing.nav.contact') }}</a></li>
                </ul>
            </div>

            <div>
                <h4>{{ __('marketing.footer.get_started_heading') }}</h4>
                <ul>
                    <li><a href="{{ route('marketing.start-trial', ['locale' => $locale]) }}">{{ __('marketing.cta.start_trial') }}</a></li>
                    <li><a href="{{ route('marketing.book-demo', ['locale' => $locale]) }}">{{ __('marketing.cta.book_demo') }}</a></li>
                    <li><a href="{{ route('marketing.contact', ['locale' => $locale]) }}">{{ __('marketing.cta.contact_sales') }}</a></li>
                </ul>
            </div>

            <div>
                <h4>{{ __('marketing.footer.legal_heading') }}</h4>
                <ul>
                    <li><a href="{{ route('marketing.privacy', ['locale' => $locale]) }}">{{ __('marketing.nav.privacy') }}</a></li>
                    <li><a href="{{ route('marketing.terms', ['locale' => $locale]) }}">{{ __('marketing.nav.terms') }}</a></li>
                    <li>
                        <a href="{{ route('marketing.home', ['locale' => $alt]) }}" rel="alternate" hreflang="{{ $alt }}">
                            {{ $alt === 'ar' ? 'العربية' : 'English' }}
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="mkt-footer-bottom">
            <span>© {{ date('Y') }} {{ config('marketing.company.legal_name') }}. {{ __('marketing.footer.rights_reserved') }}</span>
            <span>{{ __('marketing.footer.address') }}</span>
        </div>
    </div>
</footer>
