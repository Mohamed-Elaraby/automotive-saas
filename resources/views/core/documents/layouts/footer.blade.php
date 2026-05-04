<table style="width:100%; border:0; border-top:1px solid #d1d5db; margin-top:0;">
    <tr>
        <td style="border:0;" class="small muted">
            {{ __('maintenance.generated_at') }}: {{ now()->format('Y-m-d H:i') }}
        </td>
        <td style="border:0; text-align:center;" class="small muted">
            {PAGENO} / {nbpg}
        </td>
        <td style="border:0; text-align:right;" class="small muted">
            {{ $document['verify_url'] ?? '' }}
        </td>
    </tr>
</table>
