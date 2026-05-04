@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper"><div class="content content-two">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div><h4 class="mb-1">{{ __('maintenance.inspections') }}</h4><p class="mb-0 text-muted">{{ __('maintenance.inspections_subtitle') }}</p></div>
            <a href="{{ route('automotive.admin.maintenance.inspection-templates.index') }}" class="btn btn-outline-light">{{ __('maintenance.inspection_templates') }}</a>
        </div>

        <div class="row">
            <div class="col-xl-4 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.start_inspection') }}</h5></div><div class="card-body">
                <form method="POST" action="{{ route('automotive.admin.maintenance.inspections.store') }}">
                    @csrf
                    <div class="mb-3"><label class="form-label">{{ __('tenant.branch') }}</label><select name="branch_id" class="form-select" required>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.work_order') }}</label><select name="work_order_id" class="form-select"><option value="">{{ __('maintenance.none') }}</option>@foreach($workOrders as $workOrder)<option value="{{ $workOrder->id }}">{{ $workOrder->work_order_number }} · {{ $workOrder->customer?->name }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.vehicle') }}</label><select name="vehicle_id" class="form-select" required>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}">{{ $vehicle->plate_number ?: $vehicle->vin_number }} · {{ $vehicle->make }} {{ $vehicle->model }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.template') }}</label><select name="template_id" class="form-select"><option value="">{{ __('maintenance.none') }}</option>@foreach($templates as $template)<option value="{{ $template->id }}">{{ $template->name }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.inspection_type') }}</label><select name="inspection_type" class="form-select">@foreach(['initial','diagnostic','pre_repair','final','qc','delivery'] as $type)<option value="{{ $type }}">{{ __('maintenance.inspection_types.' . $type) }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.assignee') }}</label><select name="assigned_to" class="form-select"><option value="">{{ __('maintenance.unassigned') }}</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div>
                    <button type="submit" class="btn btn-primary">{{ __('maintenance.start_inspection') }}</button>
                </form>
            </div></div></div>
            <div class="col-xl-8 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.recent_inspections') }}</h5></div><div class="card-body">
                @forelse($inspections as $inspection)
                    <div class="border-bottom pb-2 mb-2 d-flex justify-content-between align-items-start">
                        <div><h6 class="mb-1">{{ $inspection->inspection_number }}</h6><div class="text-muted small">{{ $inspection->vehicle?->plate_number }} · {{ $inspection->vehicle?->make }} {{ $inspection->vehicle?->model }} · {{ $inspection->customer?->name }}</div><div class="text-muted small">{{ __('maintenance.inspection_types.' . $inspection->inspection_type) }} · {{ $inspection->assignee?->name ?: __('maintenance.unassigned') }}</div></div>
                        <div class="text-end"><span class="badge bg-primary">{{ strtoupper(str_replace('_', ' ', $inspection->status)) }}</span><div class="mt-2"><a href="{{ route('automotive.admin.maintenance.inspections.show', $inspection) }}" class="btn btn-sm btn-outline-light">{{ __('tenant.view') }}</a></div></div>
                    </div>
                @empty
                    <p class="text-muted mb-0">{{ __('maintenance.no_inspections') }}</p>
                @endforelse
            </div></div></div>
        </div>
    </div></div>
@endsection
