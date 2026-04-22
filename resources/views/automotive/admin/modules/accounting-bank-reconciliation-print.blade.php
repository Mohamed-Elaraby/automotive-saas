<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bank Reconciliation Report</title>
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
    <button class="no-print" onclick="window.print()">Print</button>
    <div class="header">
        <div>
            <h1>Bank Reconciliation Report</h1>
            <div class="muted">
                Deposit batches, direct receipts, and vendor payments
                · Period {{ data_get($reportData, 'filters.date_from') ?: 'Beginning' }} to {{ data_get($reportData, 'filters.date_to') ?: now()->toDateString() }}
                @if(data_get($reportData, 'filters.reconciliation_status'))
                    · Reconciliation {{ strtoupper(data_get($reportData, 'filters.reconciliation_status')) }}
                @endif
                @if(data_get($reportData, 'filters.deposit_account'))
                    · Account {{ data_get($reportData, 'filters.deposit_account') }}
                @endif
            </div>
        </div>
        <div class="right">
            <strong>{{ now()->format('Y-m-d H:i') }}</strong>
            <div class="muted">Generated At</div>
        </div>
    </div>

    <div class="summary">
        <div class="box"><div class="muted">Posted Batches</div><h3>{{ $reportData['posted_count'] }}</h3></div>
        <div class="box"><div class="muted">Posted Total</div><h3>{{ number_format((float) $reportData['posted_total'], 2) }}</h3></div>
        <div class="box"><div class="muted">Reconciled Batches</div><h3>{{ $reportData['reconciled_count'] }}</h3></div>
        <div class="box"><div class="muted">Reconciled Total</div><h3>{{ number_format((float) $reportData['reconciled_total'], 2) }}</h3></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Deposit</th>
                <th>Date</th>
                <th>Account</th>
                <th>Status</th>
                <th>Reference</th>
                <th>Bank Match</th>
                <th class="right">Payments</th>
                <th class="right">Amount</th>
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
                <tr><td colspan="8" class="muted">No deposit batches match this report.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2 style="margin-top: 28px;">Direct Receipts</h2>
    <table>
        <thead>
            <tr>
                <th>Payment</th>
                <th>Date</th>
                <th>Account</th>
                <th>Status</th>
                <th>Reference</th>
                <th>Bank Match</th>
                <th class="right">Amount</th>
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
                <tr><td colspan="7" class="muted">No direct receipts match this report.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2 style="margin-top: 28px;">Vendor Payments</h2>
    <table>
        <thead>
            <tr>
                <th>Payment</th>
                <th>Date</th>
                <th>Account</th>
                <th>Status</th>
                <th>Reference</th>
                <th>Bank Match</th>
                <th class="right">Amount</th>
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
                <tr><td colspan="7" class="muted">No vendor payments match this report.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
