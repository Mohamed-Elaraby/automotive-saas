@extends('marketing.layouts.app', ['seo' => $seo, 'locale' => $locale])

@section('content')
    {{-- HERO --}}
    <section class="mkt-hero">
        <div class="mkt-container">
            <div class="mkt-hero-grid">
                <div>
                    <span class="mkt-eyebrow">
                        <span aria-hidden="true">🚗</span>
                        {{ __('marketing.home.eyebrow') }}
                    </span>
                    <h1 class="mkt-h1">
                        <span>{{ __('marketing.home.h1_a') }}</span><br>
                        <span class="accent">{{ __('marketing.home.h1_b') }}</span>
                    </h1>
                    <p class="mkt-lead">{{ __('marketing.home.lead') }}</p>
                    <div class="mkt-hero-actions">
                        <a href="{{ route('marketing.start-trial', ['locale' => $locale]) }}" class="mkt-btn mkt-btn-primary mkt-btn-lg">
                            {{ __('marketing.cta.start_trial') }}
                        </a>
                        <a href="{{ route('marketing.book-demo', ['locale' => $locale]) }}" class="mkt-btn mkt-btn-outline mkt-btn-lg">
                            {{ __('marketing.cta.book_demo') }}
                        </a>
                    </div>
                    <div class="mkt-hero-trust">
                        <span>✓ {{ __('marketing.home.trust_no_card') }}</span>
                        <span>✓ {{ __('marketing.home.trust_arabic') }}</span>
                        <span>✓ {{ __('marketing.home.trust_branches') }}</span>
                    </div>
                </div>

                <div class="mkt-hero-visual" aria-hidden="true">
                    @php
                        $heroTiles = [
                            ['icon' => '🔧', 'label' => __('marketing.nav.product_workshop_title')],
                            ['icon' => '📦', 'label' => __('marketing.nav.product_spare_parts_title')],
                            ['icon' => '📊', 'label' => __('marketing.nav.product_accounting_title')],
                            ['icon' => '🚗', 'label' => __('marketing.home.audience_workshops_t')],
                        ];
                    @endphp
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                        @foreach($heroTiles as $tile)
                            <div style="background:var(--mkt-bg-soft);border:1px solid var(--mkt-border);border-radius:14px;padding:1rem;display:flex;flex-direction:column;align-items:flex-start;gap:.5rem;">
                                <span style="font-size:1.5rem;">{{ $tile['icon'] }}</span>
                                <span style="font-size:.875rem;color:var(--mkt-muted);font-weight:500;">{{ $tile['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div style="margin-top:1rem;padding:1rem;background:linear-gradient(135deg, var(--mkt-primary-50), #fff);border-radius:14px;border:1px solid var(--mkt-border);">
                        <div style="display:flex;justify-content:space-between;font-size:.85rem;color:var(--mkt-muted);">
                            <span>{{ date('Y') }}</span>
                            <span style="color:var(--mkt-primary);font-weight:700;">+24%</span>
                        </div>
                        <div style="height:8px;background:var(--mkt-border);border-radius:99px;margin-top:.5rem;overflow:hidden;">
                            <div style="width:72%;height:100%;background:var(--mkt-primary);"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- OVERVIEW --}}
    <section class="mkt-section">
        <div class="mkt-container">
            <header class="mkt-section-head">
                <span class="mkt-section-kicker">{{ __('marketing.home.overview_kicker') }}</span>
                <h2 class="mkt-section-title">{{ __('marketing.home.overview_title') }}</h2>
                <p class="mkt-section-subtitle">{{ __('marketing.home.overview_subtitle') }}</p>
            </header>
            <div class="mkt-grid-4">
                @include('marketing.components.feature-card', [
                    'icon' => '🚗',
                    'title' => __('marketing.home.overview_b1_t'),
                    'description' => __('marketing.home.overview_b1_d'),
                ])
                @include('marketing.components.feature-card', [
                    'icon' => '🌐',
                    'title' => __('marketing.home.overview_b2_t'),
                    'description' => __('marketing.home.overview_b2_d'),
                ])
                @include('marketing.components.feature-card', [
                    'icon' => '🏢',
                    'title' => __('marketing.home.overview_b3_t'),
                    'description' => __('marketing.home.overview_b3_d'),
                ])
                @include('marketing.components.feature-card', [
                    'icon' => '🔒',
                    'title' => __('marketing.home.overview_b4_t'),
                    'description' => __('marketing.home.overview_b4_d'),
                ])
            </div>
        </div>
    </section>

    {{-- THREE SYSTEMS --}}
    <section class="mkt-section mkt-section-soft" id="systems">
        <div class="mkt-container">
            <header class="mkt-section-head">
                <span class="mkt-section-kicker">{{ __('marketing.home.systems_kicker') }}</span>
                <h2 class="mkt-section-title">{{ __('marketing.home.systems_title') }}</h2>
                <p class="mkt-section-subtitle">{{ __('marketing.home.systems_subtitle') }}</p>
            </header>
            <div class="mkt-grid-3">
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
                @include('marketing.components.product-card', [
                    'icon'        => '📊',
                    'title'       => __('marketing.nav.product_accounting_title'),
                    'description' => __('marketing.nav.product_accounting_desc'),
                    'href'        => route('marketing.products.accounting', ['locale' => $locale]),
                ])
            </div>
        </div>
    </section>

    {{-- AUDIENCE --}}
    <section class="mkt-section">
        <div class="mkt-container">
            <header class="mkt-section-head">
                <span class="mkt-section-kicker">{{ __('marketing.home.audience_kicker') }}</span>
                <h2 class="mkt-section-title">{{ __('marketing.home.audience_title') }}</h2>
                <p class="mkt-section-subtitle">{{ __('marketing.home.audience_subtitle') }}</p>
            </header>
            <div class="mkt-grid-3">
                @include('marketing.components.feature-card', [
                    'icon' => '🛠️',
                    'title' => __('marketing.home.audience_workshops_t'),
                    'description' => __('marketing.home.audience_workshops_d'),
                ])
                @include('marketing.components.feature-card', [
                    'icon' => '🚙',
                    'title' => __('marketing.home.audience_centers_t'),
                    'description' => __('marketing.home.audience_centers_d'),
                ])
                @include('marketing.components.feature-card', [
                    'icon' => '🛒',
                    'title' => __('marketing.home.audience_parts_t'),
                    'description' => __('marketing.home.audience_parts_d'),
                ])
            </div>
        </div>
    </section>

    {{-- BENEFITS --}}
    <section class="mkt-section mkt-section-soft">
        <div class="mkt-container">
            <header class="mkt-section-head">
                <span class="mkt-section-kicker">{{ __('marketing.home.benefits_kicker') }}</span>
                <h2 class="mkt-section-title">{{ __('marketing.home.benefits_title') }}</h2>
                <p class="mkt-section-subtitle">{{ __('marketing.home.benefits_subtitle') }}</p>
            </header>
            <div class="mkt-grid-2" style="max-width:920px;margin:0 auto;">
                <ul class="mkt-feature-list">
                    <li>{{ __('marketing.home.benefits_b1') }}</li>
                    <li>{{ __('marketing.home.benefits_b2') }}</li>
                    <li>{{ __('marketing.home.benefits_b3') }}</li>
                </ul>
                <ul class="mkt-feature-list">
                    <li>{{ __('marketing.home.benefits_b4') }}</li>
                    <li>{{ __('marketing.home.benefits_b5') }}</li>
                    <li>{{ __('marketing.home.benefits_b6') }}</li>
                </ul>
            </div>
        </div>
    </section>

    {{-- WORKFLOW --}}
    <section class="mkt-section">
        <div class="mkt-container">
            <header class="mkt-section-head">
                <span class="mkt-section-kicker">{{ __('marketing.home.workflow_kicker') }}</span>
                <h2 class="mkt-section-title">{{ __('marketing.home.workflow_title') }}</h2>
                <p class="mkt-section-subtitle">{{ __('marketing.home.workflow_subtitle') }}</p>
            </header>
            <div class="mkt-grid-4">
                @php $steps = trans('marketing.home.workflow_steps'); @endphp
                @foreach($steps as $i => $step)
                    <div class="mkt-card">
                        <div class="mkt-card-icon">{{ $i + 1 }}</div>
                        <h3 class="mkt-card-title">{{ $step['t'] }}</h3>
                        <p class="mkt-card-desc">{{ $step['d'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- PRICING TEASER --}}
    <section class="mkt-section mkt-section-soft" id="pricing-preview">
        <div class="mkt-container">
            <header class="mkt-section-head">
                <span class="mkt-section-kicker">{{ __('marketing.home.pricing_kicker') }}</span>
                <h2 class="mkt-section-title">{{ __('marketing.home.pricing_title') }}</h2>
                <p class="mkt-section-subtitle">{{ __('marketing.home.pricing_subtitle') }}</p>
            </header>
            <div style="text-align:center;">
                <a href="{{ route('marketing.pricing', ['locale' => $locale]) }}" class="mkt-btn mkt-btn-primary mkt-btn-lg">
                    {{ __('marketing.cta.see_pricing') }}
                </a>
            </div>
        </div>
    </section>

    {{-- SECURITY --}}
    <section class="mkt-section">
        <div class="mkt-container">
            <header class="mkt-section-head">
                <span class="mkt-section-kicker">{{ __('marketing.home.security_kicker') }}</span>
                <h2 class="mkt-section-title">{{ __('marketing.home.security_title') }}</h2>
                <p class="mkt-section-subtitle">{{ __('marketing.home.security_subtitle') }}</p>
            </header>
            <div class="mkt-trust-row">
                <span class="mkt-trust-item">🔐 {{ __('marketing.home.security_b1') }}</span>
                <span class="mkt-trust-item">👥 {{ __('marketing.home.security_b2') }}</span>
                <span class="mkt-trust-item">🗂️ {{ __('marketing.home.security_b3') }}</span>
                <span class="mkt-trust-item">📜 {{ __('marketing.home.security_b4') }}</span>
            </div>
            <div style="text-align:center;margin-top:2rem;">
                <a href="{{ route('marketing.security', ['locale' => $locale]) }}" class="mkt-btn mkt-btn-outline">
                    {{ __('marketing.nav.security') }}
                </a>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="mkt-section mkt-section-soft">
        <div class="mkt-container">
            <header class="mkt-section-head">
                <span class="mkt-section-kicker">{{ __('marketing.home.faq_kicker') }}</span>
                <h2 class="mkt-section-title">{{ __('marketing.home.faq_title') }}</h2>
            </header>
            @include('marketing.components.faq-accordion', [
                'items' => trans('marketing.home.faq'),
            ])
        </div>
    </section>
@endsection
