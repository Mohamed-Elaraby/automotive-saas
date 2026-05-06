<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('maintenance.customer_portal.payment_request_title') }} - {{ $paymentRequest->request_number }}</title>
    <style>
        body { margin: 0; font-family: DejaVu Sans, Arial, sans-serif; background: #f8fafc; color: #0f172a; }
        .wrap { max-width: 760px; margin: 0 auto; padding: 24px; }
        .header { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; margin-bottom: 20px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 18px; margin-bottom: 16px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .muted { color: #64748b; font-size: 13px; }
        .value { font-weight: 700; margin-top: 3px; }
        .amount { font-size: 34px; font-weight: 800; margin: 8px 0; }
        .badge { display: inline-block; border-radius: 999px; padding: 6px 10px; background: #dbeafe; color: #1d4ed8; font-size: 12px; font-weight: 700; }
        .button { display: inline-block; border-radius: 6px; background: #2563eb; color: #fff; text-decoration: none; padding: 10px 14px; font-weight: 700; }
        @media (max-width: 700px) { .grid { grid-template-columns: 1fr; } .header { display: block; } }
    </style>
</head>
<body>
<main class="wrap">
    <div class="header">
        <div>
            <h1>{{ __('maintenance.customer_portal.payment_request_title') }}</h1>
            <div class="muted">{{ $paymentRequest->request_number }} · {{ $paymentRequest->customer?->name }}</div>
        </div>
        <span class="badge">{{ strtoupper(str_replace('_', ' ', $paymentRequest->status)) }}</span>
    </div>

    <section class="card">
        <div class="muted">{{ __('maintenance.amount') }}</div>
        <div class="amount">{{ number_format((float) $paymentRequest->amount, 2) }} {{ $paymentRequest->currency }}</div>
        <div class="grid">
            <div><div class="muted">{{ __('maintenance.invoice') }}</div><div class="value">{{ $paymentRequest->invoice?->invoice_number }}</div></div>
            <div><div class="muted">{{ __('maintenance.payment_statuses.' . $paymentRequest->invoice?->payment_status) }}</div><div class="value">{{ strtoupper(str_replace('_', ' ', $paymentRequest->invoice?->payment_status ?? '')) }}</div></div>
            <div><div class="muted">{{ __('maintenance.vehicle') }}</div><div class="value">{{ $paymentRequest->vehicle?->make }} {{ $paymentRequest->vehicle?->model }}</div></div>
            <div><div class="muted">{{ __('tenant.plate_number') }}</div><div class="value">{{ $paymentRequest->vehicle?->plate_number ?: __('maintenance.no_plate') }}</div></div>
        </div>
    </section>

    <section class="card">
        <h2>{{ __('maintenance.customer_portal.payment_status_title') }}</h2>
        <p class="muted">{{ __('maintenance.customer_portal.payment_request_notice') }}</p>
        <div class="grid">
            <div><div class="muted">{{ __('maintenance.status') }}</div><div class="value">{{ strtoupper($paymentRequest->status) }}</div></div>
            <div><div class="muted">{{ __('maintenance.integrations.provider') }}</div><div class="value">{{ $paymentRequest->provider ?: __('maintenance.none') }}</div></div>
            <div><div class="muted">{{ __('maintenance.expires_at') }}</div><div class="value">{{ optional($paymentRequest->expires_at)->format('Y-m-d H:i') ?: __('maintenance.none') }}</div></div>
            <div><div class="muted">{{ __('maintenance.paid_at') }}</div><div class="value">{{ optional($paymentRequest->paid_at)->format('Y-m-d H:i') ?: __('maintenance.none') }}</div></div>
        </div>
    </section>

    @if($paymentRequest->workOrder?->tracking_token)
        <a class="button" href="{{ route('automotive.customer.maintenance.tracking', $paymentRequest->workOrder->tracking_token) }}">
            {{ __('maintenance.customer_portal.back_to_tracking') }}
        </a>
    @endif
</main>
</body>
</html>
