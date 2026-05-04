@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper"><div class="content content-two">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div><h4 class="mb-1">{{ __('maintenance.new_estimate') }}</h4><p class="mb-0 text-muted">{{ __('maintenance.new_estimate_subtitle') }}</p></div>
            <a href="{{ route('automotive.admin.maintenance.estimates.index') }}" class="btn btn-outline-light">{{ __('tenant.back') }}</a>
        </div>
        <form method="POST" action="{{ route('automotive.admin.maintenance.estimates.store') }}">
            @csrf
            <div class="card"><div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3"><label class="form-label">{{ __('tenant.branch') }}</label><select name="branch_id" class="form-select">@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>
                    <div class="col-md-3 mb-3"><label class="form-label">{{ __('tenant.customer') }}</label><select name="customer_id" class="form-select">@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}</option>@endforeach</select></div>
                    <div class="col-md-3 mb-3"><label class="form-label">{{ __('tenant.vehicle') }}</label><select name="vehicle_id" class="form-select">@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}">{{ $vehicle->make }} {{ $vehicle->model }}{{ $vehicle->plate_number ? ' · '.$vehicle->plate_number : '' }}</option>@endforeach</select></div>
                    <div class="col-md-3 mb-3"><label class="form-label">{{ __('maintenance.valid_until') }}</label><input type="date" name="valid_until" class="form-control" value="{{ now()->addDays(7)->toDateString() }}"></div>
                </div>
            </div></div>
            <div class="card"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.estimate_lines') }}</h5></div><div class="card-body">
                @for($i = 0; $i < 4; $i++)
                    <div class="row border-bottom mb-3 pb-2">
                        <div class="col-md-3 mb-2"><select name="lines[{{ $i }}][service_catalog_item_id]" class="form-select"><option value="">{{ __('maintenance.manual_line') }}</option>@foreach($serviceItems as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></div>
                        <div class="col-md-2 mb-2"><select name="lines[{{ $i }}][line_type]" class="form-select"><option value="labor">{{ __('maintenance.labor') }}</option><option value="part">{{ __('maintenance.part') }}</option><option value="package">{{ __('maintenance.package') }}</option><option value="other">{{ __('maintenance.other') }}</option></select></div>
                        <div class="col-md-3 mb-2"><input type="text" name="lines[{{ $i }}][description]" class="form-control" placeholder="{{ __('tenant.description') }}"></div>
                        <div class="col-md-1 mb-2"><input type="number" step="0.001" min="0.001" name="lines[{{ $i }}][quantity]" class="form-control" value="1"></div>
                        <div class="col-md-1 mb-2"><input type="number" step="0.01" min="0" name="lines[{{ $i }}][unit_price]" class="form-control" value="0"></div>
                        <div class="col-md-1 mb-2"><input type="number" step="0.01" min="0" name="lines[{{ $i }}][discount_amount]" class="form-control" value="0"></div>
                        <div class="col-md-1 mb-2"><input type="number" step="0.01" min="0" name="lines[{{ $i }}][tax_amount]" class="form-control" value="0"></div>
                    </div>
                @endfor
                <div class="mb-3"><label class="form-label">{{ __('maintenance.terms') }}</label><textarea name="terms" class="form-control" rows="3"></textarea></div>
                <div class="d-flex justify-content-end gap-2"><a href="{{ route('automotive.admin.maintenance.estimates.index') }}" class="btn btn-outline-light">{{ __('tenant.cancel') }}</a><button type="submit" class="btn btn-primary">{{ __('maintenance.save_estimate') }}</button></div>
            </div></div>
        </form>
    </div></div>
@endsection
