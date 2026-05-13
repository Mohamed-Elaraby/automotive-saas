@extends('marketing.layouts.app', ['seo' => $seo, 'locale' => $locale])

@section('content')
    <section class="mkt-hero" style="padding-bottom:2.5rem;">
        <div class="mkt-container">
            <header class="mkt-section-head" style="margin-bottom:0;">
                <span class="mkt-eyebrow">{{ __('marketing.nav.products') }}</span>
                <h1 class="mkt-h1" style="font-size:clamp(1.75rem,3.5vw,2.75rem);">{{ __('marketing.products_index.h1') }}</h1>
                <p class="mkt-lead" style="margin: 0 auto;">{{ __('marketing.products_index.lead') }}</p>
            </header>
        </div>
    </section>

    <section class="mkt-section">
        <div class="mkt-container">
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
@endsection
