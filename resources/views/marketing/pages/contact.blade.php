@extends('marketing.layouts.app', ['seo' => $seo, 'locale' => $locale])

@section('content')
    <section class="mkt-hero" style="padding-bottom:2rem;">
        <div class="mkt-container">
            <div class="mkt-hero-grid">
                <div>
                    <span class="mkt-eyebrow">{{ __('marketing.nav.contact') }}</span>
                    <h1 class="mkt-h1">{{ __('marketing.contact.h1') }}</h1>
                    <p class="mkt-lead">{{ __('marketing.contact.lead') }}</p>
                </div>
                <div class="mkt-hero-visual">
                    <div class="mkt-card" style="box-shadow:none;">
                        <div class="mkt-card-icon">✉</div>
                        <h2 class="mkt-card-title">{{ __('marketing.contact.side_email') }}</h2>
                        <p class="mkt-card-desc">
                            <a href="mailto:{{ config('marketing.company.sales_email', __('marketing.contact.side_email_value')) }}">
                                {{ config('marketing.company.sales_email', __('marketing.contact.side_email_value')) }}
                            </a>
                        </p>
                        <p class="mkt-card-desc" style="margin-top:.75rem;">{{ __('marketing.contact.side_response') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mkt-form-section">
        <div class="mkt-container">
            <div class="mkt-form-card">
                @include('marketing.components.lead-form', [
                    'action' => route('marketing.contact.submit', ['locale' => $locale]),
                    'kind' => 'contact',
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
