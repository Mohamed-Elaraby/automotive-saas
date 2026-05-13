@php
    /** @var string $title */
    /** @var string $description */
    /** @var string $icon */
    /** @var string $href */
    /** @var string|null $linkText */
    $linkText = $linkText ?? __('marketing.cta.learn_more');
@endphp
<a href="{{ $href }}" class="mkt-card">
    <div class="mkt-card-icon" aria-hidden="true">{!! $icon ?? '★' !!}</div>
    <h3 class="mkt-card-title">{{ $title }}</h3>
    <p class="mkt-card-desc">{{ $description }}</p>
    <span class="mkt-card-link">
        {{ $linkText }}
        <span aria-hidden="true">→</span>
    </span>
</a>
