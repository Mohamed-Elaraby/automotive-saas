@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper"><div class="content content-two">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div><h4 class="mb-1">{{ $fleet->fleet_number }} · {{ $fleet->customer?->name }}</h4><p class="mb-0 text-muted">{{ __('maintenance.fleet.profile') }}</p></div>
            <div class="d-flex gap-2">
                <a href="{{ route('automotive.admin.maintenance.fleet.export.single', $fleet) }}" class="btn btn-outline-light">{{ __('maintenance.fleet.export') }}</a>
                <a href="{{ route('automotive.admin.maintenance.fleet.index') }}" class="btn btn-outline-light">{{ __('tenant.back') }}</a>
            </div>
        </div>

        <div class="row">
            @foreach(['vehicles_count' => __('maintenance.profiles.vehicles'), 'open_work_orders' => __('maintenance.report_metrics.open_work_orders'), 'invoice_total' => __('maintenance.report_metrics.revenue'), 'pending_total' => __('maintenance.report_metrics.pending_payments')] as $key => $label)
                <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">{{ $label }}</div><h4 class="mb-0">{{ is_numeric($summary[$key] ?? null) && $key !== 'vehicles_count' && $key !== 'open_work_orders' ? number_format((float) $summary[$key], 2) : ($summary[$key] ?? 0) }}</h4></div></div></div>
            @endforeach
        </div>

        <div class="row">
            <div class="col-xl-6 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.profiles.vehicles') }}</h5></div><div class="card-body">
                @forelse($vehicles as $vehicle)
                    <div class="border-bottom pb-2 mb-2 d-flex justify-content-between"><div><strong>{{ $vehicle->plate_number ?: $vehicle->vehicle_number }}</strong><div class="text-muted small">{{ $vehicle->make }} {{ $vehicle->model }} · {{ $vehicle->vin }}</div></div><a href="{{ route('automotive.admin.maintenance.vehicles.profile', $vehicle) }}" class="btn btn-sm btn-outline-light">{{ __('tenant.view') }}</a></div>
                @empty
                    <p class="text-muted mb-0">{{ __('maintenance.profiles.no_vehicles') }}</p>
                @endforelse
            </div></div></div>
            <div class="col-xl-6 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.profiles.work_order_history') }}</h5></div><div class="card-body">
                @forelse($workOrders as $workOrder)
                    <div class="border-bottom pb-2 mb-2 d-flex justify-content-between"><div><strong>{{ $workOrder->work_order_number }}</strong><div class="text-muted small">{{ $workOrder->vehicle?->plate_number }} · {{ strtoupper(str_replace('_', ' ', $workOrder->status)) }}</div></div><span class="text-muted small">{{ optional($workOrder->created_at)->format('Y-m-d') }}</span></div>
                @empty
                    <p class="text-muted mb-0">{{ __('maintenance.no_report_data') }}</p>
                @endforelse
            </div></div></div>
        </div>

        <div class="card"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.profiles.invoices') }}</h5></div><div class="card-body">
            @forelse($invoices as $invoice)
                <div class="border-bottom pb-2 mb-2 d-flex justify-content-between"><div><strong>{{ $invoice->invoice_number }}</strong><div class="text-muted small">{{ strtoupper(str_replace('_', ' ', $invoice->payment_status)) }}</div></div><div class="text-end">{{ number_format((float) $invoice->paid_amount, 2) }} / {{ number_format((float) $invoice->grand_total, 2) }}</div></div>
            @empty
                <p class="text-muted mb-0">{{ __('maintenance.integrations.no_invoices') }}</p>
            @endforelse
        </div></div>
    </div></div>
@endsection
