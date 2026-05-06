<div class="signature-box keep-together">
    <strong>{{ $label ?? __('maintenance.signature') }}</strong>
    @php
        $signatureSrc = is_string($signature ?? null) ? trim($signature) : '';
        $signatureIsImage = str_starts_with($signatureSrc, 'data:image/')
            || str_starts_with($signatureSrc, 'http://')
            || str_starts_with($signatureSrc, 'https://')
            || str_starts_with($signatureSrc, '/');
    @endphp
    @if($signatureIsImage)
        <br>
        <img src="{{ $signatureSrc }}" alt="{{ $label ?? __('maintenance.signature') }}" style="max-height: 34px; max-width: 180px; margin-top: 4px;">
    @else
        <br><br>
    @endif
    <span class="muted small">{{ $name ?? '' }}</span>
</div>
