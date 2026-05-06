<h1>{{ data_get($document, 'document_title', 'Statement of Account') }}</h1>
@include('core.documents.components.document-meta')

<div class="section">
    <h2>{{ data_get($snapshot, 'customer.name', 'Customer') }}</h2>
    @include('documents.products.shared.lines')
</div>
