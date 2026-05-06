@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper"><div class="content content-two">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div><h4 class="mb-1">{{ __('maintenance.documents.title') }}</h4><p class="mb-0 text-muted">{{ __('maintenance.documents.subtitle') }}</p></div>
            <a href="{{ route('automotive.admin.maintenance.index') }}" class="btn btn-outline-light">{{ __('tenant.back') }}</a>
        </div>

        <div class="row">
            <div class="col-xl-4 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.generate_document') }}</h5></div><div class="card-body">
                <form method="POST" action="{{ route('automotive.admin.maintenance.documents.generate') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">{{ __('maintenance.document_type') }}</label>
                        <select name="document_type" class="form-select" required>
                            <option value="maintenance_check_in">{{ __('maintenance.documents.check_in') }}</option>
                            <option value="maintenance_work_order">{{ __('maintenance.documents.work_order') }}</option>
                            <option value="maintenance_estimate">{{ __('maintenance.documents.estimate') }}</option>
                            <option value="maintenance_approval_certificate">{{ __('maintenance.documents.approval_certificate') }}</option>
                            <option value="maintenance_delivery_report">{{ __('maintenance.documents.delivery') }}</option>
                            <option value="maintenance_warranty_certificate">{{ __('maintenance.documents.warranty') }}</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('maintenance.entity_id') }}</label>
                        <input type="number" name="entity_id" class="form-control" required>
                        <div class="form-text">{{ __('maintenance.entity_id_hint') }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('maintenance.language') }}</label>
                        <select name="language" class="form-select"><option value="en">English</option><option value="ar">العربية</option></select>
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('maintenance.generate_document') }}</button>
                </form>
            </div></div></div>
            <div class="col-xl-8 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.entity_reference') }}</h5></div><div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6"><h6>{{ __('maintenance.check_ins') }}</h6>@foreach($checkIns as $item)<div class="small text-muted">{{ $item->id }} · {{ $item->check_in_number }} · {{ $item->customer?->name }}</div>@endforeach</div>
                    <div class="col-md-6"><h6>{{ __('maintenance.work_order') }}</h6>@foreach($workOrders as $item)<div class="small text-muted">{{ $item->id }} · {{ $item->work_order_number }} · {{ $item->customer?->name }}</div>@endforeach</div>
                    <div class="col-md-6"><h6>{{ __('maintenance.estimates') }}</h6>@foreach($estimates as $item)<div class="small text-muted">{{ $item->id }} · {{ $item->estimate_number }} · {{ $item->customer?->name }}</div>@endforeach</div>
                    <div class="col-md-6"><h6>{{ __('maintenance.approval_history') }}</h6>@foreach($approvalRecords as $item)<div class="small text-muted">{{ $item->id }} · {{ $item->estimate?->estimate_number }} · {{ $item->customer?->name }} · {{ strtoupper(str_replace('_', ' ', $item->status)) }}</div>@endforeach</div>
                    <div class="col-md-6"><h6>{{ __('maintenance.deliveries') }}</h6>@foreach($deliveries as $item)<div class="small text-muted">{{ $item->id }} · {{ $item->delivery_number }} · {{ $item->customer?->name }}</div>@endforeach</div>
                    <div class="col-md-6"><h6>{{ __('maintenance.warranties') }}</h6>@foreach($warranties as $item)<div class="small text-muted">{{ $item->id }} · {{ $item->warranty_number }} · {{ $item->customer?->name }}</div>@endforeach</div>
                </div>
            </div></div></div>
        </div>

        <div class="card"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.generated_documents') }}</h5></div><div class="card-body">
            @forelse($documents as $document)
                <div class="border-bottom pb-2 mb-2 d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1">{{ $document->document_number }} v{{ $document->version }}</h6>
                        <div class="text-muted small">{{ $document->document_type }} · {{ $document->document_title }} · {{ strtoupper($document->language) }} · {{ optional($document->generated_at)->format('Y-m-d H:i') }}</div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-primary">{{ strtoupper($document->status) }}</span>
                        @if($document->status === 'completed')
                            <div class="mt-2">
                                <a href="{{ route('automotive.admin.maintenance.documents.preview', $document) }}" class="btn btn-sm btn-outline-light" target="_blank">{{ __('maintenance.preview') }}</a>
                                <a href="{{ route('automotive.admin.maintenance.documents.download', $document) }}" class="btn btn-sm btn-outline-light">{{ __('maintenance.download') }}</a>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-muted mb-0">{{ __('maintenance.no_generated_documents') }}</p>
            @endforelse
        </div></div>
    </div></div>
@endsection
