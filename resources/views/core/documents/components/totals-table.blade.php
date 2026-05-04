<table class="keep-together">
    <tr><th>{{ __('maintenance.subtotal') }}</th><td>{{ number_format((float) ($subtotal ?? 0), 2) }}</td></tr>
    <tr><th>{{ __('maintenance.discount') }}</th><td>{{ number_format((float) ($discount ?? 0), 2) }}</td></tr>
    <tr><th>{{ __('maintenance.tax') }}</th><td>{{ number_format((float) ($tax ?? 0), 2) }}</td></tr>
    <tr><th>{{ __('maintenance.total') }}</th><td><strong>{{ number_format((float) ($total ?? 0), 2) }}</strong></td></tr>
</table>
