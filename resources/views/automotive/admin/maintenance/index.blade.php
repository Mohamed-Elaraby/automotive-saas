@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="mb-1">{{ __('maintenance.title') }}</h4>
                    <p class="mb-0 text-muted">{{ __('maintenance.subtitle') }}</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('automotive.admin.maintenance.check-ins.create') }}" class="btn btn-primary">
                        <i class="isax isax-add-circle me-1"></i>{{ __('maintenance.new_check_in') }}
                    </a>
                    <a href="{{ route('automotive.admin.maintenance.estimates.create') }}" class="btn btn-outline-light">
                        <i class="isax isax-document-text me-1"></i>{{ __('maintenance.new_estimate') }}
                    </a>
                    <a href="{{ route('automotive.admin.maintenance.board') }}" class="btn btn-outline-light">
                        <i class="isax isax-grid-5 me-1"></i>{{ __('maintenance.workshop_board') }}
                    </a>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">{{ __('maintenance.today_check_ins') }}</div><h4 class="mb-1">{{ $dashboard['today_check_ins_count'] ?? 0 }}</h4><p class="mb-0 text-muted">{{ __('maintenance.today_check_ins_hint') }}</p></div></div></div>
                <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">{{ __('maintenance.open_check_ins') }}</div><h4 class="mb-1">{{ $dashboard['open_check_ins_count'] ?? 0 }}</h4><p class="mb-0 text-muted">{{ __('maintenance.open_check_ins_hint') }}</p></div></div></div>
                <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">{{ __('maintenance.service_catalog') }}</div><h4 class="mb-1">{{ $serviceItems->count() }}</h4><p class="mb-0 text-muted">{{ __('maintenance.service_catalog_hint') }}</p></div></div></div>
                <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">{{ __('maintenance.estimates') }}</div><h4 class="mb-1">{{ $estimates->count() }}</h4><p class="mb-0 text-muted">{{ __('maintenance.estimates_hint') }}</p></div></div></div>
            </div>

            <div class="row">
                <div class="col-xl-8 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h5 class="card-title mb-0">{{ __('maintenance.recent_check_ins') }}</h5>
                            <a href="{{ route('automotive.admin.maintenance.check-ins.index') }}" class="btn btn-sm btn-outline-light">{{ __('tenant.view') }}</a>
                        </div>
                        <div class="card-body">
                            @forelse($dashboard['recent_check_ins'] ?? collect() as $checkIn)
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">{{ $checkIn->check_in_number }}</h6>
                                            <div class="text-muted small">{{ $checkIn->customer?->name }} · {{ $checkIn->vehicle?->make }} {{ $checkIn->vehicle?->model }}{{ $checkIn->vehicle?->plate_number ? ' · '.$checkIn->vehicle?->plate_number : '' }}</div>
                                            <div class="text-muted small">{{ $checkIn->branch?->name }} · {{ optional($checkIn->checked_in_at)->format('Y-m-d H:i') }}</div>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-success">{{ strtoupper(str_replace('_', ' ', $checkIn->status)) }}</span>
                                            <div class="mt-2"><a href="{{ route('automotive.admin.maintenance.check-ins.show', $checkIn) }}" class="btn btn-sm btn-outline-light">{{ __('maintenance.open_check_in') }}</a></div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted mb-0">{{ __('maintenance.no_check_ins') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <h5 class="card-title mb-0">{{ __('maintenance.quick_links') }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="{{ route('automotive.admin.maintenance.check-ins.create') }}" class="btn btn-outline-light text-start"><i class="isax isax-login me-2"></i>{{ __('maintenance.new_check_in') }}</a>
                                <a href="{{ route('automotive.admin.maintenance.board') }}" class="btn btn-outline-light text-start"><i class="isax isax-grid-5 me-2"></i>{{ __('maintenance.workshop_board') }}</a>
                                <a href="{{ route('automotive.admin.maintenance.inspections.index') }}" class="btn btn-outline-light text-start"><i class="isax isax-clipboard-tick me-2"></i>{{ __('maintenance.inspections') }}</a>
                                <a href="{{ route('automotive.admin.maintenance.jobs.index') }}" class="btn btn-outline-light text-start"><i class="isax isax-user-tick me-2"></i>{{ __('maintenance.technician_jobs') }}</a>
                                <a href="{{ route('automotive.admin.maintenance.qc.index') }}" class="btn btn-outline-light text-start"><i class="isax isax-shield-tick me-2"></i>{{ __('maintenance.quality_control') }}</a>
                                <a href="{{ route('automotive.admin.maintenance.service-catalog.index') }}" class="btn btn-outline-light text-start"><i class="isax isax-note-2 me-2"></i>{{ __('maintenance.service_catalog') }}</a>
                                <a href="{{ route('automotive.admin.maintenance.estimates.index') }}" class="btn btn-outline-light text-start"><i class="isax isax-receipt-text me-2"></i>{{ __('maintenance.estimates') }}</a>
                                <a href="{{ route('automotive.admin.modules.workshop-work-orders') }}" class="btn btn-outline-light text-start"><i class="isax isax-note-text me-2"></i>{{ __('tenant.work_orders_table') }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
