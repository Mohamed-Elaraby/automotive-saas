<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('accounting.bank_reconciliation_report') }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #1f2937; margin: 32px; }
        h1, h2, h3 { margin: 0; }
        .muted { color: #6b7280; }
        .header { display: flex; justify-content: space-between; gap: 24px; border-bottom: 2px solid #111827; padding-bottom: 16px; margin-bottom: 24px; }
        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 24px; }
        .box { border: 1px solid #d1d5db; padding: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 8px; text-align: left; font-size: 13px; }
        th { background: #f3f4f6; }
        .right { text-align: right; }
        .badge { display: inline-block; padding: 2px 8px; border: 1px solid #d1d5db; font-size: 12px; }
        @media print { body { margin: 18mm; } .no-print { display: none; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">{{ __('accounting.print') }}</button>
    <div class="header">
        <div>
            <h1>{{ __('accounting.bank_reconciliation_report') }}</h1>
            <div class="muted">
                {{ __('accounting.bank_reconciliation_scope') }}
                · {{ __('accounting.period') }} {{ data_get($reportData, 'filters.date_from') ?: __('accounting.beginning') }} {{ __('accounting.to') }} {{ data_get($reportData, 'filters.date_to') ?: now()->toDateString() }}
                @if(data_get($reportData, 'filters.reconciliation_status'))
                    · {{ __('accounting.reconciliation') }} {{ strtoupper(data_get($reportData, 'filters.reconciliation_status')) }}
                @endif
                @if(data_get($reportData, 'filters.deposit_account'))
                    · {{ __('accounting.account') }} {{ data_get($reportData, 'filters.deposit_account') }}
                @endif
            </div>
        </div>
        <div class="right">
            <strong>{{ now()->format('Y-m-d H:i') }}</strong>
            <div class="muted">{{ __('accounting.generated_at') }}</div>
        </div>
    </div>

    <div class="summary">
        <div class="box"><div class="muted">{{ __('accounting.posted_batches') }}</div><h3>{{ $reportData['posted_count'] }}</h3></div>
        <div class="box"><div class="muted">{{ __('accounting.posted_total') }}</div><h3>{{ number_format((float) $reportData['posted_total'], 2) }}</h3></div>
        <div class="box"><div class="muted">{{ __('accounting.reconciled_batches') }}</div><h3>{{ $reportData['reconciled_count'] }}</h3></div>
        <div class="box"><div class="muted">{{ __('accounting.reconciled_total') }}</div><h3>{{ number_format((float) $reportData['reconciled_total'], 2) }}</h3></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('accounting.deposit') }}</th>
                <th>{{ __('accounting.date') }}</th>
                <th>{{ __('accounting.account') }}</th>
                <th>{{ __('accounting.status') }}</th>
                <th>{{ __('accounting.reference') }}</th>
                <th>{{ __('accounting.bank_match') }}</th>
                <th class="right">{{ __('accounting.payments') }}</th>
                <th class="right">{{ __('accounting.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reportData['batches'] as $batch)
                <tr>
                    <td>{{ $batch->deposit_number }}</td>
                    <td>{{ optional($batch->deposit_date)->format('Y-m-d') }}</td>
                    <td>{{ $batch->deposit_account }}</td>
                    <td><span class="badge">{{ strtoupper($batch->status) }} / {{ strtoupper($batch->reconciliation_status ?: 'pending') }}</span></td>
                    <td>{{ $batch->reference ?: '-' }}</td>
                    <td>{{ optional($batch->bank_reconciliation_date)->format('Y-m-d') ?: '-' }}{{ $batch->bank_reference ? ' · '.$batch->bank_reference : '' }}</td>
                    <td class="right">{{ $batch->payments_count }}</td>
                    <td class="right">{{ number_format((float) $batch->total_amount, 2) }} {{ $batch->currency }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">{{ __('accounting.no_deposit_batches_match') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2 style="margin-top: 28px;">{{ __('accounting.direct_receipts') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('accounting.payment') }}</th>
                <th>{{ __('accounting.date') }}</th>
                <th>{{ __('accounting.account') }}</th>
                <th>{{ __('accounting.status') }}</th>
                <th>{{ __('accounting.reference') }}</th>
                <th>{{ __('accounting.bank_match') }}</th>
                <th class="right">{{ __('accounting.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reportData['direct_receipts'] as $payment)
                <tr>
                    <td>{{ $payment->payment_number }}</td>
                    <td>{{ optional($payment->payment_date)->format('Y-m-d') }}</td>
                    <td>{{ $payment->cash_account }}</td>
                    <td><span class="badge">{{ strtoupper($payment->reconciliation_status ?: 'pending') }}</span></td>
                    <td>{{ $payment->reference ?: '-' }}</td>
                    <td>{{ optional($payment->bank_reconciliation_date)->format('Y-m-d') ?: '-' }}{{ $payment->bank_reference ? ' · '.$payment->bank_reference : '' }}</td>
                    <td class="right">{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">{{ __('accounting.no_direct_receipts_match') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2 style="margin-top: 28px;">{{ __('accounting.vendor_payments') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('accounting.payment') }}</th>
                <th>{{ __('accounting.date') }}</th>
                <th>{{ __('accounting.account') }}</th>
                <th>{{ __('accounting.status') }}</th>
                <th>{{ __('accounting.reference') }}</th>
                <th>{{ __('accounting.bank_match') }}</th>
                <th class="right">{{ __('accounting.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reportData['vendor_payments'] as $payment)
                <tr>
                    <td>{{ $payment->payment_number }}</td>
                    <td>{{ optional($payment->payment_date)->format('Y-m-d') }}</td>
                    <td>{{ $payment->cash_account }}</td>
                    <td><span class="badge">{{ strtoupper($payment->reconciliation_status ?: 'pending') }}</span></td>
                    <td>{{ $payment->reference ?: '-' }}</td>
                    <td>{{ optional($payment->bank_reconciliation_date)->format('Y-m-d') ?: '-' }}{{ $payment->bank_reference ? ' · '.$payment->bank_reference : '' }}</td>
                    <td class="right">{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">{{ __('accounting.no_vendor_payments_match') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
