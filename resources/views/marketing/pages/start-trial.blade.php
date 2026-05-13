@extends('marketing.layouts.app', ['seo' => $seo, 'locale' => $locale])

@section('content')
    <section class="mkt-hero" style="padding-bottom:2rem;">
        <div class="mkt-container">
            <header class="mkt-section-head" style="margin-bottom:0;">
                <span class="mkt-eyebrow">{{ __('marketing.cta.start_trial') }}</span>
                <h1 class="mkt-h1" style="font-size:clamp(1.75rem,3.5vw,2.75rem);">{{ __('marketing.start_trial.h1') }}</h1>
                <p class="mkt-lead" style="margin:0 auto;">{{ __('marketing.start_trial.lead') }}</p>
            </header>
        </div>
    </section>

    <section class="mkt-form-section">
        <div class="mkt-container">
            <div class="mkt-form-card">
                @include('marketing.components.lead-form', [
                    'action' => route('marketing.start-trial.submit', ['locale' => $locale]),
                    'kind' => 'start_trial',
                    'locale' => $locale,
                    'businessTypes' => $businessTypes,
                    'interestedSystems' => $interestedSystems,
                    'preferredLanguages' => $preferredLanguages,
                    'countries' => $countries,
                ])
            </div>
        </div>
    </section>
@endsection

@section('cta-after')
@endsection
