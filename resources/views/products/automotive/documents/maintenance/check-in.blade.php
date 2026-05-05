<h1>{{ __('maintenance.documents.check_in') }}</h1>
@include('core.documents.components.document-meta')

<div class="section">
    <h2>{{ __('maintenance.customer_vehicle') }}</h2>
    <table class="grid-table">
        <tr>
            <td><strong>{{ __('tenant.name') }}</strong><br>{{ data_get($snapshot, 'customer.name') }}</td>
            <td><strong>{{ __('maintenance.vehicle') }}</strong><br>{{ data_get($snapshot, 'vehicle.make') }} {{ data_get($snapshot, 'vehicle.model') }}</td>
            <td><strong>{{ __('maintenance.vin_number') }}</strong><br>{{ data_get($snapshot, 'vehicle.vin_number') ?: data_get($snapshot, 'check_in.vin_number') }}</td>
            <td><strong>{{ __('maintenance.odometer') }}</strong><br>{{ data_get($snapshot, 'check_in.odometer') }}</td>
        </tr>
    </table>
</div>

<div class="section">
    <h2>{{ __('maintenance.check_in_details') }}</h2>
    <table class="grid-table">
        <tr>
            <td><strong>{{ __('maintenance.status') }}</strong><br>{{ data_get($snapshot, 'check_in.status') }}</td>
            <td><strong>{{ __('maintenance.fuel_level') }}</strong><br>{{ data_get($snapshot, 'check_in.fuel_level') }}%</td>
            <td><strong>{{ __('maintenance.expected_delivery_at') }}</strong><br>{{ data_get($snapshot, 'check_in.expected_delivery_at') }}</td>
            <td><strong>{{ __('maintenance.work_order') }}</strong><br>{{ data_get($snapshot, 'work_order.work_order_number') }}</td>
        </tr>
    </table>
    <p class="long-text"><strong>{{ __('maintenance.customer_complaint') }}:</strong><br>{{ data_get($snapshot, 'check_in.customer_complaint') }}</p>
    <p class="long-text"><strong>{{ __('maintenance.existing_damage_notes') }}:</strong><br>{{ data_get($snapshot, 'check_in.existing_damage_notes') }}</p>
</div>

<div class="section">
    <h2>{{ __('maintenance.condition_map') }}</h2>
    <table>
        <thead><tr><th>{{ __('maintenance.vehicle_area') }}</th><th>{{ __('maintenance.method') }}</th><th>{{ __('maintenance.severity') }}</th><th>{{ __('tenant.description') }}</th></tr></thead>
        <tbody>
        @forelse(data_get($snapshot, 'condition_maps.0.items', []) as $item)
            <tr><td>{{ $item['label'] ?? $item['vehicle_area_code'] ?? '' }}</td><td>{{ $item['note_type'] ?? '' }}</td><td>{{ $item['severity'] ?? '' }}</td><td>{{ $item['description'] ?? '' }}</td></tr>
        @empty
            <tr><td colspan="4">{{ __('maintenance.no_condition_items') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<table class="keep-together">
    <tr>
        <td>@include('core.documents.components.signature-box', ['label' => __('maintenance.customer_signature'), 'name' => data_get($snapshot, 'customer.name'), 'signature' => data_get($snapshot, 'check_in.customer_signature')])</td>
        <td>@include('core.documents.components.signature-box', ['label' => __('maintenance.service_advisor_signature'), 'name' => data_get($snapshot, 'check_in.service_advisor.name'), 'signature' => data_get($snapshot, 'check_in.service_advisor_signature')])</td>
        <td>@include('core.documents.components.qr-code')</td>
    </tr>
</table>
