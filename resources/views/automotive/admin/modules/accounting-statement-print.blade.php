<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('accounting.customer_statement') }}</title>
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
    <h1>{{ __('accounting.customer_statement') }}</h1>
    <div class="meta">{{ $statement['customer_name'] }} · {{ __('accounting.generated') }} {{ now()->format('Y-m-d H:i') }}</div>

    <table>
        <thead><tr><th>{{ __('accounting.date') }}</th><th>{{ __('accounting.type') }}</th><th>{{ __('accounting.reference') }}</th><th>{{ __('accounting.description') }}</th><th class="right">{{ __('accounting.debit') }}</th><th class="right">{{ __('accounting.credit') }}</th><th class="right">{{ __('accounting.balance') }}</th></tr></thead>
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
                <tr><td colspan="7">{{ __('accounting.no_statement_rows') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tbody>
            <tr><th>{{ __('accounting.total_debits') }}</th><td class="right">{{ number_format((float) $statement['debit_total'], 2) }}</td></tr>
            <tr><th>{{ __('accounting.total_credits') }}</th><td class="right">{{ number_format((float) $statement['credit_total'], 2) }}</td></tr>
            <tr><th>{{ __('accounting.open_balance') }}</th><td class="right">{{ number_format((float) $statement['open_balance'], 2) }}</td></tr>
        </tbody>
    </table>
</body>
</html>
