@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="mb-1">{{ __('maintenance.profiles.vehicle_360') }} · {{ $vehicle->make }} {{ $vehicle->model }}</h4>
                    <p class="mb-0 text-muted">{{ $vehicle->vehicle_number }} · {{ $vehicle->plate_number ?: __('maintenance.no_plate') }} · {{ $vehicle->customer?->name }}</p>
                </div>
                <div class="d-flex gap-2">
                    @if($vehicle->customer)
                        <a href="{{ route('automotive.admin.maintenance.customers.profile', $vehicle->customer) }}" class="btn btn-outline-light">{{ __('maintenance.profiles.open_customer_360') }}</a>
                    @endif
                    <a href="{{ route('automotive.admin.maintenance.index') }}" class="btn btn-outline-light">{{ __('tenant.back') }}</a>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">{{ __('maintenance.profiles.visits') }}</div><h4 class="mb-0">{{ $metrics['visits_count'] }}</h4></div></div></div>
                <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">{{ __('maintenance.profiles.open_work_orders') }}</div><h4 class="mb-0">{{ $metrics['open_work_orders_count'] }}</h4></div></div></div>
                <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">{{ __('maintenance.profiles.total_spend') }}</div><h4 class="mb-0">{{ number_format($metrics['total_spend'], 2) }}</h4></div></div></div>
                <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">{{ __('maintenance.profiles.health_score') }}</div><h4 class="mb-0">{{ $metrics['latest_health_score'] !== null ? $metrics['latest_health_score'].'/100' : __('maintenance.none') }}</h4></div></div></div>
            </div>

            <div class="row">
                <div class="col-xl-4 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.profiles.vehicle_details') }}</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-6"><div class="text-muted small">{{ __('maintenance.vin_number') }}</div><strong>{{ $vehicle->vin ?: __('maintenance.none') }}</strong></div>
                                <div class="col-6"><div class="text-muted small">{{ __('maintenance.odometer') }}</div><strong>{{ $vehicle->odometer ?: __('maintenance.none') }}</strong></div>
                                <div class="col-6"><div class="text-muted small">{{ __('maintenance.profiles.color') }}</div><div>{{ $vehicle->color ?: __('maintenance.none') }}</div></div>
                                <div class="col-6"><div class="text-muted small">{{ __('maintenance.profiles.trim') }}</div><div>{{ $vehicle->trim ?: __('maintenance.none') }}</div></div>
                                <div class="col-6"><div class="text-muted small">{{ __('maintenance.profiles.fuel_type') }}</div><div>{{ $vehicle->fuel_type ?: __('maintenance.none') }}</div></div>
                                <div class="col-6"><div class="text-muted small">{{ __('maintenance.profiles.transmission') }}</div><div>{{ $vehicle->transmission ?: __('maintenance.none') }}</div></div>
                                <div class="col-6"><div class="text-muted small">{{ __('maintenance.profiles.last_service') }}</div><div>{{ optional($metrics['last_service_at'])->format('Y-m-d') ?: __('maintenance.none') }}</div></div>
                                <div class="col-6"><div class="text-muted small">{{ __('maintenance.profiles.next_service_due') }}</div><div>{{ optional($vehicle->next_service_due_at)->format('Y-m-d') ?: __('maintenance.none') }}</div></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-8 d-flex">@include('automotive.admin.maintenance.profiles.partials.work-orders-list', ['title' => __('maintenance.profiles.work_order_history'), 'workOrders' => $work_orders])</div>
            </div>

            <div class="row">
                <div class="col-xl-6 d-flex">@include('automotive.admin.maintenance.profiles.partials.timeline-list', ['title' => __('maintenance.profiles.recent_visits'), 'items' => $recent_visits, 'numberField' => 'check_in_number', 'dateField' => 'checked_in_at'])</div>
                <div class="col-xl-6 d-flex">@include('automotive.admin.maintenance.profiles.partials.simple-list', ['title' => __('maintenance.inspections'), 'items' => $inspections, 'numberField' => 'inspection_number', 'subtitleField' => 'status'])</div>
            </div>

            <div class="row">
                <div class="col-xl-4 d-flex">@include('automotive.admin.maintenance.profiles.partials.simple-list', ['title' => __('maintenance.diagnosis'), 'items' => $diagnosis_records, 'numberField' => 'diagnosis_number', 'subtitleField' => 'priority'])</div>
                <div class="col-xl-4 d-flex">@include('automotive.admin.maintenance.profiles.partials.money-list', ['title' => __('maintenance.profiles.invoices'), 'items' => $invoices, 'numberField' => 'invoice_number', 'amountField' => 'grand_total'])</div>
                <div class="col-xl-4 d-flex">@include('automotive.admin.maintenance.profiles.partials.simple-list', ['title' => __('maintenance.warranties'), 'items' => $warranties, 'numberField' => 'warranty_number', 'subtitleField' => 'status'])</div>
            </div>

            <div class="row">
                <div class="col-xl-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.attachments') }}</h5></div>
                        <div class="card-body">
                            @forelse($attachments as $attachment)
                                <div class="border-bottom pb-2 mb-2 d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong>{{ __('maintenance.photo_categories.'.$attachment->category) }}</strong>
                                        <div class="text-muted small">{{ $attachment->original_name }} · {{ optional($attachment->captured_at)->format('Y-m-d H:i') }}</div>
                                    </div>
                                    <a href="{{ Storage::disk($attachment->file_disk)->url($attachment->file_path) }}" target="_blank" class="btn btn-sm btn-outline-light">{{ __('tenant.view') }}</a>
                                </div>
                            @empty
                                <p class="text-muted mb-0">{{ __('maintenance.no_attachments') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="col-xl-6 d-flex">@include('automotive.admin.maintenance.profiles.partials.simple-list', ['title' => __('maintenance.service_recommendations'), 'items' => $recommendations, 'numberField' => 'title', 'subtitleField' => 'priority'])</div>
            </div>
        </div>
    </div>
@endsection
