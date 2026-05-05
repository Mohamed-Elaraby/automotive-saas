@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="mb-1">{{ __('maintenance.profiles.customer_360') }} · {{ $customer->name }}</h4>
                    <p class="mb-0 text-muted">{{ $customer->customer_number }} · {{ $customer->phone ?: __('maintenance.none') }} · {{ $customer->email ?: __('maintenance.none') }}</p>
                </div>
                <a href="{{ route('automotive.admin.maintenance.index') }}" class="btn btn-outline-light">
                    <i class="isax isax-arrow-left me-1"></i>{{ __('tenant.back') }}
                </a>
            </div>

            <div class="row">
                <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">{{ __('maintenance.profiles.vehicles') }}</div><h4 class="mb-0">{{ $metrics['vehicles_count'] }}</h4></div></div></div>
                <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">{{ __('maintenance.profiles.visits') }}</div><h4 class="mb-0">{{ $metrics['visits_count'] }}</h4></div></div></div>
                <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">{{ __('maintenance.profiles.total_spend') }}</div><h4 class="mb-0">{{ number_format($metrics['total_spend'], 2) }}</h4></div></div></div>
                <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">{{ __('maintenance.profiles.open_work_orders') }}</div><h4 class="mb-0">{{ $metrics['open_work_orders_count'] }}</h4></div></div></div>
            </div>

            <div class="row">
                <div class="col-xl-4 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.profiles.customer_details') }}</h5></div>
                        <div class="card-body">
                            <div class="mb-3"><div class="text-muted small">{{ __('maintenance.profiles.customer_type') }}</div><strong>{{ strtoupper($customer->customer_type ?: 'individual') }}</strong></div>
                            <div class="mb-3"><div class="text-muted small">{{ __('maintenance.profiles.company_name') }}</div><div>{{ $customer->company_name ?: __('maintenance.none') }}</div></div>
                            <div class="mb-3"><div class="text-muted small">{{ __('maintenance.profiles.tax_number') }}</div><div>{{ $customer->tax_number ?: __('maintenance.none') }}</div></div>
                            <div class="mb-0"><div class="text-muted small">{{ __('maintenance.internal_notes') }}</div><div>{{ $customer->internal_notes ?: __('maintenance.none') }}</div></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-8 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.profiles.vehicles') }}</h5></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-nowrap">
                                    <thead><tr><th>{{ __('maintenance.vehicle') }}</th><th>{{ __('maintenance.vin_number') }}</th><th>{{ __('maintenance.odometer') }}</th><th></th></tr></thead>
                                    <tbody>
                                    @forelse($vehicles as $vehicle)
                                        <tr>
                                            <td><strong>{{ $vehicle->make }} {{ $vehicle->model }}</strong><div class="text-muted small">{{ $vehicle->plate_number ?: __('maintenance.no_plate') }} · {{ $vehicle->year ?: __('maintenance.none') }}</div></td>
                                            <td>{{ $vehicle->vin ?: __('maintenance.none') }}</td>
                                            <td>{{ $vehicle->odometer ?: __('maintenance.none') }}</td>
                                            <td class="text-end"><a href="{{ route('automotive.admin.maintenance.vehicles.profile', $vehicle) }}" class="btn btn-sm btn-outline-light">{{ __('maintenance.profiles.open_vehicle_360') }}</a></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-muted">{{ __('maintenance.profiles.no_vehicles') }}</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-6 d-flex">@include('automotive.admin.maintenance.profiles.partials.timeline-list', ['title' => __('maintenance.profiles.recent_visits'), 'items' => $recent_visits, 'numberField' => 'check_in_number', 'dateField' => 'checked_in_at'])</div>
                <div class="col-xl-6 d-flex">@include('automotive.admin.maintenance.profiles.partials.timeline-list', ['title' => __('maintenance.profiles.upcoming_appointments'), 'items' => $upcoming_appointments, 'numberField' => 'appointment_number', 'dateField' => 'scheduled_at'])</div>
            </div>

            <div class="row">
                <div class="col-xl-12 d-flex">@include('automotive.admin.maintenance.profiles.partials.work-orders-list', ['title' => __('maintenance.profiles.open_work_orders'), 'workOrders' => $open_work_orders])</div>
            </div>

            <div class="row">
                <div class="col-xl-4 d-flex">@include('automotive.admin.maintenance.profiles.partials.money-list', ['title' => __('maintenance.estimates'), 'items' => $recent_estimates, 'numberField' => 'estimate_number', 'amountField' => 'grand_total'])</div>
                <div class="col-xl-4 d-flex">@include('automotive.admin.maintenance.profiles.partials.money-list', ['title' => __('maintenance.profiles.invoices'), 'items' => $recent_invoices, 'numberField' => 'invoice_number', 'amountField' => 'grand_total'])</div>
                <div class="col-xl-4 d-flex">@include('automotive.admin.maintenance.profiles.partials.simple-list', ['title' => __('maintenance.complaints'), 'items' => $complaints, 'numberField' => 'complaint_number', 'subtitleField' => 'status'])</div>
            </div>

            <div class="row">
                <div class="col-xl-12 d-flex">@include('automotive.admin.maintenance.profiles.partials.simple-list', ['title' => __('maintenance.profiles.active_warranties'), 'items' => $active_warranties, 'numberField' => 'warranty_number', 'subtitleField' => 'status'])</div>
            </div>
        </div>
    </div>
@endsection
