@extends('marketing.layouts.app', ['seo' => $seo, 'locale' => $locale])

@section('content')
    <section class="mkt-hero" style="padding-bottom:2rem;">
        <div class="mkt-container">
            <header class="mkt-section-head" style="margin-bottom:1rem;">
                <span class="mkt-eyebrow">{{ __('marketing.pricing.eyebrow') }}</span>
                <h1 class="mkt-h1" style="font-size:clamp(1.75rem,3.5vw,2.75rem);">{{ __('marketing.pricing.h1') }}</h1>
                <p class="mkt-lead" style="margin: 0 auto;">{{ __('marketing.pricing.lead') }}</p>
            </header>
        </div>
    </section>

    <section class="mkt-section">
        <div class="mkt-container">
            <div class="mkt-pricing-grid">
                @foreach($plans as $plan)
                    @include('marketing.components.pricing-card', [
                        'plan'     => $plan,
                        'currency' => $currency,
                        'locale'   => $locale,
                    ])
                @endforeach
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="mkt-section mkt-section-soft">
        <div class="mkt-container">
            <header class="mkt-section-head">
                <span class="mkt-section-kicker">{{ __('marketing.pricing.faq_kicker') }}</span>
                <h2 class="mkt-section-title">{{ __('marketing.pricing.faq_title') }}</h2>
            </header>
            @include('marketing.components.faq-accordion', [
                'items' => trans('marketing.pricing.faq'),
            ])
        </div>
    </section>
@endsection
