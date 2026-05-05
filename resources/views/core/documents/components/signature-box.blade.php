<div class="signature-box keep-together">
    <strong>{{ $label ?? __('maintenance.signature') }}</strong>
    @if(! empty($signature))
        <br>
        <img src="{{ $signature }}" alt="{{ $label ?? __('maintenance.signature') }}" style="max-height: 34px; max-width: 180px; margin-top: 4px;">
    @else
        <br><br>
    @endif
    <span class="muted small">{{ $name ?? '' }}</span>
</div>
