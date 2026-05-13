@extends('marketing.layouts.app', ['seo' => $seo, 'locale' => $locale])

@section('content')
    <section class="mkt-hero" style="padding-bottom:2.5rem;">
        <div class="mkt-container">
            <header class="mkt-section-head" style="margin-bottom:0;">
                <span class="mkt-eyebrow">{{ __('marketing.privacy.last_updated') }}: {{ __('marketing.privacy.last_updated_value') }}</span>
                <h1 class="mkt-h1" style="font-size:clamp(1.75rem,3.5vw,2.75rem);">{{ __('marketing.privacy.h1') }}</h1>
            </header>
        </div>
    </section>

    <section class="mkt-section">
        <div class="mkt-container" style="max-width:900px;">
            <div class="mkt-card" style="display:grid;gap:1.5rem;">
                @foreach(trans('marketing.privacy.sections') as $section)
                    <section>
                        <h2 class="mkt-card-title">{{ $section['t'] }}</h2>
                        <p class="mkt-card-desc">{{ $section['d'] }}</p>
                    </section>
                @endforeach
            </div>
        </div>
    </section>
@endsection
