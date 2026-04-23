@php($page = 'journal-entry-show')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="mb-1">{{ $journalEntry->journal_number }}</h4>
                    <p class="mb-0 text-muted">{{ $journalEntry->memo ?: 'Journal entry detail' }}</p>
                </div>

                <div class="d-flex gap-2">
                    <a href="{{ route('automotive.admin.modules.general-ledger', $workspaceQuery) }}" class="btn btn-outline-light">
                        Back To General Ledger
                    </a>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-8 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header"><h5 class="card-title mb-0">Journal Entry Overview</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4"><div class="text-muted small mb-1">Status</div><span class="badge {{ $journalEntry->status === 'posted' ? 'bg-success' : 'bg-secondary' }}">{{ strtoupper($journalEntry->status) }}</span></div>
                                <div class="col-md-4"><div class="text-muted small mb-1">Entry Date</div><div>{{ optional($journalEntry->entry_date)->format('Y-m-d') ?: '-' }}</div></div>
                                <div class="col-md-4"><div class="text-muted small mb-1">Currency</div><div>{{ $journalEntry->currency }}</div></div>
                                <div class="col-md-4"><div class="text-muted small mb-1">Debit Total</div><div>{{ number_format((float) $journalEntry->debit_total, 2) }}</div></div>
                                <div class="col-md-4"><div class="text-muted small mb-1">Credit Total</div><div>{{ number_format((float) $journalEntry->credit_total, 2) }}</div></div>
                                <div class="col-md-4"><div class="text-muted small mb-1">Created By</div><div>{{ $journalEntry->creator?->name ?: 'System user' }}</div></div>
                                <div class="col-md-6"><div class="text-muted small mb-1">Posting Group</div><div>{{ $journalEntry->postingGroup?->name ?: 'Manual / default posting' }}</div></div>
                                <div class="col-md-6"><div class="text-muted small mb-1">Source</div><div>{{ $journalEntry->source_type ?: '-' }}{{ $journalEntry->source_id ? ' #'.$journalEntry->source_id : '' }}</div></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header"><h5 class="card-title mb-0">Journal Actions</h5></div>
                        <div class="card-body">
                            <div class="alert alert-light border">
                                <div class="fw-semibold mb-1">Accounting Access</div>
                                <div class="text-muted small">Role {{ str_replace('_', ' ', strtoupper($accountingPermissionSummary['role'] ?? 'legacy_full_access')) }} · {{ $accountingPermissionSummary['mode_label'] ?? 'Full Access' }}</div>
                            </div>
                            @if($journalEntry->status === 'pending_approval' && ($accountingPermissions['manual_journals_approve'] ?? true))
                                <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.journal-entries.approve', ['journalEntry' => $journalEntry->id] + $workspaceQuery) }}" class="mb-2">
                                    @csrf
                                    <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? 'accounting' }}">
                                    <textarea name="approval_notes" class="form-control mb-2" rows="2" placeholder="Approval notes"></textarea>
                                    <button type="submit" class="btn btn-success w-100">Approve Manual Journal</button>
                                </form>
                                <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.journal-entries.reject', ['journalEntry' => $journalEntry->id] + $workspaceQuery) }}">
                                    @csrf
                                    <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? 'accounting' }}">
                                    <button type="submit" class="btn btn-outline-danger w-100">Reject Manual Journal</button>
                                </form>
                            @elseif($journalEntry->status === 'approved' && ($accountingPermissions['manual_journals_post'] ?? true))
                                <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.journal-entries.post-approved', ['journalEntry' => $journalEntry->id] + $workspaceQuery) }}">
                                    @csrf
                                    <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? 'accounting' }}">
                                    <button type="submit" class="btn btn-primary w-100">Post Approved Journal</button>
                                </form>
                            @elseif($journalEntry->status === 'posted' && $journalEntry->source_type !== 'journal_reversal' && ($accountingPermissions['journals_reverse'] ?? true))
                                <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.journal-entries.reverse', ['journalEntry' => $journalEntry->id] + $workspaceQuery) }}">
                                    @csrf
                                    <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? 'accounting' }}">
                                    <button type="submit" class="btn btn-outline-danger w-100">Reverse Journal Entry</button>
                                </form>
                            @else
                                <p class="text-muted mb-0">No action is available for this journal entry under your current permissions and its current status.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            @include('automotive.admin.partials.workspace-integrations', [
                'title' => 'Connected Product Integrations',
                'columnClass' => 'col-xl-6',
            ])

            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Journal Lines</h5></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Account</th><th>Name</th><th>Memo</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead>
                            <tbody>
                            @foreach($journalEntry->lines as $line)
                                <tr>
                                    <td>{{ $line->account_code }}</td>
                                    <td>{{ $line->account_name ?: '-' }}</td>
                                    <td>{{ $line->memo ?: '-' }}</td>
                                    <td class="text-end">{{ number_format((float) $line->debit, 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $line->credit, 2) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @include('automotive.admin.components.page-footer')
        </div>
    </div>
@endsection
