<h1>{{ data_get($document, 'document_title', 'Payment Voucher') }}</h1>
@include('core.documents.components.document-meta')

<table class="grid-table">
    <tr>
        <td><strong>Voucher</strong><br>{{ data_get($snapshot, 'voucher.number') }}</td>
        <td><strong>Payee</strong><br>{{ data_get($snapshot, 'payee.name') }}</td>
        <td><strong>Date</strong><br>{{ data_get($snapshot, 'voucher.date') }}</td>
        <td><strong>Amount</strong><br>{{ number_format((float) data_get($snapshot, 'voucher.amount', 0), 2) }}</td>
    </tr>
</table>
