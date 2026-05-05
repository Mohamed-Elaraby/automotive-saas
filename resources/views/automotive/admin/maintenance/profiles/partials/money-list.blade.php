<div class="card flex-fill">
    <div class="card-header"><h5 class="card-title mb-0">{{ $title }}</h5></div>
    <div class="card-body">
        @forelse($items as $item)
            <div class="border-bottom pb-2 mb-2 d-flex justify-content-between align-items-start">
                <div>
                    <strong>{{ $item->{$numberField} }}</strong>
                    <div class="text-muted small">{{ strtoupper(str_replace('_', ' ', $item->status ?? $item->payment_status ?? '')) }}</div>
                </div>
                <strong>{{ number_format((float) $item->{$amountField}, 2) }}</strong>
            </div>
        @empty
            <p class="text-muted mb-0">{{ __('maintenance.profiles.no_records') }}</p>
        @endforelse
    </div>
</div>
