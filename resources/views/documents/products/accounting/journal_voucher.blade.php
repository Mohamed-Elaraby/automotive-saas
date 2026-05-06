<h1>{{ data_get($document, 'document_title', 'Journal Voucher') }}</h1>
@include('core.documents.components.document-meta')

@include('documents.products.shared.lines')
