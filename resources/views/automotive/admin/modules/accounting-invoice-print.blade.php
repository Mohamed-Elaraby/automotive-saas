<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document['invoice_number'] }}</title>
    <style>
        body { color: #111827; font-family: Arial, sans-serif; margin: 32px; }
        h1 { font-size: 26px; margin: 0 0 6px; }
        h2 { font-size: 16px; margin: 24px 0 8px; }
        .meta, .muted { color: #6b7280; font-size: 13px; }
        .grid { display: grid; gap: 16px; grid-template-columns: 1fr 1fr; margin-top: 20px; }
        table { border-collapse: collapse; margin-top: 16px; width: 100%; }
        th, td { border: 1px solid #d1d5db; font-size: 13px; padding: 8px; text-align: left; }
        th { background: #f3f4f6; }
        .right { text-align: right; }
        .totals { margin-left: auto; margin-top: 16px; width: 320px; }
        @media print { body { margin: 12mm; } }
    </style>
</head>
<body>
    <h1>{{ __('accounting.invoice') }} {{ $document['invoice_number'] }}</h1>
    <div class="meta">{{ __('accounting.generated') }} {{ now()->format('Y-m-d H:i') }}</div>

    <div class="grid">
        <div>
            <h2>{{ __('accounting.bill_to') }}</h2>
            <div>{{ data_get($accountingEvent->payload, 'customer_name', __('accounting.customer')) }}</div>
            <div class="muted">{{ data_get($accountingEvent->payload, 'vehicle', '') }}</div>
        </div>
        <div>
            <h2>{{ __('accounting.invoice_detail') }}</h2>
            <div>{{ __('accounting.event_date') }}: {{ optional($accountingEvent->event_date)->format('Y-m-d') }}</div>
            <div>{{ __('accounting.work_order') }}: {{ data_get($accountingEvent->payload, 'work_order_number', '-') }}</div>
            <div>{{ __('accounting.status') }}: {{ strtoupper(str_replace('_', ' ', $accountingEvent->status)) }}</div>
        </div>
    </div>

    <table>
        <thead><tr><th>{{ __('accounting.description') }}</th><th class="right">{{ __('accounting.amount') }}</th></tr></thead>
        <tbody>
            @foreach($document['lines'] as $line)
                <tr><td>{{ $line['description'] }}</td><td class="right">{{ number_format((float) $line['amount'], 2) }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tbody>
            <tr><th>{{ __('accounting.total') }}</th><td class="right">{{ number_format((float) $accountingEvent->total_amount, 2) }} {{ $accountingEvent->currency }}</td></tr>
            <tr><th>{{ __('accounting.paid_amount') }}</th><td class="right">{{ number_format((float) $document['paid_amount'], 2) }} {{ $accountingEvent->currency }}</td></tr>
            <tr><th>{{ __('accounting.open_balance') }}</th><td class="right">{{ number_format((float) $document['open_amount'], 2) }} {{ $accountingEvent->currency }}</td></tr>
        </tbody>
    </table>

    <h2>{{ __('accounting.payments') }}</h2>
    <table>
        <thead><tr><th>{{ __('accounting.payment') }}</th><th>{{ __('accounting.date') }}</th><th>{{ __('accounting.status') }}</th><th>{{ __('accounting.method') }}</th><th class="right">{{ __('accounting.amount') }}</th></tr></thead>
        <tbody>
            @forelse($document['payments'] as $payment)
                <tr>
                    <td>{{ $payment->payment_number }}</td>
                    <td>{{ optional($payment->payment_date)->format('Y-m-d') }}</td>
                    <td>{{ strtoupper($payment->status) }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $payment->method)) }}</td>
                    <td class="right">{{ number_format((float) $payment->amount, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">{{ __('accounting.no_payments_recorded') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
