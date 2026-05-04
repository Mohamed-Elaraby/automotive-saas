@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="mb-1">{{ __('maintenance.check_ins') }}</h4>
                    <p class="mb-0 text-muted">{{ __('maintenance.check_ins_subtitle') }}</p>
                </div>
                <a href="{{ route('automotive.admin.maintenance.check-ins.create') }}" class="btn btn-primary">{{ __('maintenance.new_check_in') }}</a>
            </div>

            <div class="card">
                <div class="card-body">
                    @forelse($checkIns as $checkIn)
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
    </div>
@endsection
