@php
    /** @var array $items  Each: ['q' => string, 'a' => string] */
    $items = $items ?? [];
    $jsonldId = $jsonldId ?? null;
@endphp
@if(!empty($items))
<div class="mkt-faq" @if($jsonldId) id="{{ $jsonldId }}" @endif>
    @foreach($items as $i => $item)
        <details @if($i === 0) open @endif>
            <summary>{{ $item['q'] }}</summary>
            <div class="mkt-faq-answer">{!! $item['a'] !!}</div>
        </details>
    @endforeach
</div>
@endif
