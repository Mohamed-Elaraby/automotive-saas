@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper"><div class="content content-two">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div><h4 class="mb-1">{{ $estimate->estimate_number }}</h4><p class="mb-0 text-muted">{{ $estimate->customer?->name }} · {{ $estimate->vehicle?->make }} {{ $estimate->vehicle?->model }}</p></div>
            <a href="{{ route('automotive.admin.maintenance.estimates.index') }}" class="btn btn-outline-light">{{ __('tenant.back') }}</a>
        </div>
        <div class="row"><div class="col-xl-8 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.estimate_lines') }}</h5></div><div class="card-body">
            @foreach($estimate->lines as $line)
                <div class="border-bottom pb-2 mb-2 d-flex justify-content-between"><div><h6 class="mb-1">{{ $line->description }}</h6><div class="text-muted small">{{ strtoupper($line->line_type) }} · {{ $line->serviceCatalogItem?->service_number }}</div></div><div class="text-end"><div>{{ rtrim(rtrim((string) $line->quantity, '0'), '.') }} × {{ number_format((float) $line->unit_price, 2) }}</div><strong>{{ number_format((float) $line->total_price, 2) }}</strong></div></div>
            @endforeach
        </div></div></div><div class="col-xl-4 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('tenant.summary') }}</h5></div><div class="card-body">
            <div class="d-flex justify-content-between mb-2"><span>{{ __('maintenance.subtotal') }}</span><strong>{{ number_format((float) $estimate->subtotal, 2) }}</strong></div>
            <div class="d-flex justify-content-between mb-2"><span>{{ __('maintenance.discount') }}</span><strong>{{ number_format((float) $estimate->discount_total, 2) }}</strong></div>
            <div class="d-flex justify-content-between mb-2"><span>{{ __('maintenance.tax') }}</span><strong>{{ number_format((float) $estimate->tax_total, 2) }}</strong></div>
            <hr><div class="d-flex justify-content-between"><span>{{ __('tenant.grand_total') }}</span><strong>{{ number_format((float) $estimate->grand_total, 2) }}</strong></div>
        </div></div></div></div>
    </div></div>
@endsection
