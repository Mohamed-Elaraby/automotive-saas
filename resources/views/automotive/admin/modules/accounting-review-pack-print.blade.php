<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Accountant Review Pack</title>
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
    <h1>Accountant Review Pack</h1>
    <div class="meta">
        <div><strong>Generated At:</strong> {{ optional($reviewPack['generated_at'] ?? null)->format('Y-m-d H:i') }}</div>
        <div><strong>Accounting Source Of Truth:</strong> {{ str_replace('_', ' + ', $reviewPack['source_of_truth'] ?? 'journal_entries_and_journal_entry_lines') }}</div>
        <div class="small">This pack is supporting evidence for review and signoff. Journals remain authoritative.</div>
    </div>

    <div class="grid summary">
        <div class="card"><strong>Posted Journals</strong><br>{{ number_format((int) data_get($reviewPack, 'summary.posted_journal_count', 0)) }}</div>
        <div class="card"><strong>Pending Manual Approvals</strong><br>{{ number_format((int) data_get($reviewPack, 'summary.pending_manual_approval_count', 0)) }}</div>
        <div class="card"><strong>Audit Entries</strong><br>{{ number_format((int) data_get($reviewPack, 'summary.audit_entry_count', 0)) }}</div>
        <div class="card"><strong>Net Income</strong><br>{{ number_format((float) data_get($reviewPack, 'summary.net_income', 0), 2) }}</div>
    </div>

    <h2>Evidence Summary</h2>
    <table>
        <thead>
            <tr>
                <th>Section</th>
                <th>Metric</th>
                <th>Value</th>
                <th>Evidence Source</th>
                <th>Notes</th>
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

    <h2>Recent Journals</h2>
    <table>
        <thead>
            <tr>
                <th>Journal Number</th>
                <th>Entry Date</th>
                <th>Status</th>
                <th>Memo</th>
                <th>Debit</th>
                <th>Credit</th>
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

    <h2>Recent Audit Entries</h2>
    <table>
        <thead>
            <tr>
                <th>Event Type</th>
                <th>Description</th>
                <th>Actor</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($reviewPack['recent_audits'] ?? collect()) as $audit)
                <tr>
                    <td>{{ $audit->event_type }}</td>
                    <td>{{ $audit->description }}</td>
                    <td>{{ $audit->actor?->name ?: 'System user' }}</td>
                    <td>{{ optional($audit->created_at)->format('Y-m-d H:i') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
