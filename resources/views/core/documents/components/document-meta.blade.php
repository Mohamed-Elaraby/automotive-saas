<table class="grid-table">
    <tr>
        <td><strong>{{ __('maintenance.document_number') }}</strong><br>{{ $document['document_number'] ?? '' }}</td>
        <td><strong>{{ __('maintenance.version') }}</strong><br>{{ $document['version'] ?? 1 }}</td>
        <td><strong>{{ __('maintenance.language') }}</strong><br>{{ strtoupper($document['language'] ?? 'en') }}</td>
        <td><strong>{{ __('maintenance.status') }}</strong><br>{{ strtoupper($document['status'] ?? 'processing') }}</td>
    </tr>
</table>
