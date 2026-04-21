@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h4 class="mb-1">Deposit Batch {{ $depositBatch->deposit_number }}</h4>
                    <div class="text-muted">{{ optional($depositBatch->deposit_date)->format('Y-m-d') }} · {{ $depositBatch->deposit_account }}</div>
                </div>
                <a href="{{ route('automotive.admin.modules.general-ledger', $workspaceQuery) }}" class="btn btn-outline-light">Back To General Ledger</a>
            </div>

            @include('automotive.admin.partials.workspace-integrations', ['workspaceIntegrations' => $workspaceIntegrations ?? collect(), 'workspaceQuery' => $workspaceQuery ?? []])

            <div class="row">
                <div class="col-xl-8 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header"><h5 class="card-title mb-0">Deposit Payments</h5></div>
                        <div class="card-body">
                            @forelse($depositBatch->payments as $payment)
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="d-flex justify-content-between gap-3">
                                        <div>
                                            <h6 class="mb-1">{{ $payment->payment_number }}</h6>
                                            <div class="text-muted small">{{ $payment->payer_name ?: 'Customer payment' }} · {{ ucfirst(str_replace('_', ' ', $payment->method)) }}</div>
                                            <div class="text-muted small">{{ $payment->reference ?: 'No reference' }} · {{ optional($payment->payment_date)->format('Y-m-d') }}</div>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge {{ $payment->reconciliation_status === 'deposited' ? 'bg-primary' : 'bg-warning text-dark' }}">{{ strtoupper($payment->reconciliation_status ?: 'pending') }}</span>
                                            <div class="fw-semibold">{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted mb-0">No payments are attached to this deposit batch.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header"><h5 class="card-title mb-0">Batch Status</h5></div>
                        <div class="card-body">
                            <div class="mb-3"><div class="text-muted small">Status</div><span class="badge {{ $depositBatch->status === 'posted' ? 'bg-success' : 'bg-secondary' }}">{{ strtoupper($depositBatch->status) }}</span></div>
                            <div class="mb-3"><div class="text-muted small">Total Amount</div><h5 class="mb-0">{{ number_format((float) $depositBatch->total_amount, 2) }} {{ $depositBatch->currency }}</h5></div>
                            <div class="mb-3"><div class="text-muted small">Reference</div><div>{{ $depositBatch->reference ?: '-' }}</div></div>
                            <div class="mb-3"><div class="text-muted small">Posted At</div><div>{{ optional($depositBatch->posted_at)->format('Y-m-d H:i') ?: '-' }}</div></div>
                            @if($depositBatch->status === 'corrected')
                                <div class="mb-3"><div class="text-muted small">Corrected At</div><div>{{ optional($depositBatch->corrected_at)->format('Y-m-d H:i') ?: '-' }}</div></div>
                                <div class="mb-3"><div class="text-muted small">Correction Reason</div><div>{{ $depositBatch->correction_reason ?: '-' }}</div></div>
                            @else
                                <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.deposit-batches.correct', ['depositBatch' => $depositBatch->id] + $workspaceQuery) }}">
                                    @csrf
                                    <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                                    <div class="mb-3"><label class="form-label">Correction Reason</label><textarea name="correction_reason" class="form-control" rows="3">{{ old('correction_reason') }}</textarea></div>
                                    <button type="submit" class="btn btn-outline-danger w-100">Correct Deposit Batch</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
