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

            @if(session('createdApiToken'))
                <div class="alert alert-warning">
                    <strong>{{ __('maintenance.integrations.api_token_plain') }}</strong>
                    <div class="mt-2"><code>{{ session('createdApiToken') }}</code></div>
                </div>
            @endif

            <div class="row">
                <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">{{ __('maintenance.integrations.parts_workspace') }}</div><h5 class="mb-0">{{ ($dashboard['parts_active'] ?? false) ? __('maintenance.connected') : __('maintenance.not_connected') }}</h5></div></div></div>
                <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">{{ __('maintenance.integrations.accounting_workspace') }}</div><h5 class="mb-0">{{ ($dashboard['accounting_active'] ?? false) ? __('maintenance.connected') : __('maintenance.not_connected') }}</h5></div></div></div>
                <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">{{ __('maintenance.integrations.open_parts_requests') }}</div><h4 class="mb-0">{{ $dashboard['open_parts_requests'] ?? 0 }}</h4></div></div></div>
                <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">{{ __('maintenance.integrations.pending_handoffs') }}</div><h4 class="mb-0">{{ $dashboard['pending_handoffs'] ?? 0 }}</h4><div class="text-muted small">{{ __('maintenance.integrations.active_api_tokens') }}: {{ $apiTokens->where('status', 'active')->count() }}</div></div></div></div>
            </div>

            <div class="row">
                <div class="col-xl-4 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.integrations.create_api_token') }}</h5></div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('automotive.admin.maintenance.integrations.api-tokens.store') }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">{{ __('maintenance.integrations.token_name') }}</label>
                                    <input name="token_name" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('maintenance.integrations.scopes') }}</label>
                                    @foreach($apiScopes as $scope)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="scopes[]" value="{{ $scope }}" id="scope-{{ $loop->index }}">
                                            <label class="form-check-label" for="scope-{{ $loop->index }}">{{ $scope }}</label>
                                        </div>
                                    @endforeach
                                </div>
                                <button type="submit" class="btn btn-primary w-100">{{ __('maintenance.integrations.create_api_token') }}</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-xl-8 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.integrations.api_tokens') }}</h5></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-nowrap">
                                    <thead><tr><th>{{ __('maintenance.name') }}</th><th>{{ __('maintenance.integrations.scopes') }}</th><th>{{ __('maintenance.status') }}</th><th>{{ __('maintenance.integrations.last_used') }}</th><th></th></tr></thead>
                                    <tbody>
                                    @forelse($apiTokens as $apiToken)
                                        <tr>
                                            <td><strong>{{ $apiToken->token_name }}</strong><div class="text-muted small">{{ $apiToken->creator?->name }}</div></td>
                                            <td><span class="text-muted small">{{ implode(', ', $apiToken->scopes ?: []) }}</span></td>
                                            <td><span class="badge bg-light text-dark">{{ strtoupper($apiToken->status) }}</span></td>
                                            <td>{{ optional($apiToken->last_used_at)->format('Y-m-d H:i') ?: '-' }}</td>
                                            <td class="text-end">
                                                @if($apiToken->status === 'active')
                                                    <form method="POST" action="{{ route('automotive.admin.maintenance.integrations.api-tokens.revoke', $apiToken) }}" class="d-inline">
                                                        @csrf
                                                        <button class="btn btn-sm btn-outline-danger">{{ __('maintenance.integrations.revoke') }}</button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-muted">{{ __('maintenance.integrations.no_api_tokens') }}</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-muted small mt-2">{{ __('maintenance.integrations.api_endpoint_hint') }}</div>
                        </div>
                    </div>
                </div>
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
                        <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.integrations.create_invoice') }}</h5></div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('automotive.admin.maintenance.integrations.invoices.store') }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">{{ __('maintenance.branch') }}</label>
                                    <select name="branch_id" class="form-select">
                                        <option value="">{{ __('maintenance.none') }}</option>
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
                                    <label class="form-label">{{ __('maintenance.estimates') }}</label>
                                    <select name="estimate_id" class="form-select">
                                        <option value="">{{ __('maintenance.integrations.select_estimate') }}</option>
                                        @foreach($estimates as $estimate)
                                            <option value="{{ $estimate->id }}">{{ $estimate->estimate_number }} · {{ $estimate->customer?->name }} · {{ number_format((float) $estimate->grand_total, 2) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3"><label class="form-label">{{ __('maintenance.issued_at') }}</label><input type="datetime-local" name="issued_at" class="form-control"></div>
                                <button type="submit" class="btn btn-primary w-100">{{ __('maintenance.integrations.create_invoice') }}</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-xl-7 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.integrations.accounting_sync') }}</h5></div>
                        <div class="card-body">
                            @forelse($invoices as $invoice)
                                <div class="border-bottom pb-3 mb-3">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                        <div>
                                        <strong>{{ $invoice->invoice_number }}</strong>
                                            <div class="text-muted small">{{ $invoice->customer?->name }} · {{ number_format((float) $invoice->paid_amount, 2) }}/{{ number_format((float) $invoice->grand_total, 2) }} · {{ strtoupper(str_replace('_', ' ', $invoice->payment_status)) }}</div>
                                        </div>
                                        <div class="text-end">
                                            <form method="POST" action="{{ route('automotive.admin.maintenance.integrations.invoices.sync', $invoice) }}" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-light">{{ __('maintenance.integrations.sync') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('automotive.admin.maintenance.integrations.invoices.documents.generate', $invoice) }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="language" value="{{ app()->getLocale() === 'ar' ? 'ar' : 'en' }}">
                                                <button class="btn btn-sm btn-outline-light">{{ __('maintenance.integrations.generate_invoice_pdf') }}</button>
                                            </form>
                                        </div>
                                    </div>

                                    @if($invoice->payment_status !== 'paid' && $invoice->payment_status !== 'cancelled')
                                        <form method="POST" action="{{ route('automotive.admin.maintenance.integrations.invoices.payment-requests.store', $invoice) }}" class="row g-2 mt-2">
                                            @csrf
                                            <div class="col-md-3"><input type="number" step="0.01" min="0.01" name="amount" class="form-control form-control-sm" placeholder="{{ __('maintenance.integrations.payment_request_amount') }}"></div>
                                            <div class="col-md-3"><input name="provider" class="form-control form-control-sm" placeholder="{{ __('maintenance.integrations.provider') }}" value="external"></div>
                                            <div class="col-md-3"><input type="datetime-local" name="expires_at" class="form-control form-control-sm"></div>
                                            <div class="col-md-3"><button class="btn btn-sm btn-outline-light w-100">{{ __('maintenance.integrations.create_payment_request') }}</button></div>
                                        </form>
                                        <form method="POST" action="{{ route('automotive.admin.maintenance.integrations.invoices.receipts.store', $invoice) }}" class="row g-2 mt-2">
                                        @csrf
                                            <div class="col-md-3"><input type="number" step="0.01" min="0.01" name="amount" class="form-control form-control-sm" placeholder="{{ __('maintenance.amount') }}" required></div>
                                            <div class="col-md-3">
                                                <select name="payment_method" class="form-select form-select-sm" required>
                                                    <option value="cash">Cash</option>
                                                    <option value="card">Card</option>
                                                    <option value="bank_transfer">Bank transfer</option>
                                                    <option value="online">Online</option>
                                                    <option value="cheque">Cheque</option>
                                                    <option value="other">Other</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3"><input name="reference_number" class="form-control form-control-sm" placeholder="{{ __('maintenance.reference_number') }}"></div>
                                            <div class="col-md-3"><button class="btn btn-sm btn-primary w-100">{{ __('maintenance.integrations.record_receipt') }}</button></div>
                                        </form>
                                    @endif
                                </div>
                            @empty
                                <p class="text-muted mb-0">{{ __('maintenance.integrations.no_invoices') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.integrations.payment_requests') }}</h5></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-nowrap">
                                    <thead><tr><th>{{ __('maintenance.document_number') }}</th><th>{{ __('maintenance.invoice') }}</th><th>{{ __('maintenance.customer') }}</th><th>{{ __('maintenance.amount') }}</th><th>{{ __('maintenance.status') }}</th><th>{{ __('maintenance.integrations.payment_url') }}</th><th></th></tr></thead>
                                    <tbody>
                                    @forelse($paymentRequests as $paymentRequest)
                                        <tr>
                                            <td><strong>{{ $paymentRequest->request_number }}</strong><div class="text-muted small">{{ $paymentRequest->provider }}</div></td>
                                            <td>{{ $paymentRequest->invoice?->invoice_number }}</td>
                                            <td>{{ $paymentRequest->customer?->name }}</td>
                                            <td>{{ number_format((float) $paymentRequest->amount, 2) }} {{ $paymentRequest->currency }}</td>
                                            <td><span class="badge bg-light text-dark">{{ strtoupper($paymentRequest->status) }}</span></td>
                                            <td><a href="{{ $paymentRequest->payment_url }}" target="_blank" rel="noopener">{{ __('maintenance.integrations.open_payment_link') }}</a></td>
                                            <td class="text-end">
                                                @if($paymentRequest->status === 'pending')
                                                    <form method="POST" action="{{ route('automotive.admin.maintenance.integrations.payment-requests.paid', $paymentRequest) }}" class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="reference_number" value="{{ $paymentRequest->request_number }}">
                                                        <button class="btn btn-sm btn-primary">{{ __('maintenance.integrations.mark_paid') }}</button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="text-muted">{{ __('maintenance.integrations.no_payment_requests') }}</td></tr>
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
                        <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.integrations.receipts') }}</h5></div>
                        <div class="card-body">
                            @forelse($receipts as $receipt)
                                <div class="border-bottom pb-2 mb-2 d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <strong>{{ $receipt->receipt_number }}</strong>
                                        <div class="text-muted small">{{ $receipt->invoice?->invoice_number }} · {{ $receipt->customer?->name }} · {{ number_format((float) $receipt->amount, 2) }} {{ $receipt->currency }}</div>
                                    </div>
                                    <form method="POST" action="{{ route('automotive.admin.maintenance.integrations.receipts.documents.generate', $receipt) }}">
                                        @csrf
                                        <input type="hidden" name="language" value="{{ app()->getLocale() === 'ar' ? 'ar' : 'en' }}">
                                        <button class="btn btn-sm btn-outline-light">{{ __('maintenance.integrations.generate_receipt_pdf') }}</button>
                                    </form>
                                </div>
                            @empty
                                <p class="text-muted mb-0">{{ __('maintenance.no_receipts') }}</p>
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
