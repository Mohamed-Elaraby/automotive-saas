<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $statement['title'] }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #1f2937; margin: 32px; }
        h1, h2, h3 { margin: 0; }
        .muted { color: #6b7280; }
        .header { display: flex; justify-content: space-between; gap: 24px; border-bottom: 2px solid #111827; padding-bottom: 16px; margin-bottom: 24px; }
        .summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 24px; }
        .box { border: 1px solid #d1d5db; padding: 12px; }
        table { width: 100%; border-collapse: collapse; margin: 12px 0 24px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 8px; text-align: left; font-size: 13px; }
        th { background: #f3f4f6; }
        .right { text-align: right; }
        .section-title { margin-top: 18px; }
        @media print { body { margin: 18mm; } .no-print { display: none; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print</button>
    <div class="header">
        <div>
            <h1>{{ $statement['title'] }}</h1>
            <div class="muted">
                {{ data_get($statement, 'filters.date_from') ?: 'Beginning' }}
                to
                {{ data_get($statement, 'filters.date_to') ?: now()->toDateString() }}
            </div>
        </div>
        <div class="right">
            <strong>{{ now()->format('Y-m-d H:i') }}</strong>
            <div class="muted">Generated At</div>
        </div>
    </div>

    <div class="summary">
        @foreach($statement['summary'] as $label => $amount)
            <div class="box">
                <div class="muted">{{ $label }}</div>
                <h3>{{ number_format((float) $amount, 2) }}</h3>
            </div>
        @endforeach
    </div>

    @foreach($statement['sections'] as $section)
        <h2 class="section-title">{{ $section['label'] }}</h2>
        <table>
            <thead>
                <tr>
                    <th>Account Code</th>
                    <th>Account Name</th>
                    <th class="right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($section['rows'] as $row)
                    <tr>
                        <td>{{ $row->account_code }}</td>
                        <td>{{ $row->account_name }}</td>
                        <td class="right">{{ number_format((float) $row->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">No accounts in this section.</td></tr>
                @endforelse
                <tr>
                    <th colspan="2">{{ $section['label'] }} Total</th>
                    <th class="right">{{ number_format((float) $section['total'], 2) }}</th>
                </tr>
            </tbody>
        </table>
    @endforeach
</body>
</html>
