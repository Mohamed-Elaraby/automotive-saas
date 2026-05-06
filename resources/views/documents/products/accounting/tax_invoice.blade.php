<h1>{{ data_get($document, 'document_title', 'Tax Invoice') }}</h1>
@include('core.documents.components.document-meta')

<table class="grid-table">
    <tr>
        <td><strong>Invoice</strong><br>{{ data_get($snapshot, 'invoice.invoice_number') }}</td>
        <td><strong>Customer</strong><br>{{ data_get($snapshot, 'customer.name') }}</td>
        <td><strong>Date</strong><br>{{ data_get($snapshot, 'invoice.issued_at') }}</td>
        <td><strong>Total</strong><br>{{ number_format((float) data_get($snapshot, 'invoice.total', 0), 2) }}</td>
    </tr>
</table>

@include('documents.products.shared.lines')
