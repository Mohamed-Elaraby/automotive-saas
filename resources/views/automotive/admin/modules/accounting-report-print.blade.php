<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        body { color: #111827; font-family: Arial, sans-serif; margin: 32px; }
        h1 { font-size: 24px; margin: 0 0 6px; }
        .meta { color: #6b7280; font-size: 13px; margin-bottom: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #d1d5db; font-size: 13px; padding: 8px; text-align: left; }
        th { background: #f3f4f6; }
        @media print { body { margin: 12mm; } }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">
        {{ __('accounting.generated') }} {{ now()->format('Y-m-d H:i') }}
        @if(! empty($filters['date_from']) || ! empty($filters['date_to']))
            · {{ __('accounting.period') }} {{ $filters['date_from'] ?? '...' }} {{ __('accounting.to') }} {{ $filters['date_to'] ?? '...' }}
        @endif
    </div>

    <table>
        <thead>
            <tr>
                @foreach($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($row as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($headers) }}">{{ __('accounting.no_rows_available') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
