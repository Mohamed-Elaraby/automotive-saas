@php
    /** @var string $locale */
    /** @var string|null $variant */
    $locale  = $locale ?? app()->getLocale();
    $variant = $variant ?? 'global';
    $title   = __("marketing.cta_section.{$variant}.title");
    $body    = __("marketing.cta_section.{$variant}.body");

    if ($title === "marketing.cta_section.{$variant}.title") {
        $title = __('marketing.cta_section.global.title');
        $body  = __('marketing.cta_section.global.body');
    }
@endphp
<section class="mkt-container">
    <div class="mkt-cta">
        <h2>{{ $title }}</h2>
        <p>{{ $body }}</p>
        <div class="mkt-hero-actions" style="justify-content:center;">
            <a href="{{ route('marketing.start-trial', ['locale' => $locale]) }}" class="mkt-btn mkt-btn-primary mkt-btn-lg">
                {{ __('marketing.cta.start_trial') }}
            </a>
            <a href="{{ route('marketing.book-demo', ['locale' => $locale]) }}" class="mkt-btn mkt-btn-outline mkt-btn-lg">
                {{ __('marketing.cta.book_demo') }}
            </a>
            <a href="{{ route('marketing.contact', ['locale' => $locale]) }}" class="mkt-btn mkt-btn-outline mkt-btn-lg">
                {{ __('marketing.cta.contact_sales') }}
            </a>
        </div>
    </div>
</section>
