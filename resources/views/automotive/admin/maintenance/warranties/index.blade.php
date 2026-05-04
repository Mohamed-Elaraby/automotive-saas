@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper"><div class="content content-two">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div><h4 class="mb-1">{{ __('maintenance.warranties') }}</h4><p class="mb-0 text-muted">{{ __('maintenance.warranties_subtitle') }}</p></div>
            <a href="{{ route('automotive.admin.maintenance.index') }}" class="btn btn-outline-light">{{ __('tenant.back') }}</a>
        </div>

        <div class="row">
            <div class="col-xl-4 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.create_warranty') }}</h5></div><div class="card-body">
                <form method="POST" action="{{ route('automotive.admin.maintenance.warranties.store') }}">
                    @csrf
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.work_order') }}</label><select name="work_order_id" class="form-select"><option value="">{{ __('maintenance.none') }}</option>@foreach($workOrders as $workOrder)<option value="{{ $workOrder->id }}">{{ $workOrder->work_order_number }} · {{ $workOrder->customer?->name }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.service_catalog') }}</label><select name="service_catalog_item_id" class="form-select"><option value="">{{ __('maintenance.none') }}</option>@foreach($serviceItems as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.warranty_type') }}</label><select name="warranty_type" class="form-select">@foreach(['labor','parts','service_package','no_warranty'] as $type)<option value="{{ $type }}">{{ __('maintenance.warranty_types.' . $type) }}</option>@endforeach</select></div>
                    <div class="row"><div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.start_date') }}</label><input type="date" name="start_date" class="form-control" value="{{ now()->toDateString() }}"></div><div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.end_date') }}</label><input type="date" name="end_date" class="form-control"></div></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.mileage_limit') }}</label><input type="number" name="mileage_limit" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.terms') }}</label><textarea name="terms" class="form-control" rows="3"></textarea></div>
                    <button type="submit" class="btn btn-primary">{{ __('maintenance.create_warranty') }}</button>
                </form>
            </div></div></div>
            <div class="col-xl-8 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.active_warranties') }}</h5></div><div class="card-body">
                @forelse($warranties as $warranty)
                    <div class="border-bottom pb-2 mb-2"><h6 class="mb-1">{{ $warranty->warranty_number }}</h6><div class="text-muted small">{{ $warranty->vehicle?->plate_number }} · {{ $warranty->customer?->name }} · {{ __('maintenance.warranty_types.' . $warranty->warranty_type) }} · {{ optional($warranty->end_date)->format('Y-m-d') }}</div></div>
                @empty
                    <p class="text-muted mb-0">{{ __('maintenance.no_warranties') }}</p>
                @endforelse
            </div></div></div>
        </div>

        <div class="card"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.warranty_claims') }}</h5></div><div class="card-body">
            <form method="POST" action="{{ route('automotive.admin.maintenance.warranty-claims.store') }}" class="row g-2 mb-4">
                @csrf
                <div class="col-md-3"><select name="warranty_id" class="form-select"><option value="">{{ __('maintenance.warranty') }}</option>@foreach($warranties as $warranty)<option value="{{ $warranty->id }}">{{ $warranty->warranty_number }}</option>@endforeach</select></div>
                <div class="col-md-3"><select name="vehicle_id" class="form-select"><option value="">{{ __('maintenance.vehicle') }}</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}">{{ $vehicle->plate_number ?: $vehicle->vin_number }}</option>@endforeach</select></div>
                <div class="col-md-4"><input type="text" name="customer_complaint" class="form-control" placeholder="{{ __('maintenance.customer_complaint') }}"></div>
                <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">{{ __('tenant.save') }}</button></div>
            </form>
            @forelse($claims as $claim)
                <div class="border-bottom pb-2 mb-2"><strong>{{ $claim->claim_number }}</strong><div class="text-muted small">{{ $claim->vehicle?->plate_number }} · {{ strtoupper(str_replace('_', ' ', $claim->status)) }} · {{ $claim->customer_complaint }}</div></div>
            @empty
                <p class="text-muted mb-0">{{ __('maintenance.no_warranty_claims') }}</p>
            @endforelse
        </div></div>
    </div></div>
@endsection
