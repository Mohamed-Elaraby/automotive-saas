@php
    /** @var array $items */
    /** @var string $locale */
    $items = $items ?? [];
@endphp
@if(!empty($items))
<nav class="mkt-breadcrumbs" aria-label="{{ __('marketing.a11y.breadcrumb') }}">
    <div class="mkt-container">
        <ol>
            @foreach($items as $index => $item)
                <li>
                    @if(!empty($item['url']) && $index !== array_key_last($items))
                        <a href="{{ $item['url'] }}">{{ $item['title'] }}</a>
                    @else
                        <span aria-current="page">{{ $item['title'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </div>
</nav>
@endif
