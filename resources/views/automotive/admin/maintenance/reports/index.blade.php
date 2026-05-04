@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper"><div class="content content-two">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div><h4 class="mb-1">{{ __('maintenance.reports.title') }}</h4><p class="mb-0 text-muted">{{ __('maintenance.reports.subtitle') }}</p></div>
            <div class="d-flex gap-2">
                <a href="{{ route('automotive.admin.maintenance.reports.export', 'financial-summary') }}" class="btn btn-outline-light">{{ __('maintenance.export_financial') }}</a>
                <a href="{{ route('automotive.admin.maintenance.advanced.index') }}" class="btn btn-primary">{{ __('maintenance.advanced_operations') }}</a>
            </div>
        </div>

        <div class="row">
            @foreach([
                'revenue' => __('maintenance.report_metrics.revenue'),
                'open_work_orders' => __('maintenance.report_metrics.open_work_orders'),
                'vehicles_in_workshop' => __('maintenance.report_metrics.vehicles_in_workshop'),
                'pending_approvals' => __('maintenance.report_metrics.pending_approvals'),
                'pending_payments' => __('maintenance.report_metrics.pending_payments'),
                'qc_failures' => __('maintenance.report_metrics.qc_failures'),
                'complaints' => __('maintenance.report_metrics.complaints'),
                'warranty_claims' => __('maintenance.report_metrics.warranty_claims'),
            ] as $key => $label)
                <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">{{ $label }}</div><h4 class="mb-0">{{ is_float($dashboard[$key] ?? null) ? number_format($dashboard[$key], 2) : ($dashboard[$key] ?? 0) }}</h4></div></div></div>
            @endforeach
        </div>

        <div class="row">
            <div class="col-xl-6 d-flex"><div class="card flex-fill"><div class="card-header d-flex justify-content-between"><h5 class="card-title mb-0">{{ __('maintenance.technician_productivity') }}</h5><a href="{{ route('automotive.admin.maintenance.reports.export', 'technician-productivity') }}" class="btn btn-sm btn-outline-light">{{ __('maintenance.download') }}</a></div><div class="card-body">
                @forelse($technicians as $row)
                    <div class="border-bottom pb-2 mb-2 d-flex justify-content-between"><div><strong>{{ $row->technician?->name }}</strong><div class="text-muted small">{{ $row->completed_count }} / {{ $row->jobs_count }} {{ __('maintenance.jobs') }}</div></div><div class="text-end small">{{ round((float) $row->average_minutes, 1) }} {{ __('maintenance.minutes') }}<br>{{ $row->rework_count }} {{ __('maintenance.rework') }}</div></div>
                @empty
                    <p class="text-muted mb-0">{{ __('maintenance.no_report_data') }}</p>
                @endforelse
            </div></div></div>
            <div class="col-xl-6 d-flex"><div class="card flex-fill"><div class="card-header d-flex justify-content-between"><h5 class="card-title mb-0">{{ __('maintenance.branch_performance') }}</h5><a href="{{ route('automotive.admin.maintenance.reports.export', 'branch-performance') }}" class="btn btn-sm btn-outline-light">{{ __('maintenance.download') }}</a></div><div class="card-body">
                @forelse($branches as $row)
                    <div class="border-bottom pb-2 mb-2 d-flex justify-content-between"><div><strong>{{ $row->branch?->name }}</strong><div class="text-muted small">{{ $row->work_orders_count }} {{ __('maintenance.work_order') }}</div></div><div class="text-end">{{ $row->delivered_count }} {{ __('maintenance.delivered') }}</div></div>
                @empty
                    <p class="text-muted mb-0">{{ __('maintenance.no_report_data') }}</p>
                @endforelse
            </div></div></div>
        </div>

        <div class="row">
            <div class="col-xl-6 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.service_advisor_performance') }}</h5></div><div class="card-body">
                @forelse($advisors as $row)
                    <div class="border-bottom pb-2 mb-2 d-flex justify-content-between"><strong>{{ $row->serviceAdvisor?->name }}</strong><span>{{ $row->closed_count }} / {{ $row->work_orders_count }}</span></div>
                @empty
                    <p class="text-muted mb-0">{{ __('maintenance.no_report_data') }}</p>
                @endforelse
            </div></div></div>
            <div class="col-xl-6 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.top_services') }}</h5></div><div class="card-body">
                @forelse($topServices as $row)
                    <div class="border-bottom pb-2 mb-2 d-flex justify-content-between"><strong>{{ $row->serviceCatalogItem?->name }}</strong><span>{{ $row->jobs_count }}</span></div>
                @empty
                    <p class="text-muted mb-0">{{ __('maintenance.no_report_data') }}</p>
                @endforelse
            </div></div></div>
        </div>
    </div></div>
@endsection
