@if(! empty($document['qr_enabled']) && ! empty($document['verify_url']))
    <div class="keep-together">
        <barcode code="{{ $document['verify_url'] }}" type="QR" size="0.8" error="M" />
        <div class="small muted">{{ $document['verify_url'] }}</div>
    </div>
@endif
