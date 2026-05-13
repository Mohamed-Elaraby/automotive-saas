@extends('marketing.layouts.app', ['seo' => $seo, 'locale' => $locale])

@section('content')
    <section class="mkt-hero">
        <div class="mkt-container">
            <div class="mkt-hero-grid">
                <div>
                    <span class="mkt-eyebrow"><span aria-hidden="true">🔧</span> {{ __('marketing.product_workshop.eyebrow') }}</span>
                    <h1 class="mkt-h1">{{ __('marketing.product_workshop.h1') }}</h1>
                    <p class="mkt-lead">{{ __('marketing.product_workshop.lead') }}</p>
                    <div class="mkt-hero-actions">
                        <a href="{{ route('marketing.start-trial', ['locale' => $locale]) }}" class="mkt-btn mkt-btn-primary mkt-btn-lg">{{ __('marketing.cta.start_trial') }}</a>
                        <a href="{{ route('marketing.book-demo', ['locale' => $locale]) }}" class="mkt-btn mkt-btn-outline mkt-btn-lg">{{ __('marketing.cta.book_demo') }}</a>
                    </div>
                </div>
                <div class="mkt-hero-visual" aria-hidden="true">
                    <div style="display:grid;gap:.75rem;">
                        <div style="display:flex;justify-content:space-between;padding:.875rem 1rem;background:var(--mkt-bg-soft);border-radius:12px;border:1px solid var(--mkt-border);">
                            <span>📋 {{ __('marketing.product_workshop.features.jobcard.t') }}</span>
                            <span style="color:var(--mkt-primary);font-weight:700;">●</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:.875rem 1rem;background:var(--mkt-bg-soft);border-radius:12px;border:1px solid var(--mkt-border);">
                            <span>🚗 {{ __('marketing.product_workshop.features.history.t') }}</span>
                            <span style="color:var(--mkt-primary);font-weight:700;">●</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:.875rem 1rem;background:var(--mkt-bg-soft);border-radius:12px;border:1px solid var(--mkt-border);">
                            <span>🛠️ {{ __('marketing.product_workshop.features.tech.t') }}</span>
                            <span style="color:var(--mkt-primary);font-weight:700;">●</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:.875rem 1rem;background:var(--mkt-bg-soft);border-radius:12px;border:1px solid var(--mkt-border);">
                            <span>💳 {{ __('marketing.product_workshop.features.invoice.t') }}</span>
                            <span style="color:var(--mkt-primary);font-weight:700;">●</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Problem --}}
    <section class="mkt-section">
        <div class="mkt-container">
            <div class="mkt-grid-2" style="align-items:center;">
                <div>
                    <h2 class="mkt-section-title" style="text-align:start;">{{ __('marketing.product_workshop.problem_title') }}</h2>
                    <p style="color:var(--mkt-muted);font-size:1.05rem;margin-bottom:1.25rem;">{{ __('marketing.product_workshop.problem_body') }}</p>
                    <ul class="mkt-feature-list">
                        <li>{{ __('marketing.product_workshop.problem_b1') }}</li>
                        <li>{{ __('marketing.product_workshop.problem_b2') }}</li>
                        <li>{{ __('marketing.product_workshop.problem_b3') }}</li>
                        <li>{{ __('marketing.product_workshop.problem_b4') }}</li>
                    </ul>
                </div>
                <div>
                    <h2 class="mkt-section-title" style="text-align:start;">{{ __('marketing.product_workshop.solution_title') }}</h2>
                    <p style="color:var(--mkt-muted);font-size:1.05rem;">{{ __('marketing.product_workshop.solution_body') }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section class="mkt-section mkt-section-soft">
        <div class="mkt-container">
            <header class="mkt-section-head">
                <h2 class="mkt-section-title">{{ __('marketing.nav.product_workshop_title') }}</h2>
            </header>
            <div class="mkt-grid-4">
                @php $features = trans('marketing.product_workshop.features'); @endphp
                @foreach($features as $key => $f)
                    @include('marketing.components.feature-card', [
                        'icon'        => match($key) {
                            'jobcard'   => '📋',
                            'history'   => '📚',
                            'customers' => '👥',
                            'tech'      => '🛠️',
                            'invoice'   => '💳',
                            'reports'   => '📊',
                            'multi'     => '🏢',
                            'lang'      => '🌐',
                            default     => '✦',
                        },
                        'title'       => $f['t'],
                        'description' => $f['d'],
                    ])
                @endforeach
            </div>
        </div>
    </section>

    {{-- Related links --}}
    <section class="mkt-section">
        <div class="mkt-container">
            <header class="mkt-section-head">
                <h2 class="mkt-section-title">{{ __('marketing.nav.products') }}</h2>
            </header>
            <div class="mkt-grid-2">
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

    {{-- FAQ --}}
    <section class="mkt-section mkt-section-soft">
        <div class="mkt-container">
            <header class="mkt-section-head">
                <h2 class="mkt-section-title">{{ __('marketing.home.faq_title') }}</h2>
            </header>
            @include('marketing.components.faq-accordion', [
                'items' => trans('marketing.product_workshop.faq'),
            ])
        </div>
    </section>
@endsection

@section('cta-after')
    @include('marketing.components.cta-section', ['variant' => 'product', 'locale' => $locale])
@endsection
