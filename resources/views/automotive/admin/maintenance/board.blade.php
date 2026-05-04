@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper"><div class="content content-two">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div><h4 class="mb-1">{{ __('maintenance.workshop_board') }}</h4><p class="mb-0 text-muted">{{ __('maintenance.workshop_board_subtitle') }}</p></div>
            <a href="{{ route('automotive.admin.maintenance.index') }}" class="btn btn-outline-light">{{ __('tenant.back') }}</a>
        </div>

        <div class="row g-3">
            @foreach($columns as $column => $orders)
                <div class="col-xxl-3 col-xl-4 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h6 class="mb-0">{{ __('maintenance.board_columns.' . $column) }}</h6>
                            <span class="badge bg-light text-dark">{{ $orders->count() }}</span>
                        </div>
                        <div class="card-body">
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
