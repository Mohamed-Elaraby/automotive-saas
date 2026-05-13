@extends('marketing.layouts.app', ['seo' => $seo, 'locale' => $locale])

@section('content')
    <section class="mkt-hero" style="padding-bottom:2.5rem;">
        <div class="mkt-container">
            <header class="mkt-section-head" style="margin-bottom:0;">
                <span class="mkt-eyebrow">{{ __('marketing.security.eyebrow') }}</span>
                <h1 class="mkt-h1" style="font-size:clamp(1.75rem,3.5vw,2.75rem);">{{ __('marketing.security.h1') }}</h1>
                <p class="mkt-lead" style="margin:0 auto;">{{ __('marketing.security.lead') }}</p>
            </header>
        </div>
    </section>

    <section class="mkt-section">
        <div class="mkt-container">
            <div class="mkt-grid-4">
                @foreach(trans('marketing.security.sections') as $section)
                    @include('marketing.components.feature-card', [
                        'icon' => '🔒',
                        'title' => $section['t'],
                        'description' => $section['d'],
                    ])
                @endforeach
            </div>
        </div>
    </section>
@endsection
