<h1>{{ __('maintenance.documents.approval_certificate') }}</h1>
@include('core.documents.components.document-meta')

<div class="section">
    <h2>{{ __('maintenance.customer_approvals') }}</h2>
    <table class="grid-table">
        <tr>
            <td><strong>{{ __('maintenance.document_number') }}</strong><br>{{ data_get($snapshot, 'estimate.estimate_number') }}</td>
            <td><strong>{{ __('tenant.name') }}</strong><br>{{ data_get($snapshot, 'customer.name') }}</td>
            <td><strong>{{ __('maintenance.vehicle') }}</strong><br>{{ data_get($snapshot, 'vehicle.plate_number') }} {{ data_get($snapshot, 'vehicle.make') }} {{ data_get($snapshot, 'vehicle.model') }}</td>
            <td><strong>{{ __('maintenance.method') }}</strong><br>{{ data_get($snapshot, 'approval.method') }}</td>
        </tr>
        <tr>
            <td><strong>{{ __('maintenance.status') }}</strong><br>{{ data_get($snapshot, 'approval.status') }}</td>
            <td><strong>{{ __('maintenance.approved_at_label') }}</strong><br>{{ data_get($snapshot, 'approval.approved_at') }}</td>
            <td><strong>{{ __('maintenance.total') }}</strong><br>{{ number_format((float) data_get($snapshot, 'estimate.grand_total', 0), 2) }}</td>
            <td><strong>{{ __('maintenance.approved_amount') }}</strong><br>{{ number_format((float) data_get($snapshot, 'approval.approved_amount', 0), 2) }}</td>
        </tr>
    </table>
</div>

<div class="section">
    <h2>{{ __('maintenance.estimate_lines') }}</h2>
    <table>
        <thead>
        <tr>
            <th>{{ __('tenant.description') }}</th>
            <th>{{ __('maintenance.quantity') }}</th>
            <th>{{ __('maintenance.total') }}</th>
            <th>{{ __('maintenance.status') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach(data_get($snapshot, 'lines', []) as $line)
            <tr>
                <td>{{ $line['description'] ?? '' }}</td>
                <td>{{ $line['quantity'] ?? 0 }}</td>
                <td>{{ number_format((float) ($line['total_price'] ?? 0), 2) }}</td>
                <td>{{ $line['approval_status'] ?? '' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<div class="section keep-together">
    <p class="long-text"><strong>{{ __('maintenance.terms') }}:</strong><br>{{ data_get($snapshot, 'approval.terms_snapshot') }}</p>
    <p><strong>{{ __('maintenance.customer_portal.accept_terms') }}:</strong> {{ data_get($snapshot, 'approval.terms_accepted') ? __('maintenance.yes') : __('maintenance.no') }}</p>
    @if(data_get($snapshot, 'approval.reason'))
        <p class="long-text"><strong>{{ __('maintenance.customer_portal.customer_note') }}:</strong><br>{{ data_get($snapshot, 'approval.reason') }}</p>
    @endif
</div>

<table class="keep-together">
    <tr>
        <td>@include('core.documents.components.signature-box', ['label' => __('maintenance.customer_signature'), 'name' => data_get($snapshot, 'customer.name')])</td>
        <td>@include('core.documents.components.qr-code')</td>
    </tr>
</table>
