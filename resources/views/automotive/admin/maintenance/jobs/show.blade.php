@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper"><div class="content content-two">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div><h4 class="mb-1">{{ $job->job_number }} · {{ $job->title }}</h4><p class="mb-0 text-muted">{{ $job->workOrder?->work_order_number }} · {{ $job->workOrder?->vehicle?->make }} {{ $job->workOrder?->vehicle?->model }}</p></div>
            <a href="{{ route('automotive.admin.maintenance.jobs.index') }}" class="btn btn-outline-light">{{ __('tenant.back') }}</a>
        </div>

        <div class="row">
            <div class="col-xl-8 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.job_details') }}</h5></div><div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4"><div class="text-muted small">{{ __('maintenance.status') }}</div><strong>{{ strtoupper(str_replace('_', ' ', $job->status)) }}</strong></div>
                    <div class="col-md-4"><div class="text-muted small">{{ __('maintenance.technician') }}</div><strong>{{ $job->technician?->name ?: __('maintenance.unassigned') }}</strong></div>
                    <div class="col-md-4"><div class="text-muted small">{{ __('maintenance.qc_status') }}</div><strong>{{ strtoupper(str_replace('_', ' ', $job->qc_status)) }}</strong></div>
                </div>
                <p class="text-muted">{{ $job->description }}</p>
                <h6>{{ __('maintenance.time_logs') }}</h6>
                @forelse($job->timeLogs as $log)
                    <div class="border-bottom pb-2 mb-2"><strong>{{ strtoupper(str_replace('_', ' ', $log->action)) }}</strong><div class="text-muted small">{{ $log->technician?->name }} · {{ optional($log->started_at)->format('Y-m-d H:i') }} - {{ optional($log->ended_at)->format('Y-m-d H:i') }} · {{ $log->duration_minutes }} {{ __('maintenance.minutes') }}</div>@if($log->note)<div class="small">{{ $log->note }}</div>@endif</div>
                @empty
                    <p class="text-muted">{{ __('maintenance.no_time_logs') }}</p>
                @endforelse
            </div></div></div>
            <div class="col-xl-4 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.technician_actions') }}</h5></div><div class="card-body">
                <div class="d-grid gap-2 mb-3">
                    <form method="POST" action="{{ route('automotive.admin.maintenance.jobs.start', $job) }}">@csrf<button class="btn btn-success w-100" type="submit">{{ __('maintenance.start_job') }}</button></form>
                    <form method="POST" action="{{ route('automotive.admin.maintenance.jobs.resume', $job) }}">@csrf<button class="btn btn-outline-light w-100" type="submit">{{ __('maintenance.resume_job') }}</button></form>
                    <form method="POST" action="{{ route('automotive.admin.maintenance.jobs.complete', $job) }}">@csrf<div class="mb-2"><textarea name="note" class="form-control" rows="2" placeholder="{{ __('maintenance.completion_note') }}"></textarea></div><button class="btn btn-primary w-100" type="submit">{{ __('maintenance.complete_job') }}</button></form>
                </div>
                <form method="POST" action="{{ route('automotive.admin.maintenance.jobs.pause', $job) }}" class="mb-3">
                    @csrf
                    <textarea name="note" class="form-control mb-2" rows="2" placeholder="{{ __('maintenance.pause_note') }}"></textarea>
                    <button class="btn btn-warning w-100" type="submit">{{ __('maintenance.pause_job') }}</button>
                </form>
                <form method="POST" action="{{ route('automotive.admin.maintenance.jobs.blocker', $job) }}">
                    @csrf
                    <textarea name="note" class="form-control mb-2" rows="3" placeholder="{{ __('maintenance.blocker_note') }}" required></textarea>
                    <button class="btn btn-danger w-100" type="submit">{{ __('maintenance.mark_blocker') }}</button>
                </form>
            </div></div></div>
        </div>
    </div></div>
@endsection
