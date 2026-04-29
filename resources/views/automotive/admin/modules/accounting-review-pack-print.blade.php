<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('accounting.accountant_review_pack') }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
        h1, h2, h3 { margin: 0 0 12px; }
        .meta, .summary { margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-bottom: 20px; }
        .card { border: 1px solid #d1d5db; padding: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; font-size: 12px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; }
        .small { color: #6b7280; font-size: 12px; }
    </style>
</head>
<body>
    <h1>{{ __('accounting.accountant_review_pack') }}</h1>
    <div class="meta">
        <div><strong>{{ __('accounting.generated_at') }}:</strong> {{ optional($reviewPack['generated_at'] ?? null)->format('Y-m-d H:i') }}</div>
        <div><strong>{{ __('accounting.accounting_source_of_truth') }}:</strong> {{ str_replace('_', ' + ', $reviewPack['source_of_truth'] ?? 'journal_entries_and_journal_entry_lines') }}</div>
        <div class="small">{{ __('accounting.review_pack_print_hint') }}</div>
    </div>

    <div class="grid summary">
        <div class="card"><strong>{{ __('accounting.posted_journals') }}</strong><br>{{ number_format((int) data_get($reviewPack, 'summary.posted_journal_count', 0)) }}</div>
        <div class="card"><strong>{{ __('accounting.pending_manual_approvals') }}</strong><br>{{ number_format((int) data_get($reviewPack, 'summary.pending_manual_approval_count', 0)) }}</div>
        <div class="card"><strong>{{ __('accounting.audit_entries') }}</strong><br>{{ number_format((int) data_get($reviewPack, 'summary.audit_entry_count', 0)) }}</div>
        <div class="card"><strong>{{ __('accounting.net_income') }}</strong><br>{{ number_format((float) data_get($reviewPack, 'summary.net_income', 0), 2) }}</div>
    </div>

    <h2>{{ __('accounting.evidence_summary') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('accounting.section') }}</th>
                <th>{{ __('accounting.metric') }}</th>
                <th>{{ __('accounting.value') }}</th>
                <th>{{ __('accounting.evidence_source') }}</th>
                <th>{{ __('accounting.notes') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($reviewPack['evidence_rows'] ?? collect()) as $row)
                <tr>
                    <td>{{ $row['section'] }}</td>
                    <td>{{ $row['metric'] }}</td>
                    <td>{{ $row['value'] }}</td>
                    <td>{{ $row['evidence_source'] }}</td>
                    <td>{{ $row['notes'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>{{ __('accounting.recent_journals') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('accounting.journal_number') }}</th>
                <th>{{ __('accounting.entry_date') }}</th>
                <th>{{ __('accounting.status') }}</th>
                <th>{{ __('accounting.memo') }}</th>
                <th>{{ __('accounting.debit') }}</th>
                <th>{{ __('accounting.credit') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($reviewPack['recent_journals'] ?? collect()) as $journal)
                <tr>
                    <td>{{ $journal->journal_number }}</td>
                    <td>{{ optional($journal->entry_date)->format('Y-m-d') }}</td>
                    <td>{{ $journal->status }}</td>
                    <td>{{ $journal->memo }}</td>
                    <td>{{ number_format((float) $journal->debit_total, 2) }}</td>
                    <td>{{ number_format((float) $journal->credit_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>{{ __('accounting.recent_audit_entries') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('accounting.event_type') }}</th>
                <th>{{ __('accounting.description') }}</th>
                <th>{{ __('accounting.actor') }}</th>
                <th>{{ __('accounting.timestamp') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($reviewPack['recent_audits'] ?? collect()) as $audit)
                <tr>
                    <td>{{ $audit->event_type }}</td>
                    <td>{{ $audit->description }}</td>
                    <td>{{ $audit->actor?->name ?: __('accounting.system_user') }}</td>
                    <td>{{ optional($audit->created_at)->format('Y-m-d H:i') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
