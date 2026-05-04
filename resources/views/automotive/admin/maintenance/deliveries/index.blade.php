@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper"><div class="content content-two">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div><h4 class="mb-1">{{ __('maintenance.deliveries') }}</h4><p class="mb-0 text-muted">{{ __('maintenance.deliveries_subtitle') }}</p></div>
            <a href="{{ route('automotive.admin.maintenance.board') }}" class="btn btn-outline-light">{{ __('maintenance.workshop_board') }}</a>
        </div>

        <div class="row">
            <div class="col-xl-4 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.prepare_delivery') }}</h5></div><div class="card-body">
                <form method="POST" action="{{ route('automotive.admin.maintenance.deliveries.store') }}">
                    @csrf
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.work_order') }}</label><select name="work_order_id" class="form-select" required>@foreach($workOrders as $workOrder)<option value="{{ $workOrder->id }}">{{ $workOrder->work_order_number }} · {{ $workOrder->customer?->name }}</option>@endforeach</select></div>
                    @foreach(['customer_received_vehicle','personal_items_confirmed','old_parts_returned','invoice_explained','warranty_explained','next_service_explained'] as $item)
                        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="checklist[{{ $item }}]" value="1"><label class="form-check-label">{{ __('maintenance.delivery_checklist.' . $item) }}</label></div>
                    @endforeach
                    <button type="submit" class="btn btn-primary mt-2">{{ __('maintenance.prepare_delivery') }}</button>
                </form>
            </div></div></div>
            <div class="col-xl-8 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.delivery_records') }}</h5></div><div class="card-body">
                @forelse($deliveries as $delivery)
                    <div class="border-bottom pb-3 mb-3">
                        <div class="d-flex justify-content-between gap-2"><div><h6 class="mb-1">{{ $delivery->delivery_number }}</h6><div class="text-muted small">{{ $delivery->workOrder?->work_order_number }} · {{ $delivery->vehicle?->plate_number }} · {{ $delivery->customer?->name }}</div></div><span class="badge bg-primary">{{ strtoupper(str_replace('_', ' ', $delivery->status)) }}</span></div>
                        @if($delivery->status !== 'delivered')
                            <form method="POST" action="{{ route('automotive.admin.maintenance.deliveries.release', $delivery) }}" class="row g-2 mt-2">
                                @csrf
                                <div class="col-md-4"><select name="payment_status" class="form-select form-select-sm"><option value="paid">{{ __('maintenance.payment_statuses.paid') }}</option><option value="unpaid">{{ __('maintenance.payment_statuses.unpaid') }}</option><option value="partially_paid">{{ __('maintenance.payment_statuses.partially_paid') }}</option></select></div>
                                <div class="col-md-8"><input type="text" name="customer_visible_notes" class="form-control form-control-sm" placeholder="{{ __('maintenance.customer_visible_notes') }}"></div>
                                <div class="col-12"><button type="submit" class="btn btn-sm btn-success">{{ __('maintenance.release_vehicle') }}</button></div>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-muted mb-0">{{ __('maintenance.no_deliveries') }}</p>
                @endforelse
            </div></div></div>
        </div>
    </div></div>
@endsection
