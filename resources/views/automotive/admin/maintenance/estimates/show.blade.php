@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper"><div class="content content-two">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h4 class="mb-1">{{ $estimate->estimate_number }}</h4>
                <p class="mb-0 text-muted">{{ $estimate->customer?->name }} · {{ $estimate->vehicle?->make }} {{ $estimate->vehicle?->model }}</p>
            </div>
            <a href="{{ route('automotive.admin.maintenance.estimates.index') }}" class="btn btn-outline-light">{{ __('tenant.back') }}</a>
        </div>

        <div class="row">
            <div class="col-xl-8 d-flex">
                <div class="card flex-fill">
                    <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.estimate_lines') }}</h5></div>
                    <div class="card-body">
                        @foreach($estimate->lines as $line)
                            <div class="border-bottom pb-2 mb-2 d-flex justify-content-between gap-3">
                                <div>
                                    <h6 class="mb-1">{{ $line->description }}</h6>
                                    <div class="text-muted small">{{ strtoupper($line->line_type) }} · {{ $line->serviceCatalogItem?->service_number }} · {{ strtoupper(str_replace('_', ' ', $line->approval_status ?? 'pending')) }}</div>
                                    @if($line->notes)<div class="small">{{ $line->notes }}</div>@endif
                                </div>
                                <div class="text-end">
                                    <div>{{ rtrim(rtrim((string) $line->quantity, '0'), '.') }} × {{ number_format((float) $line->unit_price, 2) }}</div>
                                    <strong>{{ number_format((float) $line->total_price, 2) }}</strong>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="col-xl-4 d-flex">
                <div class="card flex-fill">
                    <div class="card-header"><h5 class="card-title mb-0">{{ __('tenant.summary') }}</h5></div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2"><span>{{ __('maintenance.status') }}</span><strong>{{ strtoupper(str_replace('_', ' ', $estimate->status)) }}</strong></div>
                        <div class="d-flex justify-content-between mb-2"><span>{{ __('maintenance.subtotal') }}</span><strong>{{ number_format((float) $estimate->subtotal, 2) }}</strong></div>
                        <div class="d-flex justify-content-between mb-2"><span>{{ __('maintenance.discount') }}</span><strong>{{ number_format((float) $estimate->discount_total, 2) }}</strong></div>
                        <div class="d-flex justify-content-between mb-2"><span>{{ __('maintenance.tax') }}</span><strong>{{ number_format((float) $estimate->tax_total, 2) }}</strong></div>
                        <hr>
                        <div class="d-flex justify-content-between"><span>{{ __('tenant.grand_total') }}</span><strong>{{ number_format((float) $estimate->grand_total, 2) }}</strong></div>
                        @if($estimate->approved_at)
                            <div class="alert alert-success mt-3 mb-0">{{ __('maintenance.approved_at', ['date' => $estimate->approved_at->format('Y-m-d H:i')]) }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-5 d-flex">
                <div class="card flex-fill">
                    <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.estimate_document_actions') }}</h5></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('automotive.admin.maintenance.estimates.documents.generate', $estimate) }}" class="d-flex gap-2 mb-3">
                            @csrf
                            <select name="language" class="form-select">
                                <option value="en">{{ __('maintenance.language') }}: English</option>
                                <option value="ar">{{ __('maintenance.language') }}: العربية</option>
                            </select>
                            <button type="submit" class="btn btn-primary flex-shrink-0">{{ __('maintenance.generate_estimate_pdf') }}</button>
                        </form>

                        <form method="POST" action="{{ route('automotive.admin.maintenance.estimates.documents.approval.generate', $estimate) }}" class="d-flex gap-2">
                            @csrf
                            <select name="language" class="form-select">
                                <option value="en">{{ __('maintenance.language') }}: English</option>
                                <option value="ar">{{ __('maintenance.language') }}: العربية</option>
                            </select>
                            <button type="submit" class="btn btn-outline-light flex-shrink-0" @disabled($estimate->approvals->isEmpty())>{{ __('maintenance.generate_approval_certificate') }}</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-xl-7 d-flex">
                <div class="card flex-fill">
                    <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.generated_documents') }}</h5></div>
                    <div class="card-body">
                        @forelse($generatedDocuments as $document)
                            <div class="border-bottom pb-2 mb-2">
                                <div class="d-flex justify-content-between gap-2 align-items-start">
                                    <div>
                                        <h6 class="mb-1">{{ $document->document_number }} v{{ $document->version }}</h6>
                                        <div class="small text-muted">{{ __('maintenance.documents.'.$document->document_type) }} · {{ strtoupper($document->language) }} · {{ optional($document->generated_at)->format('Y-m-d H:i') }}</div>
                                    </div>
                                    <div class="d-flex gap-1">
                                        <a href="{{ route('automotive.admin.maintenance.documents.preview', $document) }}" target="_blank" class="btn btn-sm btn-outline-light">{{ __('maintenance.preview') }}</a>
                                        <a href="{{ route('automotive.admin.maintenance.documents.download', $document) }}" class="btn btn-sm btn-outline-light">{{ __('maintenance.download') }}</a>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">{{ __('maintenance.no_generated_documents') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div></div>
@endsection
