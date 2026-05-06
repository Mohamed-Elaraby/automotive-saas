<h1>{{ data_get($document, 'document_title', 'Stock Transfer') }}</h1>
@include('core.documents.components.document-meta')

<table class="grid-table">
    <tr>
        <td><strong>From</strong><br>{{ data_get($snapshot, 'from_branch.name') }}</td>
        <td><strong>To</strong><br>{{ data_get($snapshot, 'to_branch.name') }}</td>
        <td><strong>Status</strong><br>{{ data_get($snapshot, 'transfer.status') }}</td>
        <td><strong>Date</strong><br>{{ data_get($snapshot, 'transfer.date') }}</td>
    </tr>
</table>

@include('documents.products.shared.lines')
