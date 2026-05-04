@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper"><div class="content content-two">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div><h4 class="mb-1">{{ __('maintenance.estimates') }}</h4><p class="mb-0 text-muted">{{ __('maintenance.estimates_subtitle') }}</p></div>
            <a href="{{ route('automotive.admin.maintenance.estimates.create') }}" class="btn btn-primary">{{ __('maintenance.new_estimate') }}</a>
        </div>
        <div class="card"><div class="card-body">
            @forelse($estimates as $estimate)
                <div class="border-bottom pb-2 mb-2 d-flex justify-content-between align-items-start"><div><h6 class="mb-1">{{ $estimate->estimate_number }}</h6><div class="text-muted small">{{ $estimate->customer?->name }} · {{ $estimate->vehicle?->make }} {{ $estimate->vehicle?->model }}</div><div class="text-muted small">{{ $estimate->branch?->name }} · {{ optional($estimate->valid_until)->format('Y-m-d') }}</div></div><div class="text-end"><span class="badge bg-info">{{ strtoupper($estimate->status) }}</span><div class="mt-1"><strong>{{ number_format((float) $estimate->grand_total, 2) }}</strong></div><a href="{{ route('automotive.admin.maintenance.estimates.show', $estimate) }}" class="btn btn-sm btn-outline-light mt-2">{{ __('tenant.view') }}</a></div></div>
            @empty
                <p class="text-muted mb-0">{{ __('maintenance.no_estimates') }}</p>
            @endforelse
        </div></div>
    </div></div>
@endsection
