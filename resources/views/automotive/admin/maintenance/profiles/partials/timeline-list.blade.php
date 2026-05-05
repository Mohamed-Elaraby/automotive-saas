<div class="card flex-fill">
    <div class="card-header"><h5 class="card-title mb-0">{{ $title }}</h5></div>
    <div class="card-body">
        @forelse($items as $item)
            <div class="border-bottom pb-2 mb-2">
                <strong>{{ $item->{$numberField} }}</strong>
                <div class="text-muted small">{{ optional($item->{$dateField})->format('Y-m-d H:i') ?: __('maintenance.none') }} · {{ $item->branch?->name }}</div>
                @if($item->customer_complaint)<div class="small">{{ $item->customer_complaint }}</div>@endif
            </div>
        @empty
            <p class="text-muted mb-0">{{ __('maintenance.profiles.no_records') }}</p>
        @endforelse
    </div>
</div>
