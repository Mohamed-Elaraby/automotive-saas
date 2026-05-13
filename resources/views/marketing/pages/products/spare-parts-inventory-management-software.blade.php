@extends('marketing.layouts.app', ['seo' => $seo, 'locale' => $locale])

@section('content')
    <section class="mkt-hero">
        <div class="mkt-container">
            <div class="mkt-hero-grid">
                <div>
                    <span class="mkt-eyebrow"><span aria-hidden="true">📦</span> {{ __('marketing.product_spare_parts.eyebrow') }}</span>
                    <h1 class="mkt-h1">{{ __('marketing.product_spare_parts.h1') }}</h1>
                    <p class="mkt-lead">{{ __('marketing.product_spare_parts.lead') }}</p>
                    <div class="mkt-hero-actions">
                        <a href="{{ route('marketing.start-trial', ['locale' => $locale]) }}" class="mkt-btn mkt-btn-primary mkt-btn-lg">{{ __('marketing.cta.start_trial') }}</a>
                        <a href="{{ route('marketing.book-demo', ['locale' => $locale]) }}" class="mkt-btn mkt-btn-outline mkt-btn-lg">{{ __('marketing.cta.book_demo') }}</a>
                    </div>
                </div>
                <div class="mkt-hero-visual" aria-hidden="true">
                    <div style="display:grid;gap:.625rem;">
                        @php
                            $sampleParts = [
                                ['sku' => 'SKU-44210-09080', 'qty' => 24, 'lvl' => 'high'],
                                ['sku' => 'SKU-90919-01210', 'qty' => 6,  'lvl' => 'low'],
                                ['sku' => 'SKU-04465-0E020', 'qty' => 18, 'lvl' => 'mid'],
                                ['sku' => 'SKU-17801-0L040', 'qty' => 2,  'lvl' => 'low'],
                            ];
                        @endphp
                        @foreach($sampleParts as $part)
                            <div style="display:flex;justify-content:space-between;align-items:center;padding:.75rem 1rem;background:var(--mkt-bg-soft);border-radius:12px;border:1px solid var(--mkt-border);">
                                <span style="font-family:'JetBrains Mono',monospace;font-size:.85rem;color:var(--mkt-muted);">{{ $part['sku'] }}</span>
                                <span style="font-weight:700;color:{{ $part['lvl']==='low' ? '#dc2626' : ($part['lvl']==='mid' ? '#d97706' : 'var(--mkt-primary)') }};">{{ $part['qty'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Future-facing message banner --}}
    <section class="mkt-container">
        <div style="background:linear-gradient(135deg,var(--mkt-primary-50),#fff);border:1px solid var(--mkt-border);border-radius:var(--mkt-radius-lg);padding:1.5rem 1.75rem;margin: 0 0 2rem;text-align:center;">
            <strong>{{ __('marketing.product_spare_parts.future_message') }}</strong>
        </div>
    </section>

    {{-- Problem & Solution --}}
    <section class="mkt-section">
        <div class="mkt-container">
            <div class="mkt-grid-2" style="align-items:center;">
                <div>
                    <h2 class="mkt-section-title" style="text-align:start;">{{ __('marketing.product_spare_parts.problem_title') }}</h2>
                    <p style="color:var(--mkt-muted);font-size:1.05rem;margin-bottom:1.25rem;">{{ __('marketing.product_spare_parts.problem_body') }}</p>
                    <ul class="mkt-feature-list">
                        <li>{{ __('marketing.product_spare_parts.problem_b1') }}</li>
                        <li>{{ __('marketing.product_spare_parts.problem_b2') }}</li>
                        <li>{{ __('marketing.product_spare_parts.problem_b3') }}</li>
                        <li>{{ __('marketing.product_spare_parts.problem_b4') }}</li>
                    </ul>
                </div>
                <div>
                    <h2 class="mkt-section-title" style="text-align:start;">{{ __('marketing.product_spare_parts.solution_title') }}</h2>
                    <p style="color:var(--mkt-muted);font-size:1.05rem;">{{ __('marketing.product_spare_parts.solution_body') }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section class="mkt-section mkt-section-soft">
        <div class="mkt-container">
            <header class="mkt-section-head">
                <h2 class="mkt-section-title">{{ __('marketing.nav.product_spare_parts_title') }}</h2>
            </header>
            <div class="mkt-grid-4">
                @php $features = trans('marketing.product_spare_parts.features'); @endphp
                @foreach($features as $key => $f)
                    @include('marketing.components.feature-card', [
                        'icon'        => match($key) {
                            'stock'       => '📦',
                            'sku'         => '🏷️',
                            'po'          => '📝',
                            'suppliers'   => '🤝',
                            'sales'       => '💰',
                            'lowstock'    => '⚠️',
                            'multibranch' => '🏢',
                            'reports'     => '📊',
                            default       => '✦',
                        },
                        'title'       => $f['t'],
                        'description' => $f['d'],
                    ])
                @endforeach
            </div>
        </div>
    </section>

    {{-- Future growth message --}}
    <section class="mkt-section">
        <div class="mkt-container">
            <div style="background:var(--mkt-dark);color:#fff;border-radius:var(--mkt-radius-lg);padding:3rem 2rem;text-align:center;max-width:1000px;margin:0 auto;">
                <h2 style="color:#fff;font-size:clamp(1.5rem,2.4vw,2rem);font-weight:800;margin:0 0 .75rem;">
                    {{ __('marketing.product_spare_parts.future_growth_title') }}
                </h2>
                <p style="color:rgba(255,255,255,0.85);max-width:680px;margin:0 auto 1rem;">
                    {{ __('marketing.product_spare_parts.future_growth_body') }}
                </p>
                <p style="color:rgba(255,255,255,0.6);font-size:.9rem;margin:0;">
                    {{ __('marketing.product_spare_parts.avoided_promises_note') }}
                </p>
            </div>
        </div>
    </section>

    {{-- Related --}}
    <section class="mkt-section mkt-section-soft">
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
                    'icon'        => '📊',
                    'title'       => __('marketing.nav.product_accounting_title'),
                    'description' => __('marketing.nav.product_accounting_desc'),
                    'href'        => route('marketing.products.accounting', ['locale' => $locale]),
                ])
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="mkt-section">
        <div class="mkt-container">
            <header class="mkt-section-head">
                <h2 class="mkt-section-title">{{ __('marketing.home.faq_title') }}</h2>
            </header>
            @include('marketing.components.faq-accordion', [
                'items' => trans('marketing.product_spare_parts.faq'),
            ])
        </div>
    </section>
@endsection

@section('cta-after')
    @include('marketing.components.cta-section', ['variant' => 'product', 'locale' => $locale])
@endsection
