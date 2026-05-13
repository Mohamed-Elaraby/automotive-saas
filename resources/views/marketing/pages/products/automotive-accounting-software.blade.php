@extends('marketing.layouts.app', ['seo' => $seo, 'locale' => $locale])

@section('content')
    <section class="mkt-hero">
        <div class="mkt-container">
            <div class="mkt-hero-grid">
                <div>
                    <span class="mkt-eyebrow"><span aria-hidden="true">📊</span> {{ __('marketing.product_accounting.eyebrow') }}</span>
                    <h1 class="mkt-h1">{{ __('marketing.product_accounting.h1') }}</h1>
                    <p class="mkt-lead">{{ __('marketing.product_accounting.lead') }}</p>
                    <div class="mkt-hero-actions">
                        <a href="{{ route('marketing.start-trial', ['locale' => $locale]) }}" class="mkt-btn mkt-btn-primary mkt-btn-lg">{{ __('marketing.cta.start_trial') }}</a>
                        <a href="{{ route('marketing.book-demo', ['locale' => $locale]) }}" class="mkt-btn mkt-btn-outline mkt-btn-lg">{{ __('marketing.cta.book_demo') }}</a>
                    </div>
                </div>
                <div class="mkt-hero-visual" aria-hidden="true">
                    <div style="background:var(--mkt-bg-soft);padding:1rem 1.25rem;border-radius:12px;border:1px solid var(--mkt-border);">
                        <div style="display:flex;justify-content:space-between;color:var(--mkt-muted);font-size:.85rem;margin-bottom:.75rem;">
                            <span>P &amp; L</span>
                            <span>{{ date('Y') }}</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-weight:700;font-size:1.5rem;color:var(--mkt-dark);margin-bottom:.5rem;">
                            <span>1,248,910</span>
                            <span style="color:var(--mkt-primary);">AED</span>
                        </div>
                        <div style="height:80px;background:linear-gradient(180deg, var(--mkt-primary-50), transparent);border-radius:8px;position:relative;overflow:hidden;">
                            <svg viewBox="0 0 200 60" preserveAspectRatio="none" style="width:100%;height:100%;">
                                <polyline points="0,40 25,32 50,36 75,18 100,22 125,12 150,18 175,8 200,14"
                                          fill="none" stroke="#0d6efd" stroke-width="2"></polyline>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Why specialized accounting --}}
    <section class="mkt-section">
        <div class="mkt-container">
            <header class="mkt-section-head">
                <h2 class="mkt-section-title">{{ __('marketing.product_accounting.why_title') }}</h2>
                <p class="mkt-section-subtitle">{{ __('marketing.product_accounting.why_body') }}</p>
            </header>
        </div>
    </section>

    {{-- Features --}}
    <section class="mkt-section mkt-section-soft">
        <div class="mkt-container">
            <header class="mkt-section-head">
                <h2 class="mkt-section-title">{{ __('marketing.nav.product_accounting_title') }}</h2>
            </header>
            <div class="mkt-grid-4">
                @php $features = trans('marketing.product_accounting.features'); @endphp
                @foreach($features as $key => $f)
                    @include('marketing.components.feature-card', [
                        'icon'        => match($key) {
                            'invoices'    => '🧾',
                            'receipts'    => '🪙',
                            'expenses'    => '💸',
                            'suppliers'   => '🤝',
                            'cust_bal'    => '👥',
                            'vat'         => '📜',
                            'profit'      => '📈',
                            'tracking'    => '🔎',
                            'reports'     => '📊',
                            'integration' => '🔗',
                            default       => '✦',
                        },
                        'title'       => $f['t'],
                        'description' => $f['d'],
                    ])
                @endforeach
            </div>
        </div>
    </section>

    {{-- Related --}}
    <section class="mkt-section">
        <div class="mkt-container">
            <header class="mkt-section-head">
                <h2 class="mkt-section-title">{{ __('marketing.nav.products') }}</h2>
            </header>
            <div class="mkt-grid-2">
                @include('marketing.components.product-card', [
                    'icon'        => '🔧',
                    'title'       => __('marketing.nav.product_workshop_title'),
                    'description' => __('marketing.nav.product_workshop_desc'),
                    'href'        => route('marketing.products.workshop', ['locale' => $locale]),
                ])
                @include('marketing.components.product-card', [
                    'icon'        => '📦',
                    'title'       => __('marketing.nav.product_spare_parts_title'),
                    'description' => __('marketing.nav.product_spare_parts_desc'),
                    'href'        => route('marketing.products.spare-parts', ['locale' => $locale]),
                ])
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="mkt-section mkt-section-soft">
        <div class="mkt-container">
            <header class="mkt-section-head">
                <h2 class="mkt-section-title">{{ __('marketing.home.faq_title') }}</h2>
            </header>
            @include('marketing.components.faq-accordion', [
                'items' => trans('marketing.product_accounting.faq'),
            ])
        </div>
    </section>
@endsection

@section('cta-after')
    @include('marketing.components.cta-section', ['variant' => 'product', 'locale' => $locale])
@endsection
