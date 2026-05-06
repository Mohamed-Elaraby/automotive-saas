<h1>{{ __('maintenance.documents.invoice') }}</h1>
@include('core.documents.components.document-meta')

<table class="grid-table">
    <tr>
        <td><strong>{{ __('maintenance.invoice_number') }}</strong><br>{{ data_get($snapshot, 'invoice.invoice_number') }}</td>
        <td><strong>{{ __('tenant.name') }}</strong><br>{{ data_get($snapshot, 'customer.name') }}</td>
        <td><strong>{{ __('maintenance.vehicle') }}</strong><br>{{ data_get($snapshot, 'vehicle.plate_number') }} {{ data_get($snapshot, 'vehicle.make') }} {{ data_get($snapshot, 'vehicle.model') }}</td>
        <td><strong>{{ __('maintenance.work_order') }}</strong><br>{{ data_get($snapshot, 'work_order.work_order_number') ?: '-' }}</td>
    </tr>
    <tr>
        <td><strong>{{ __('maintenance.status') }}</strong><br>{{ data_get($snapshot, 'invoice.status') }}</td>
        <td><strong>{{ __('maintenance.payment_status') }}</strong><br>{{ data_get($snapshot, 'invoice.payment_status') }}</td>
        <td><strong>{{ __('maintenance.issued_at') }}</strong><br>{{ data_get($snapshot, 'invoice.issued_at') }}</td>
        <td><strong>{{ __('maintenance.branch') }}</strong><br>{{ data_get($snapshot, 'branch.name') }}</td>
    </tr>
</table>

<table>
    <thead>
    <tr>
        <th>{{ __('tenant.description') }}</th>
        <th>{{ __('maintenance.method') }}</th>
        <th>{{ __('maintenance.quantity') }}</th>
        <th>{{ __('maintenance.default_labor_price') }}</th>
        <th>{{ __('maintenance.total') }}</th>
    </tr>
    </thead>
    <tbody>
    @forelse(data_get($snapshot, 'lines', []) as $line)
        <tr>
            <td>{{ $line['description'] ?? $line['title'] ?? '' }}</td>
            <td>{{ $line['line_type'] ?? '' }}</td>
            <td>{{ $line['quantity'] ?? 1 }}</td>
            <td>{{ number_format((float) ($line['unit_price'] ?? 0), 2) }}</td>
            <td>{{ number_format((float) ($line['total_price'] ?? 0), 2) }}</td>
        </tr>
    @empty
        <tr><td colspan="5">{{ __('maintenance.no_records') }}</td></tr>
    @endforelse
    </tbody>
</table>

@include('core.documents.components.totals-table', [
    'subtotal' => data_get($snapshot, 'invoice.subtotal'),
    'discount' => data_get($snapshot, 'invoice.discount_total'),
    'tax' => data_get($snapshot, 'invoice.tax_total'),
    'total' => data_get($snapshot, 'invoice.grand_total'),
])

<div class="section keep-together">
    <h2>{{ __('maintenance.receipts') }}</h2>
    <table>
        <thead><tr><th>{{ __('maintenance.receipt_number') }}</th><th>{{ __('maintenance.payment_method') }}</th><th>{{ __('maintenance.amount') }}</th><th>{{ __('maintenance.received_at') }}</th></tr></thead>
        <tbody>
        @forelse(data_get($snapshot, 'receipts', []) as $receipt)
            <tr><td>{{ $receipt['receipt_number'] ?? '' }}</td><td>{{ $receipt['payment_method'] ?? '' }}</td><td>{{ number_format((float) ($receipt['amount'] ?? 0), 2) }}</td><td>{{ $receipt['received_at'] ?? '' }}</td></tr>
        @empty
            <tr><td colspan="4">{{ __('maintenance.no_receipts') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="keep-together">@include('core.documents.components.qr-code')</div>
