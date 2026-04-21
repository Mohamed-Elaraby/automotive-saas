<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Customer Statement</title>
    <style>
        body { color: #111827; font-family: Arial, sans-serif; margin: 32px; }
        h1 { font-size: 26px; margin: 0 0 6px; }
        .meta { color: #6b7280; font-size: 13px; margin-bottom: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #d1d5db; font-size: 13px; padding: 8px; text-align: left; }
        th { background: #f3f4f6; }
        .right { text-align: right; }
        .totals { margin-left: auto; margin-top: 16px; width: 360px; }
        @media print { body { margin: 12mm; } }
    </style>
</head>
<body>
    <h1>Customer Statement</h1>
    <div class="meta">{{ $statement['customer_name'] }} · Generated {{ now()->format('Y-m-d H:i') }}</div>

    <table>
        <thead><tr><th>Date</th><th>Type</th><th>Reference</th><th>Description</th><th class="right">Debit</th><th class="right">Credit</th><th class="right">Balance</th></tr></thead>
        <tbody>
            @forelse($statement['rows'] as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['type'] }}</td>
                    <td>{{ $row['reference'] }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td class="right">{{ number_format((float) $row['debit'], 2) }}</td>
                    <td class="right">{{ number_format((float) $row['credit'], 2) }}</td>
                    <td class="right">{{ number_format((float) $row['balance'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No statement rows available.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tbody>
            <tr><th>Total Debits</th><td class="right">{{ number_format((float) $statement['debit_total'], 2) }}</td></tr>
            <tr><th>Total Credits</th><td class="right">{{ number_format((float) $statement['credit_total'], 2) }}</td></tr>
            <tr><th>Open Balance</th><td class="right">{{ number_format((float) $statement['open_balance'], 2) }}</td></tr>
        </tbody>
    </table>
</body>
</html>
