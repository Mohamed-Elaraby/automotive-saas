@php
    /** @var string $title */
    /** @var string $description */
    /** @var string|null $icon */
    $icon = $icon ?? '✦';
@endphp
<div class="mkt-card">
    <div class="mkt-card-icon" aria-hidden="true">{!! $icon !!}</div>
    <h3 class="mkt-card-title">{{ $title }}</h3>
    <p class="mkt-card-desc">{{ $description }}</p>
</div>
