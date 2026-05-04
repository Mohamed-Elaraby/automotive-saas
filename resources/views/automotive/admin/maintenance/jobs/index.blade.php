@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper"><div class="content content-two">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div><h4 class="mb-1">{{ __('maintenance.technician_jobs') }}</h4><p class="mb-0 text-muted">{{ __('maintenance.technician_jobs_subtitle') }}</p></div>
            <a href="{{ route('automotive.admin.maintenance.board') }}" class="btn btn-outline-light">{{ __('maintenance.workshop_board') }}</a>
        </div>

        <div class="row">
            <div class="col-xl-4 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.create_job') }}</h5></div><div class="card-body">
                <form method="POST" action="{{ route('automotive.admin.maintenance.jobs.store') }}">
                    @csrf
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.work_order') }}</label><select name="work_order_id" class="form-select" required>@foreach($workOrders as $workOrder)<option value="{{ $workOrder->id }}">{{ $workOrder->work_order_number }} · {{ $workOrder->customer?->name }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.service_catalog') }}</label><select name="service_catalog_item_id" class="form-select"><option value="">{{ __('maintenance.manual_line') }}</option>@foreach($serviceItems as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.job_title') }}</label><input type="text" name="title" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.technician') }}</label><select name="assigned_technician_id" class="form-select"><option value="">{{ __('maintenance.unassigned') }}</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div>
                    <div class="row"><div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.priority') }}</label><select name="priority" class="form-select">@foreach(['normal','low','high','urgent'] as $priority)<option value="{{ $priority }}">{{ __('maintenance.priorities.' . $priority) }}</option>@endforeach</select></div><div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.estimated_minutes') }}</label><input type="number" name="estimated_minutes" class="form-control" value="0"></div></div>
                    <div class="mb-3"><label class="form-label">{{ __('tenant.description') }}</label><textarea name="description" class="form-control" rows="3"></textarea></div>
                    <button type="submit" class="btn btn-primary">{{ __('maintenance.create_job') }}</button>
                </form>
            </div></div></div>
            <div class="col-xl-8 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.recent_jobs') }}</h5></div><div class="card-body">
                @forelse($jobs as $job)
                    <div class="border-bottom pb-2 mb-2 d-flex justify-content-between align-items-start">
                        <div><h6 class="mb-1">{{ $job->job_number }} · {{ $job->title }}</h6><div class="text-muted small">{{ $job->workOrder?->work_order_number }} · {{ $job->workOrder?->vehicle?->plate_number }} · {{ $job->workOrder?->customer?->name }}</div><div class="text-muted small">{{ __('maintenance.technician') }}: {{ $job->technician?->name ?: __('maintenance.unassigned') }} · {{ $job->actual_minutes }} / {{ $job->estimated_minutes }} {{ __('maintenance.minutes') }}</div></div>
                        <div class="text-end"><span class="badge bg-primary">{{ strtoupper(str_replace('_', ' ', $job->status)) }}</span><div class="mt-2"><a href="{{ route('automotive.admin.maintenance.jobs.show', $job) }}" class="btn btn-sm btn-outline-light">{{ __('tenant.view') }}</a></div></div>
                    </div>
                @empty
                    <p class="text-muted mb-0">{{ __('maintenance.no_jobs') }}</p>
                @endforelse
            </div></div></div>
        </div>
    </div></div>
@endsection
