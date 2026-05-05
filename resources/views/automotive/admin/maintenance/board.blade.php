@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper"><div class="content content-two">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div><h4 class="mb-1">{{ __('maintenance.workshop_board') }}</h4><p class="mb-0 text-muted">{{ __('maintenance.workshop_board_subtitle') }}</p></div>
            <a href="{{ route('automotive.admin.maintenance.index') }}" class="btn btn-outline-light">{{ __('tenant.back') }}</a>
        </div>

        <div class="row g-3" id="workshopBoard" data-empty-label="{{ __('maintenance.no_board_items') }}" data-unassigned-label="{{ __('maintenance.unassigned') }}" data-no-plate-label="{{ __('maintenance.no_plate') }}">
            @foreach($columns as $column => $orders)
                <div class="col-xxl-3 col-xl-4 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h6 class="mb-0">{{ __('maintenance.board_columns.' . $column) }}</h6>
                            <span class="badge bg-light text-dark" data-board-count="{{ $column }}">{{ $orders->count() }}</span>
                        </div>
                        <div class="card-body" data-board-column="{{ $column }}">
                            @forelse($orders as $workOrder)
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="d-flex justify-content-between gap-2">
                                        <div>
                                            <h6 class="mb-1">{{ $workOrder->work_order_number }}</h6>
                                            <div class="text-muted small">{{ $workOrder->vehicle?->plate_number ?: __('maintenance.no_plate') }} · {{ $workOrder->vehicle?->make }} {{ $workOrder->vehicle?->model }}</div>
                                            <div class="text-muted small">{{ $workOrder->customer?->name }} · {{ $workOrder->branch?->name }}</div>
                                            <div class="text-muted small">{{ __('maintenance.technician') }}: {{ $workOrder->maintenanceJobs->pluck('technician.name')->filter()->unique()->implode(', ') ?: __('maintenance.unassigned') }}</div>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-primary">{{ strtoupper(str_replace('_', ' ', $workOrder->priority ?? 'normal')) }}</span>
                                            <div class="small text-muted mt-2">{{ strtoupper(str_replace('_', ' ', $workOrder->payment_status ?? 'unpaid')) }}</div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted mb-0">{{ __('maintenance.no_board_items') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div></div>
@endsection

@push('scripts')
    <script>
        (() => {
            const board = document.getElementById('workshopBoard');
            if (!board) return;

            const snapshotUrl = @json(route('automotive.admin.maintenance.board.snapshot'));
            const streamUrl = @json(route('automotive.admin.maintenance.notifications.stream'));
            const columnLabels = @json(collect(array_keys($columns))->mapWithKeys(fn ($column) => [$column => __('maintenance.board_columns.' . $column)]));
            let refreshTimer = null;

            const escapeHtml = value => String(value || '').replace(/[&<>"']/g, character => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            }[character]));

            const cardHtml = order => `
                <div class="border-bottom pb-2 mb-2" data-work-order-id="${order.id}">
                    <div class="d-flex justify-content-between gap-2">
                        <div>
                            <h6 class="mb-1">${escapeHtml(order.number)}</h6>
                            <div class="text-muted small">${escapeHtml(order.plate_number || board.dataset.noPlateLabel)} · ${escapeHtml(order.vehicle)}</div>
                            <div class="text-muted small">${escapeHtml(order.customer)} · ${escapeHtml(order.branch)}</div>
                            <div class="text-muted small">{{ __('maintenance.technician') }}: ${escapeHtml((order.technicians || []).join(', ') || board.dataset.unassignedLabel)}</div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-primary">${escapeHtml(String(order.priority || 'normal').replaceAll('_', ' ').toUpperCase())}</span>
                            <div class="small text-muted mt-2">${escapeHtml(String(order.payment_status || 'unpaid').replaceAll('_', ' ').toUpperCase())}</div>
                        </div>
                    </div>
                </div>
            `;

            const renderBoard = columns => {
                Object.entries(columns || {}).forEach(([column, orders]) => {
                    const body = document.querySelector(`[data-board-column="${column}"]`);
                    const counter = document.querySelector(`[data-board-count="${column}"]`);
                    if (!body) return;

                    counter && (counter.textContent = orders.length);
                    body.innerHTML = orders.length
                        ? orders.map(cardHtml).join('')
                        : `<p class="text-muted mb-0">${escapeHtml(board.dataset.emptyLabel)}</p>`;
                });
            };

            const refreshBoard = async () => {
                const response = await fetch(snapshotUrl, { headers: { 'Accept': 'application/json' } });
                const payload = await response.json();
                if (payload.ok) renderBoard(payload.columns);
            };

            const scheduleRefresh = () => {
                clearTimeout(refreshTimer);
                refreshTimer = setTimeout(refreshBoard, 300);
            };

            if (window.EventSource) {
                const source = new EventSource(streamUrl);
                ['work_order.status.changed', 'job.assigned', 'job.started', 'job.completed', 'qc.ready', 'qc.failed', 'qc.passed', 'vehicle.ready_for_delivery', 'vehicle.delivered'].forEach(eventType => {
                    source.addEventListener(eventType, scheduleRefresh);
                });
            }
        })();
    </script>
@endpush
