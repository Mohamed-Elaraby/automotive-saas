<h1>{{ __('maintenance.documents.delivery') }}</h1>
@include('core.documents.components.document-meta')

<table class="grid-table">
    <tr>
        <td><strong>{{ __('maintenance.deliveries') }}</strong><br>{{ data_get($snapshot, 'delivery.delivery_number') }}</td>
        <td><strong>{{ __('maintenance.work_order') }}</strong><br>{{ data_get($snapshot, 'work_order.work_order_number') }}</td>
        <td><strong>{{ __('tenant.name') }}</strong><br>{{ data_get($snapshot, 'customer.name') }}</td>
        <td><strong>{{ __('maintenance.vehicle') }}</strong><br>{{ data_get($snapshot, 'vehicle.plate_number') }}</td>
    </tr>
</table>

<h2>{{ __('maintenance.delivery_records') }}</h2>
<table>
    <thead><tr><th>{{ __('maintenance.item_label') }}</th><th>{{ __('maintenance.status') }}</th></tr></thead>
    <tbody>
    @foreach((array) data_get($snapshot, 'delivery.checklist', []) as $key => $value)
        <tr><td>{{ __('maintenance.delivery_checklist.' . $key) }}</td><td>{{ $value ? __('maintenance.qc_results.passed') : __('maintenance.results.not_checked') }}</td></tr>
    @endforeach
    </tbody>
</table>

<table class="keep-together">
    <tr>
        <td>@include('core.documents.components.signature-box', ['label' => __('maintenance.customer_signature'), 'name' => data_get($snapshot, 'customer.name')])</td>
        <td>@include('core.documents.components.signature-box', ['label' => __('maintenance.service_advisor_signature'), 'name' => data_get($snapshot, 'delivery.deliverer.name')])</td>
        <td>@include('core.documents.components.qr-code')</td>
    </tr>
</table>
