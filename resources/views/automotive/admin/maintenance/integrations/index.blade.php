@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="mb-1">{{ __('maintenance.integrations.title') }}</h4>
                    <p class="mb-0 text-muted">{{ __('maintenance.integrations.subtitle') }}</p>
                </div>
                <a href="{{ route('automotive.admin.maintenance.index') }}" class="btn btn-outline-light">
                    <i class="isax isax-arrow-left me-1"></i>{{ __('tenant.back') }}
                </a>
            </div>

            <div class="row">
                <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">{{ __('maintenance.integrations.parts_workspace') }}</div><h5 class="mb-0">{{ ($dashboard['parts_active'] ?? false) ? __('maintenance.connected') : __('maintenance.not_connected') }}</h5></div></div></div>
                <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">{{ __('maintenance.integrations.accounting_workspace') }}</div><h5 class="mb-0">{{ ($dashboard['accounting_active'] ?? false) ? __('maintenance.connected') : __('maintenance.not_connected') }}</h5></div></div></div>
                <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">{{ __('maintenance.integrations.open_parts_requests') }}</div><h4 class="mb-0">{{ $dashboard['open_parts_requests'] ?? 0 }}</h4></div></div></div>
                <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">{{ __('maintenance.integrations.pending_handoffs') }}</div><h4 class="mb-0">{{ $dashboard['pending_handoffs'] ?? 0 }}</h4></div></div></div>
            </div>

            <div class="row">
                <div class="col-xl-4 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.integrations.create_parts_request') }}</h5></div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('automotive.admin.maintenance.integrations.parts-requests.store') }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">{{ __('maintenance.branch') }}</label>
                                    <select name="branch_id" class="form-select" required>
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('maintenance.work_order') }}</label>
                                    <select name="work_order_id" class="form-select">
                                        <option value="">{{ __('maintenance.select_work_order') }}</option>
                                        @foreach($workOrders as $workOrder)
                                            <option value="{{ $workOrder->id }}">{{ $workOrder->work_order_number }} · {{ $workOrder->customer?->name }} · {{ $workOrder->vehicle?->plate_number ?: __('maintenance.no_plate') }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('maintenance.technician_jobs') }}</label>
                                    <select name="job_id" class="form-select">
                                        <option value="">{{ __('maintenance.none') }}</option>
                                        @foreach($jobs as $job)
                                            <option value="{{ $job->id }}">{{ $job->job_number }} · {{ $job->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('maintenance.integrations.inventory_item') }}</label>
                                    <select name="product_id" class="form-select">
                                        <option value="">{{ __('maintenance.integrations.manual_part') }}</option>
                                        @foreach($stockItems as $item)
                                            <option value="{{ $item->id }}">{{ $item->name }} · {{ $item->sku }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3"><label class="form-label">{{ __('maintenance.integrations.part_name') }}</label><input name="part_name" class="form-control" required></div>
                                <div class="row">
                                    <div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.integrations.part_number') }}</label><input name="part_number" class="form-control"></div>
                                    <div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.quantity') }}</label><input name="quantity" type="number" step="0.001" min="0.001" value="1" class="form-control" required></div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.integrations.unit_price') }}</label><input name="unit_price" type="number" step="0.01" min="0" value="0" class="form-control"></div>
                                    <div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.integrations.needed_by') }}</label><input name="needed_by" type="date" class="form-control"></div>
                                </div>
                                <div class="mb-3"><label class="form-label">{{ __('maintenance.integrations.supplier_name') }}</label><input name="supplier_name" class="form-control"></div>
                                <div class="mb-3"><label class="form-label">{{ __('maintenance.note') }}</label><textarea name="notes" rows="2" class="form-control"></textarea></div>
                                <button class="btn btn-primary w-100" type="submit">{{ __('maintenance.integrations.request_parts') }}</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-xl-8 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.integrations.parts_requests') }}</h5></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-nowrap">
                                    <thead><tr><th>{{ __('maintenance.document_number') }}</th><th>{{ __('maintenance.work_order') }}</th><th>{{ __('maintenance.integrations.part_name') }}</th><th>{{ __('maintenance.status') }}</th><th>{{ __('maintenance.total') }}</th><th></th></tr></thead>
                                    <tbody>
                                    @forelse($partsRequests as $partsRequest)
                                        <tr>
                                            <td><strong>{{ $partsRequest->request_number }}</strong><div class="text-muted small">{{ $partsRequest->source }}</div></td>
                                            <td>{{ $partsRequest->workOrder?->work_order_number ?: '-' }}<div class="text-muted small">{{ $partsRequest->workOrder?->customer?->name }}</div></td>
                                            <td>{{ $partsRequest->part_name }}<div class="text-muted small">{{ $partsRequest->part_number }}</div></td>
                                            <td><span class="badge bg-light text-dark">{{ strtoupper(str_replace('_', ' ', $partsRequest->status)) }}</span></td>
                                            <td>{{ number_format((float) $partsRequest->total_price, 2) }}</td>
                                            <td class="text-end">
                                                @if($partsRequest->status === 'requested')
                                                    <form method="POST" action="{{ route('automotive.admin.maintenance.integrations.parts-requests.approve', $partsRequest) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-light">{{ __('maintenance.integrations.approve') }}</button></form>
                                                @endif
                                                @if(in_array($partsRequest->status, ['requested', 'approved', 'available'], true))
                                                    <form method="POST" action="{{ route('automotive.admin.maintenance.integrations.parts-requests.issue', $partsRequest) }}" class="d-inline">@csrf<button class="btn btn-sm btn-primary">{{ __('maintenance.integrations.issue') }}</button></form>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-muted">{{ __('maintenance.integrations.no_parts_requests') }}</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-5 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.integrations.accounting_sync') }}</h5></div>
                        <div class="card-body">
                            @forelse($invoices as $invoice)
                                <div class="border-bottom pb-2 mb-2 d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong>{{ $invoice->invoice_number }}</strong>
                                        <div class="text-muted small">{{ $invoice->customer?->name }} · {{ number_format((float) $invoice->grand_total, 2) }} · {{ strtoupper(str_replace('_', ' ', $invoice->payment_status)) }}</div>
                                    </div>
                                    <form method="POST" action="{{ route('automotive.admin.maintenance.integrations.invoices.sync', $invoice) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-light">{{ __('maintenance.integrations.sync') }}</button>
                                    </form>
                                </div>
                            @empty
                                <p class="text-muted mb-0">{{ __('maintenance.integrations.no_invoices') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="col-xl-7 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.integrations.handoffs') }}</h5></div>
                        <div class="card-body">
                            @forelse($handoffs as $handoff)
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="d-flex justify-content-between">
                                        <strong>{{ $handoff->event_name }}</strong>
                                        <span class="badge bg-light text-dark">{{ strtoupper($handoff->status) }}</span>
                                    </div>
                                    <div class="text-muted small">{{ $handoff->integration_key }} · {{ $handoff->source_type }} #{{ $handoff->source_id }} · {{ optional($handoff->last_attempted_at)->format('Y-m-d H:i') }}</div>
                                    @if($handoff->error_message)<div class="small text-danger">{{ $handoff->error_message }}</div>@endif
                                </div>
                            @empty
                                <p class="text-muted mb-0">{{ __('maintenance.integrations.no_handoffs') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
