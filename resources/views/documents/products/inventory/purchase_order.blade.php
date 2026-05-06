<h1>{{ data_get($document, 'document_title', 'Purchase Order') }}</h1>
@include('core.documents.components.document-meta')

<table class="grid-table">
    <tr>
        <td><strong>PO</strong><br>{{ data_get($snapshot, 'purchase_order.number') }}</td>
        <td><strong>Supplier</strong><br>{{ data_get($snapshot, 'supplier.name') }}</td>
        <td><strong>Date</strong><br>{{ data_get($snapshot, 'purchase_order.date') }}</td>
        <td><strong>Status</strong><br>{{ data_get($snapshot, 'purchase_order.status') }}</td>
    </tr>
</table>

@include('documents.products.shared.lines')
