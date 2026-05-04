<h1>{{ __('maintenance.documents.work_order') }}</h1>
@include('core.documents.components.document-meta')

<div class="section">
    <table class="grid-table">
        <tr>
            <td><strong>{{ __('maintenance.work_order') }}</strong><br>{{ data_get($snapshot, 'work_order.work_order_number') }}</td>
            <td><strong>{{ __('maintenance.status') }}</strong><br>{{ data_get($snapshot, 'work_order.status') }}</td>
            <td><strong>{{ __('tenant.name') }}</strong><br>{{ data_get($snapshot, 'customer.name') }}</td>
            <td><strong>{{ __('maintenance.vehicle') }}</strong><br>{{ data_get($snapshot, 'vehicle.plate_number') }} {{ data_get($snapshot, 'vehicle.make') }} {{ data_get($snapshot, 'vehicle.model') }}</td>
        </tr>
    </table>
</div>

<div class="section">
    <h2>{{ __('maintenance.technician_jobs') }}</h2>
    <table>
        <thead><tr><th>{{ __('maintenance.job_title') }}</th><th>{{ __('maintenance.technician') }}</th><th>{{ __('maintenance.status') }}</th><th>{{ __('maintenance.estimated_minutes') }}</th><th>{{ __('maintenance.minutes') }}</th></tr></thead>
        <tbody>
        @forelse(data_get($snapshot, 'jobs', []) as $job)
            <tr><td>{{ $job['title'] ?? '' }}</td><td>{{ data_get($job, 'technician.name') }}</td><td>{{ $job['status'] ?? '' }}</td><td>{{ $job['estimated_minutes'] ?? 0 }}</td><td>{{ $job['actual_minutes'] ?? 0 }}</td></tr>
        @empty
            <tr><td colspan="5">{{ __('maintenance.no_jobs') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="section">
    <h2>{{ __('maintenance.timeline') }}</h2>
    <table>
        <thead><tr><th>{{ __('maintenance.status') }}</th><th>{{ __('maintenance.note') }}</th><th>{{ __('maintenance.generated_at') }}</th></tr></thead>
        <tbody>
        @forelse(data_get($snapshot, 'timeline', []) as $entry)
            <tr><td>{{ $entry['event_type'] ?? '' }}</td><td>{{ $entry['title'] ?? '' }}</td><td>{{ $entry['created_at'] ?? '' }}</td></tr>
        @empty
            <tr><td colspan="3">{{ __('maintenance.none') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="keep-together">@include('core.documents.components.qr-code')</div>
