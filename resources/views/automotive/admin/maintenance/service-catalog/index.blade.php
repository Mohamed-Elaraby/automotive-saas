@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper"><div class="content content-two">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div><h4 class="mb-1">{{ __('maintenance.service_catalog') }}</h4><p class="mb-0 text-muted">{{ __('maintenance.service_catalog_subtitle') }}</p></div>
            <a href="{{ route('automotive.admin.maintenance.index') }}" class="btn btn-outline-light">{{ __('tenant.back') }}</a>
        </div>
        <div class="row">
            <div class="col-xl-4 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.add_service') }}</h5></div><div class="card-body">
                <form method="POST" action="{{ route('automotive.admin.maintenance.service-catalog.store') }}">
                    @csrf
                    <div class="mb-3"><label class="form-label">{{ __('tenant.name') }}</label><input type="text" name="name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.category') }}</label><input type="text" name="category" class="form-control"></div>
                    <div class="row"><div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.estimated_minutes') }}</label><input type="number" name="estimated_minutes" class="form-control" value="0"></div><div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.default_labor_price') }}</label><input type="number" step="0.01" name="default_labor_price" class="form-control" value="0"></div></div>
                    <div class="row"><div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.warranty_days') }}</label><input type="number" name="warranty_days" class="form-control" value="0"></div><div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.required_skill') }}</label><input type="text" name="required_skill" class="form-control"></div></div>
                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="is_taxable" value="1" checked><label class="form-check-label">{{ __('maintenance.taxable') }}</label></div>
                    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" checked><label class="form-check-label">{{ __('tenant.active') }}</label></div>
                    <button type="submit" class="btn btn-primary">{{ __('tenant.save') }}</button>
                </form>
            </div></div></div>
            <div class="col-xl-8 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.services') }}</h5></div><div class="card-body">
                @forelse($serviceItems as $item)
                    <div class="border-bottom pb-2 mb-2 d-flex justify-content-between align-items-start"><div><h6 class="mb-1">{{ $item->name }}</h6><div class="text-muted small">{{ $item->service_number }} · {{ $item->category ?: __('maintenance.no_category') }} · {{ $item->estimated_minutes }} {{ __('maintenance.minutes') }}</div></div><div class="text-end"><strong>{{ number_format((float) $item->default_labor_price, 2) }}</strong><div><span class="badge {{ $item->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $item->is_active ? __('tenant.active') : __('tenant.inactive') }}</span></div></div></div>
                @empty
                    <p class="text-muted mb-0">{{ __('maintenance.no_services') }}</p>
                @endforelse
            </div></div></div>
        </div>
    </div></div>
@endsection
