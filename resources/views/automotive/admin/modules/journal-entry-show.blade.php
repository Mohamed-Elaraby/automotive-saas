@php($page = 'journal-entry-show')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="mb-1">{{ $journalEntry->journal_number }}</h4>
                    <p class="mb-0 text-muted">{{ $journalEntry->memo ?: __('accounting.journal_entry_detail') }}</p>
                </div>

                <div class="d-flex gap-2">
                    <a href="{{ route('automotive.admin.modules.general-ledger', $workspaceQuery) }}" class="btn btn-outline-light">
                        {{ __('accounting.back_to_general_ledger') }}
                    </a>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-8 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header"><h5 class="card-title mb-0">{{ __('accounting.journal_entry_overview') }}</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4"><div class="text-muted small mb-1">{{ __('accounting.status') }}</div><span class="badge {{ $journalEntry->status === 'posted' ? 'bg-success' : 'bg-secondary' }}">{{ strtoupper($journalEntry->status) }}</span></div>
                                <div class="col-md-4"><div class="text-muted small mb-1">{{ __('accounting.entry_date') }}</div><div>{{ optional($journalEntry->entry_date)->format('Y-m-d') ?: '-' }}</div></div>
                                <div class="col-md-4"><div class="text-muted small mb-1">{{ __('accounting.currency') }}</div><div>{{ $journalEntry->currency }}</div></div>
                                <div class="col-md-4"><div class="text-muted small mb-1">{{ __('accounting.debit_total') }}</div><div>{{ number_format((float) $journalEntry->debit_total, 2) }}</div></div>
                                <div class="col-md-4"><div class="text-muted small mb-1">{{ __('accounting.credit_total') }}</div><div>{{ number_format((float) $journalEntry->credit_total, 2) }}</div></div>
                                <div class="col-md-4"><div class="text-muted small mb-1">{{ __('accounting.created_by') }}</div><div>{{ $journalEntry->creator?->name ?: __('accounting.system_user') }}</div></div>
                                <div class="col-md-6"><div class="text-muted small mb-1">{{ __('accounting.posting_group') }}</div><div>{{ $journalEntry->postingGroup?->name ?: __('accounting.manual_default_posting') }}</div></div>
                                <div class="col-md-6"><div class="text-muted small mb-1">{{ __('accounting.source') }}</div><div>{{ $journalEntry->source_type ?: '-' }}{{ $journalEntry->source_id ? ' #'.$journalEntry->source_id : '' }}</div></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header"><h5 class="card-title mb-0">{{ __('accounting.journal_actions') }}</h5></div>
                        <div class="card-body">
                            <div class="alert alert-light border">
                                <div class="fw-semibold mb-1">{{ __('accounting.accounting_access') }}</div>
                                <div class="text-muted small">{{ __('accounting.role') }} {{ str_replace('_', ' ', strtoupper($accountingPermissionSummary['role'] ?? 'legacy_full_access')) }} · {{ $accountingPermissionSummary['mode_label'] ?? __('accounting.full_access') }}</div>
                            </div>
                            @if($journalEntry->status === 'pending_approval' && ($accountingPermissions['manual_journals_approve'] ?? true))
                                <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.journal-entries.approve', ['journalEntry' => $journalEntry->id] + $workspaceQuery) }}" class="mb-2">
                                    @csrf
                                    <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? 'accounting' }}">
                                    <textarea name="approval_notes" class="form-control mb-2" rows="2" placeholder="{{ __('accounting.approval_notes') }}"></textarea>
                                    <button type="submit" class="btn btn-success w-100">{{ __('accounting.approve_manual_journal') }}</button>
                                </form>
                                <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.journal-entries.reject', ['journalEntry' => $journalEntry->id] + $workspaceQuery) }}">
                                    @csrf
                                    <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? 'accounting' }}">
                                    <button type="submit" class="btn btn-outline-danger w-100">{{ __('accounting.reject_manual_journal') }}</button>
                                </form>
                            @elseif($journalEntry->status === 'approved' && ($accountingPermissions['manual_journals_post'] ?? true))
                                <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.journal-entries.post-approved', ['journalEntry' => $journalEntry->id] + $workspaceQuery) }}">
                                    @csrf
                                    <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? 'accounting' }}">
                                    <button type="submit" class="btn btn-primary w-100">{{ __('accounting.post_approved_journal') }}</button>
                                </form>
                            @elseif($journalEntry->status === 'posted' && $journalEntry->source_type !== 'journal_reversal' && ($accountingPermissions['journals_reverse'] ?? true))
                                <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.journal-entries.reverse', ['journalEntry' => $journalEntry->id] + $workspaceQuery) }}">
                                    @csrf
                                    <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? 'accounting' }}">
                                    <button type="submit" class="btn btn-outline-danger w-100">{{ __('accounting.reverse_journal_entry') }}</button>
                                </form>
                            @else
                                <p class="text-muted mb-0">{{ __('accounting.no_journal_action') }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            @include('automotive.admin.partials.workspace-integrations', [
                'title' => __('accounting.connected_product_integrations'),
                'columnClass' => 'col-xl-6',
            ])

            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('accounting.journal_lines') }}</h5></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('accounting.account') }}</th><th>{{ __('accounting.name') }}</th><th>{{ __('accounting.memo') }}</th><th class="text-end">{{ __('accounting.debit') }}</th><th class="text-end">{{ __('accounting.credit') }}</th></tr></thead>
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
