<div class="card flex-fill">
    <div class="card-header"><h5 class="card-title mb-0">{{ $title }}</h5></div>
    <div class="card-body">
        @forelse($workOrders as $workOrder)
            <div class="border-bottom pb-2 mb-2">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <strong>{{ $workOrder->work_order_number }}</strong>
                        <div class="text-muted small">{{ $workOrder->vehicle?->make }} {{ $workOrder->vehicle?->model }} · {{ $workOrder->branch?->name }}</div>
                        <div class="small">{{ $workOrder->title }}</div>
                    </div>
                    <span class="badge bg-light text-dark">{{ strtoupper(str_replace('_', ' ', $workOrder->status)) }}</span>
                </div>
            </div>
        @empty
            <p class="text-muted mb-0">{{ __('maintenance.profiles.no_records') }}</p>
        @endforelse
    </div>
</div>
