@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper"><div class="content content-two">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div><h4 class="mb-1">{{ $inspection->inspection_number }}</h4><p class="mb-0 text-muted">{{ $inspection->vehicle?->make }} {{ $inspection->vehicle?->model }} · {{ $inspection->customer?->name }}</p></div>
            <a href="{{ route('automotive.admin.maintenance.inspections.index') }}" class="btn btn-outline-light">{{ __('tenant.back') }}</a>
        </div>

        <div class="row">
            <div class="col-xl-8 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.inspection_items') }}</h5></div><div class="card-body">
                <form method="POST" action="{{ route('automotive.admin.maintenance.inspections.items.update', $inspection) }}">
                    @csrf
                    @forelse($inspection->items as $item)
                        <div class="border-bottom pb-3 mb-3">
                            <div class="row g-2 align-items-start">
                                <div class="col-md-3"><h6 class="mb-1">{{ $item->label }}</h6><div class="text-muted small">{{ $item->section }}</div></div>
                                <div class="col-md-3"><select name="items[{{ $item->id }}][result]" class="form-select">@foreach(['good','needs_attention','urgent','not_checked','not_applicable'] as $result)<option value="{{ $result }}" @selected($item->result === $result)>{{ __('maintenance.results.' . $result) }}</option>@endforeach</select></div>
                                <div class="col-md-3"><input type="text" name="items[{{ $item->id }}][note]" class="form-control" value="{{ $item->note }}" placeholder="{{ __('maintenance.note') }}"></div>
                                <div class="col-md-3"><input type="number" step="0.01" name="items[{{ $item->id }}][estimated_cost]" class="form-control" value="{{ $item->estimated_cost }}" placeholder="{{ __('maintenance.estimated_cost') }}"></div>
                                <div class="col-12"><input type="text" name="items[{{ $item->id }}][recommendation]" class="form-control" value="{{ $item->recommendation }}" placeholder="{{ __('maintenance.recommendation') }}"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted">{{ __('maintenance.no_inspection_items') }}</p>
                    @endforelse
                    <button type="submit" class="btn btn-primary">{{ __('tenant.save') }}</button>
                </form>
            </div></div></div>
            <div class="col-xl-4 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.complete_inspection') }}</h5></div><div class="card-body">
                <form method="POST" action="{{ route('automotive.admin.maintenance.inspections.complete', $inspection) }}">
                    @csrf
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.summary') }}</label><textarea name="summary" class="form-control" rows="4">{{ $inspection->summary }}</textarea></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.customer_visible_notes') }}</label><textarea name="customer_visible_notes" class="form-control" rows="3">{{ $inspection->customer_visible_notes }}</textarea></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.internal_notes') }}</label><textarea name="internal_notes" class="form-control" rows="3">{{ $inspection->internal_notes }}</textarea></div>
                    <button type="submit" class="btn btn-success">{{ __('maintenance.complete_inspection') }}</button>
                </form>
            </div></div></div>
        </div>
    </div></div>
@endsection
