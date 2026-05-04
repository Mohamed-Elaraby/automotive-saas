<h1>{{ __('maintenance.documents.warranty') }}</h1>
@include('core.documents.components.document-meta')

<table class="grid-table">
    <tr>
        <td><strong>{{ __('maintenance.warranty') }}</strong><br>{{ data_get($snapshot, 'warranty.warranty_number') }}</td>
        <td><strong>{{ __('maintenance.warranty_type') }}</strong><br>{{ data_get($snapshot, 'warranty.warranty_type') }}</td>
        <td><strong>{{ __('tenant.name') }}</strong><br>{{ data_get($snapshot, 'customer.name') }}</td>
        <td><strong>{{ __('maintenance.vehicle') }}</strong><br>{{ data_get($snapshot, 'vehicle.plate_number') }}</td>
    </tr>
    <tr>
        <td><strong>{{ __('maintenance.start_date') }}</strong><br>{{ data_get($snapshot, 'warranty.start_date') }}</td>
        <td><strong>{{ __('maintenance.end_date') }}</strong><br>{{ data_get($snapshot, 'warranty.end_date') }}</td>
        <td><strong>{{ __('maintenance.mileage_limit') }}</strong><br>{{ data_get($snapshot, 'warranty.mileage_limit') }}</td>
        <td><strong>{{ __('maintenance.service_catalog') }}</strong><br>{{ data_get($snapshot, 'service.name') }}</td>
    </tr>
</table>

<div class="section keep-together">
    <h2>{{ __('maintenance.terms') }}</h2>
    <p class="long-text">{{ data_get($snapshot, 'warranty.terms') }}</p>
</div>

<table class="keep-together">
    <tr>
        <td>@include('core.documents.components.signature-box', ['label' => __('maintenance.customer_signature'), 'name' => data_get($snapshot, 'customer.name')])</td>
        <td>@include('core.documents.components.qr-code')</td>
    </tr>
</table>
