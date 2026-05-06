<h1>{{ __('maintenance.documents.receipt') }}</h1>
@include('core.documents.components.document-meta')

<table class="grid-table">
    <tr>
        <td><strong>{{ __('maintenance.receipt_number') }}</strong><br>{{ data_get($snapshot, 'receipt.receipt_number') }}</td>
        <td><strong>{{ __('maintenance.invoice_number') }}</strong><br>{{ data_get($snapshot, 'invoice.invoice_number') }}</td>
        <td><strong>{{ __('tenant.name') }}</strong><br>{{ data_get($snapshot, 'customer.name') }}</td>
        <td><strong>{{ __('maintenance.vehicle') }}</strong><br>{{ data_get($snapshot, 'vehicle.plate_number') }} {{ data_get($snapshot, 'vehicle.make') }} {{ data_get($snapshot, 'vehicle.model') }}</td>
    </tr>
    <tr>
        <td><strong>{{ __('maintenance.payment_method') }}</strong><br>{{ data_get($snapshot, 'receipt.payment_method') }}</td>
        <td><strong>{{ __('maintenance.amount') }}</strong><br>{{ number_format((float) data_get($snapshot, 'receipt.amount'), 2) }} {{ data_get($snapshot, 'receipt.currency') }}</td>
        <td><strong>{{ __('maintenance.reference_number') }}</strong><br>{{ data_get($snapshot, 'receipt.reference_number') ?: '-' }}</td>
        <td><strong>{{ __('maintenance.received_at') }}</strong><br>{{ data_get($snapshot, 'receipt.received_at') }}</td>
    </tr>
</table>

<div class="section keep-together">
    <h2>{{ __('maintenance.payment_summary') }}</h2>
    <table>
        <thead><tr><th>{{ __('maintenance.subtotal') }}</th><th>{{ __('maintenance.tax') }}</th><th>{{ __('maintenance.total') }}</th><th>{{ __('maintenance.paid_amount') }}</th><th>{{ __('maintenance.payment_status') }}</th></tr></thead>
        <tbody>
            <tr>
                <td>{{ number_format((float) data_get($snapshot, 'invoice.subtotal'), 2) }}</td>
                <td>{{ number_format((float) data_get($snapshot, 'invoice.tax_total'), 2) }}</td>
                <td>{{ number_format((float) data_get($snapshot, 'invoice.grand_total'), 2) }}</td>
                <td>{{ number_format((float) data_get($snapshot, 'invoice.paid_amount'), 2) }}</td>
                <td>{{ data_get($snapshot, 'invoice.payment_status') }}</td>
            </tr>
        </tbody>
    </table>
</div>

@if(data_get($snapshot, 'receipt.notes'))
    <p class="long-text"><strong>{{ __('maintenance.note') }}:</strong><br>{{ data_get($snapshot, 'receipt.notes') }}</p>
@endif

<div class="keep-together">@include('core.documents.components.qr-code')</div>
