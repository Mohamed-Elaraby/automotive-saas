<table style="width:100%; border:0; border-bottom:1px solid #d1d5db; margin-bottom:0;">
    <tr>
        <td style="border:0; width:55%;">
            <strong>{{ $company['name'] ?? config('app.name') }}</strong><br>
            <span class="small muted">{{ $branch['name'] ?? '' }}</span>
        </td>
        <td style="border:0; width:45%; text-align:{{ ($document['direction'] ?? 'ltr') === 'rtl' ? 'left' : 'right' }};">
            <strong>{{ $document['document_title'] ?? '' }}</strong><br>
            <span class="small muted">{{ $document['document_number'] ?? '' }} v{{ $document['version'] ?? 1 }}</span>
        </td>
    </tr>
</table>
