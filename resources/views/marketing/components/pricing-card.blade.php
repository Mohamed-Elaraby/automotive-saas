@php
    /** @var array $plan */
    /** @var string $currency */
    /** @var string $locale */
    $key       = $plan['key'];
    $name      = __("marketing.pricing.plans.{$key}.name");
    $tagline   = __("marketing.pricing.plans.{$key}.tagline");
    $cta       = $plan['cta'] ?? 'start_trial';
    $price     = $plan['price'] ?? null;
    $features  = $plan['feature_keys'] ?? [];
    $isCustom  = $price === null;
    $highlight = (bool) ($plan['highlight'] ?? false);

    $ctaUrl = match ($cta) {
        'contact_sales' => route('marketing.contact', ['locale' => $locale]),
        'book_demo'     => route('marketing.book-demo', ['locale' => $locale]),
        default         => route('marketing.start-trial', ['locale' => $locale]),
    };
    $ctaLabel = match ($cta) {
        'contact_sales' => __('marketing.cta.contact_sales'),
        'book_demo'     => __('marketing.cta.book_demo'),
        default         => __('marketing.cta.start_trial'),
    };
@endphp
<div class="mkt-pricing-card {{ $highlight ? 'is-featured' : '' }}">
    @if($highlight)
        <span class="mkt-pricing-badge">{{ __('marketing.pricing.badge_popular') }}</span>
    @endif
    <h3 class="mkt-pricing-name">{{ $name }}</h3>
    <p class="mkt-pricing-tagline">{{ $tagline }}</p>

    @if($isCustom)
        <div class="mkt-pricing-price">
            <span class="num">{{ __('marketing.pricing.custom_price') }}</span>
        </div>
        <div class="mkt-pricing-period">{{ __('marketing.pricing.contact_for_quote') }}</div>
    @else
        <div class="mkt-pricing-price">
            <span class="num">{{ $price }}</span>
            <span class="currency">{{ $currency }}</span>
        </div>
        <div class="mkt-pricing-period">{{ __('marketing.pricing.per_month') }}</div>
    @endif

    <ul class="mkt-feature-list" style="margin-bottom:1.5rem;">
        @foreach($features as $featureKey)
            <li>{{ __("marketing.pricing.features.{$featureKey}") }}</li>
        @endforeach
    </ul>

    <div class="mkt-pricing-cta">
        <a href="{{ $ctaUrl }}" class="mkt-btn {{ $highlight ? 'mkt-btn-primary' : 'mkt-btn-outline' }}" style="width:100%;">
            {{ $ctaLabel }}
        </a>
    </div>
</div>
