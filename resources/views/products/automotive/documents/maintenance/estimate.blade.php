<h1>{{ __('maintenance.documents.estimate') }}</h1>
@include('core.documents.components.document-meta')

<table class="grid-table">
    <tr>
        <td><strong>{{ __('maintenance.estimates') }}</strong><br>{{ data_get($snapshot, 'estimate.estimate_number') }}</td>
        <td><strong>{{ __('tenant.name') }}</strong><br>{{ data_get($snapshot, 'customer.name') }}</td>
        <td><strong>{{ __('maintenance.vehicle') }}</strong><br>{{ data_get($snapshot, 'vehicle.plate_number') }} {{ data_get($snapshot, 'vehicle.make') }} {{ data_get($snapshot, 'vehicle.model') }}</td>
        <td><strong>{{ __('maintenance.valid_until') }}</strong><br>{{ data_get($snapshot, 'estimate.valid_until') }}</td>
    </tr>
</table>

<table>
    <thead><tr><th>{{ tenant('id') ? __('tenant.description') : 'Description' }}</th><th>{{ __('maintenance.method') }}</th><th>{{ __('maintenance.quantity') }}</th><th>{{ __('maintenance.default_labor_price') }}</th><th>{{ __('maintenance.total') }}</th></tr></thead>
    <tbody>
    @foreach(data_get($snapshot, 'lines', []) as $line)
        <tr><td>{{ $line['description'] ?? '' }}</td><td>{{ $line['line_type'] ?? '' }}</td><td>{{ $line['quantity'] ?? 0 }}</td><td>{{ number_format((float) ($line['unit_price'] ?? 0), 2) }}</td><td>{{ number_format((float) ($line['total_price'] ?? 0), 2) }}</td></tr>
    @endforeach
    </tbody>
</table>

@include('core.documents.components.totals-table', [
    'subtotal' => data_get($snapshot, 'estimate.subtotal'),
    'discount' => data_get($snapshot, 'estimate.discount_total'),
    'tax' => data_get($snapshot, 'estimate.tax_total'),
    'total' => data_get($snapshot, 'estimate.grand_total'),
])

<p class="long-text"><strong>{{ __('maintenance.terms') }}:</strong><br>{{ data_get($snapshot, 'estimate.terms') }}</p>
<div class="keep-together">@include('core.documents.components.qr-code')</div>
