<h1>{{ data_get($document, 'document_title', 'Job Card') }}</h1>
@include('core.documents.components.document-meta')

<table class="grid-table">
    <tr>
        <td><strong>Job</strong><br>{{ data_get($snapshot, 'job.job_number') ?: data_get($snapshot, 'work_order.work_order_number') }}</td>
        <td><strong>Technician</strong><br>{{ data_get($snapshot, 'job.technician.name') }}</td>
        <td><strong>Status</strong><br>{{ data_get($snapshot, 'job.status') }}</td>
        <td><strong>Vehicle</strong><br>{{ data_get($snapshot, 'vehicle.plate_number') }}</td>
    </tr>
</table>

<div class="section">
    <h2>Instructions</h2>
    <p class="long-text">{{ data_get($snapshot, 'job.description') ?: data_get($snapshot, 'job.internal_notes') }}</p>
</div>
