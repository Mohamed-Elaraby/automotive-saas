@extends('marketing.layouts.app', ['seo' => $seo, 'locale' => $locale])

@section('content')
    <section class="mkt-section">
        <div class="mkt-container">
            <div class="mkt-form-card" style="text-align:center;">
                <div class="mkt-card-icon" style="margin-inline:auto;">✓</div>
                <h1 class="mkt-section-title">{{ __("marketing.thank_you.{$kind}.h1") }}</h1>
                <p class="mkt-section-subtitle" style="margin-inline:auto;">{{ __("marketing.thank_you.{$kind}.body") }}</p>

                <p style="color:var(--mkt-muted);margin:2rem 0 1rem;">{{ __('marketing.thank_you.next_step') }}</p>
                <div class="mkt-hero-actions" style="justify-content:center;">
                    <a href="{{ route('marketing.pricing', ['locale' => $locale]) }}" class="mkt-btn mkt-btn-primary">
                        {{ __('marketing.thank_you.next_link_pricing') }}
                    </a>
                    <a href="{{ route('marketing.products.workshop', ['locale' => $locale]) }}" class="mkt-btn mkt-btn-outline">
                        {{ __('marketing.thank_you.next_link_workshop') }}
                    </a>
                    <a href="{{ route('marketing.products.spare-parts', ['locale' => $locale]) }}" class="mkt-btn mkt-btn-outline">
                        {{ __('marketing.thank_you.next_link_parts') }}
                    </a>
                    <a href="{{ route('marketing.products.accounting', ['locale' => $locale]) }}" class="mkt-btn mkt-btn-outline">
                        {{ __('marketing.thank_you.next_link_accounting') }}
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('cta-after')
@endsection
