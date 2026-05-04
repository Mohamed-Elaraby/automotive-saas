<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('maintenance.document_verification') }}</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f5f7fb; color:#111827; margin:0; padding:32px; }
        .box { max-width:720px; margin:auto; background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:24px; }
        .muted { color:#6b7280; }
        .ok { color:#047857; }
        .bad { color:#b91c1c; }
        table { width:100%; border-collapse:collapse; margin-top:16px; }
        td { border-top:1px solid #e5e7eb; padding:10px 0; }
    </style>
</head>
<body>
<div class="box">
    <h1>{{ __('maintenance.document_verification') }}</h1>
    @if($document)
        <p class="{{ $document->cancelled_at ? 'bad' : 'ok' }}">{{ $document->cancelled_at ? __('maintenance.document_cancelled') : __('maintenance.document_valid') }}</p>
        <table>
            <tr><td>{{ __('maintenance.document_number') }}</td><td>{{ $document->document_number }}</td></tr>
            <tr><td>{{ __('maintenance.document_type') }}</td><td>{{ $document->document_type }}</td></tr>
            <tr><td>{{ __('maintenance.version') }}</td><td>{{ $document->version }}</td></tr>
            <tr><td>{{ __('maintenance.branch') }}</td><td>{{ $document->branch?->name }}</td></tr>
            <tr><td>{{ __('maintenance.generated_at') }}</td><td>{{ optional($document->generated_at)->format('Y-m-d H:i') }}</td></tr>
            <tr><td>{{ __('maintenance.verified_at') }}</td><td>{{ $verifiedAt->format('Y-m-d H:i') }}</td></tr>
        </table>
    @else
        <p class="bad">{{ __('maintenance.document_not_found') }}</p>
    @endif
</div>
</body>
</html>
