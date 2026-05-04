@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper"><div class="content content-two">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div><h4 class="mb-1">{{ __('maintenance.diagnosis') }}</h4><p class="mb-0 text-muted">{{ __('maintenance.diagnosis_subtitle') }}</p></div>
            <a href="{{ route('automotive.admin.maintenance.index') }}" class="btn btn-outline-light">{{ __('tenant.back') }}</a>
        </div>

        <div class="row">
            <div class="col-xl-4 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.record_diagnosis') }}</h5></div><div class="card-body">
                <form method="POST" action="{{ route('automotive.admin.maintenance.diagnosis.store') }}">
                    @csrf
                    <div class="mb-3"><label class="form-label">{{ __('tenant.branch') }}</label><select name="branch_id" class="form-select" required>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.work_order') }}</label><select name="work_order_id" class="form-select"><option value="">{{ __('maintenance.none') }}</option>@foreach($workOrders as $workOrder)<option value="{{ $workOrder->id }}">{{ $workOrder->work_order_number }} · {{ $workOrder->customer?->name }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.vehicle') }}</label><select name="vehicle_id" class="form-select" required>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}">{{ $vehicle->plate_number ?: $vehicle->vin_number }} · {{ $vehicle->make }} {{ $vehicle->model }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.technician') }}</label><select name="diagnosed_by" class="form-select"><option value="">{{ __('maintenance.unassigned') }}</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.complaint') }}</label><textarea name="complaint" class="form-control" rows="2"></textarea></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.fault_codes') }}</label><input type="text" name="fault_codes" class="form-control" placeholder="{{ __('maintenance.comma_separated') }}"></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.root_cause') }}</label><textarea name="root_cause" class="form-control" rows="2"></textarea></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.recommended_repair') }}</label><textarea name="recommended_repair" class="form-control" rows="2"></textarea></div>
                    <div class="row"><div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.priority') }}</label><select name="priority" class="form-select">@foreach(['normal','low','high','urgent'] as $priority)<option value="{{ $priority }}">{{ __('maintenance.priorities.' . $priority) }}</option>@endforeach</select></div><div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.estimated_minutes') }}</label><input type="number" name="estimated_minutes" class="form-control"></div></div>
                    <button type="submit" class="btn btn-primary">{{ __('tenant.save') }}</button>
                </form>
            </div></div></div>
            <div class="col-xl-8 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.diagnosis_records') }}</h5></div><div class="card-body">
                @forelse($diagnosisRecords as $record)
                    <div class="border-bottom pb-2 mb-2"><h6 class="mb-1">{{ $record->diagnosis_number }} · {{ $record->vehicle?->plate_number }}</h6><div class="text-muted small">{{ $record->workOrder?->work_order_number }} · {{ $record->technician?->name ?: __('maintenance.unassigned') }} · {{ __('maintenance.priorities.' . $record->priority) }}</div><div class="small mt-1">{{ $record->root_cause }}</div></div>
                @empty
                    <p class="text-muted mb-0">{{ __('maintenance.no_diagnosis') }}</p>
                @endforelse
            </div></div></div>
        </div>
    </div></div>
@endsection
