<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('maintenance.customer_portal.tracking_title') }} - {{ $workOrder->work_order_number }}</title>
    <style>
        body { margin: 0; font-family: DejaVu Sans, Arial, sans-serif; background: #f8fafc; color: #0f172a; }
        .wrap { max-width: 1040px; margin: 0 auto; padding: 24px; }
        .header { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; margin-bottom: 20px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 18px; margin-bottom: 16px; }
        .grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
        .muted { color: #64748b; font-size: 13px; }
        .value { font-weight: 700; margin-top: 3px; }
        .badge { display: inline-block; border-radius: 999px; padding: 6px 10px; background: #dbeafe; color: #1d4ed8; font-size: 12px; font-weight: 700; }
        .timeline { border-inline-start: 2px solid #dbeafe; padding-inline-start: 16px; }
        .timeline-item { margin-bottom: 14px; }
        .button { display: inline-block; border-radius: 6px; background: #2563eb; color: #fff; text-decoration: none; padding: 9px 12px; font-weight: 700; }
        .button.secondary { background: #0f172a; border: 0; cursor: pointer; }
        textarea, select, input[type="number"] { width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 9px; box-sizing: border-box; }
        .alert { border-radius: 8px; padding: 12px; margin-bottom: 16px; background: #dcfce7; color: #166534; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 10px; text-align: start; }
        @media (max-width: 800px) { .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .header { display: block; } }
    </style>
</head>
<body>
<main class="wrap">
    <div class="header">
        <div>
            <h1>{{ __('maintenance.customer_portal.tracking_title') }}</h1>
            <div class="muted">{{ $workOrder->work_order_number }} · {{ $workOrder->customer?->name }}</div>
        </div>
        <span class="badge">{{ strtoupper(str_replace('_', ' ', $workOrder->status)) }}</span>
    </div>

    @if(session('success'))
        <div class="alert">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert" style="background:#fee2e2;color:#991b1b;">{{ $errors->first() }}</div>
    @endif

    <section class="card">
        <div class="grid">
            <div><div class="muted">{{ __('maintenance.vehicle') }}</div><div class="value">{{ $workOrder->vehicle?->make }} {{ $workOrder->vehicle?->model }}</div></div>
            <div><div class="muted">{{ __('tenant.plate_number') }}</div><div class="value">{{ $workOrder->vehicle?->plate_number ?: __('maintenance.no_plate') }}</div></div>
            <div><div class="muted">{{ __('maintenance.expected_delivery_at') }}</div><div class="value">{{ optional($workOrder->expected_delivery_at)->format('Y-m-d H:i') ?: __('maintenance.none') }}</div></div>
            <div><div class="muted">{{ __('maintenance.payment_statuses.' . $workOrder->payment_status) }}</div><div class="value">{{ strtoupper(str_replace('_', ' ', $workOrder->payment_status)) }}</div></div>
        </div>
        @if($workOrder->customer_visible_notes)
            <p>{{ $workOrder->customer_visible_notes }}</p>
        @endif
    </section>

    <section class="card">
        <h2>{{ __('maintenance.estimates') }}</h2>
        @forelse($estimates as $estimate)
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;border-bottom:1px solid #e5e7eb;padding:10px 0;">
                <div>
                    <strong>{{ $estimate->estimate_number }}</strong>
                    <div class="muted">{{ strtoupper(str_replace('_', ' ', $estimate->status)) }} · {{ number_format((float) $estimate->grand_total, 2) }}</div>
                </div>
                @if(in_array($estimate->status, ['sent', 'viewed'], true) && $estimate->approval_token)
                    <a class="button" href="{{ route('automotive.customer.maintenance.estimate', $estimate->approval_token) }}">{{ __('maintenance.customer_portal.review_estimate') }}</a>
                @endif
            </div>
        @empty
            <p class="muted">{{ __('maintenance.no_estimates') }}</p>
        @endforelse
    </section>

    <section class="card">
        <h2>{{ __('maintenance.profiles.invoices') }}</h2>
        @forelse($invoices as $invoice)
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;border-bottom:1px solid #e5e7eb;padding:10px 0;">
                <div>
                    <strong>{{ $invoice->invoice_number }}</strong>
                    <div class="muted">{{ strtoupper(str_replace('_', ' ', $invoice->payment_status)) }}</div>
                </div>
                <div class="value">{{ number_format((float) $invoice->paid_amount, 2) }} / {{ number_format((float) $invoice->grand_total, 2) }}</div>
            </div>
        @empty
            <p class="muted">{{ __('maintenance.integrations.no_invoices') }}</p>
        @endforelse
    </section>

    <section class="card">
        <h2>{{ __('maintenance.warranties') }}</h2>
        @forelse($workOrder->warranties as $warranty)
            <div style="display:flex;justify-content:space-between;gap:12px;border-bottom:1px solid #e5e7eb;padding:10px 0;">
                <div>
                    <strong>{{ $warranty->warranty_number }}</strong>
                    <div class="muted">{{ __('maintenance.warranty_types.' . $warranty->warranty_type) }} · {{ strtoupper(str_replace('_', ' ', $warranty->status)) }}</div>
                </div>
                <div class="muted">{{ optional($warranty->start_date)->format('Y-m-d') }} - {{ optional($warranty->end_date)->format('Y-m-d') }}</div>
            </div>
        @empty
            <p class="muted">{{ __('maintenance.no_warranties') }}</p>
        @endforelse
    </section>

    <section class="card">
        <h2>{{ __('maintenance.customer_portal.service_history') }}</h2>
        @forelse($serviceHistory as $history)
            <div style="display:flex;justify-content:space-between;gap:12px;border-bottom:1px solid #e5e7eb;padding:10px 0;">
                <strong>{{ $history->work_order_number }}</strong>
                <span class="muted">{{ strtoupper(str_replace('_', ' ', $history->status)) }} · {{ optional($history->closed_at)->format('Y-m-d') }}</span>
            </div>
        @empty
            <p class="muted">{{ __('maintenance.customer_portal.no_service_history') }}</p>
        @endforelse
    </section>

    <section class="card">
        <h2>{{ __('maintenance.timeline') }}</h2>
        <div class="timeline">
            @forelse($timeline as $entry)
                <div class="timeline-item">
                    <strong>{{ $entry->customer_visible_note ?: $entry->title }}</strong>
                    <div class="muted">{{ $entry->created_at->format('Y-m-d H:i') }}</div>
                </div>
            @empty
                <p class="muted">{{ __('maintenance.customer_portal.no_public_timeline') }}</p>
            @endforelse
        </div>
    </section>

    <section class="card">
        <h2>{{ __('maintenance.customer_portal.submit_complaint') }}</h2>
        <form method="POST" action="{{ route('automotive.customer.maintenance.complaints.store', $trackingToken) }}">
            @csrf
            <div class="grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                <div>
                    <label class="muted">{{ __('maintenance.severity') }}</label>
                    <select name="severity" required>
                        @foreach(['low','medium','high','urgent'] as $severity)
                            <option value="{{ $severity }}">{{ __('maintenance.' . $severity) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="muted">{{ __('maintenance.customer_portal.customer_note') }}</label>
                    <textarea name="customer_visible_note" rows="3" required></textarea>
                </div>
            </div>
            <div style="margin-top:12px;"><button class="button secondary" type="submit">{{ __('maintenance.customer_portal.submit_complaint') }}</button></div>
        </form>
    </section>

    <section class="card">
        <h2>{{ __('maintenance.customer_portal.feedback_title') }}</h2>
        <form method="POST" action="{{ route('automotive.customer.maintenance.feedback.store', $trackingToken) }}">
            @csrf
            <div class="grid" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
                <div>
                    <label class="muted">{{ __('maintenance.customer_portal.feedback_type') }}</label>
                    <select name="feedback_type" required>
                        @foreach(['delivery','service','follow_up','general'] as $type)
                            <option value="{{ $type }}">{{ __('maintenance.customer_portal.feedback_types.' . $type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="muted">{{ __('maintenance.customer_portal.rating') }}</label>
                    <input type="number" name="rating" min="1" max="5" value="5" required>
                </div>
                <div>
                    <label class="muted">{{ __('maintenance.customer_portal.customer_note') }}</label>
                    <textarea name="customer_visible_note" rows="3"></textarea>
                </div>
            </div>
            <div style="margin-top:12px;"><button class="button secondary" type="submit">{{ __('maintenance.customer_portal.submit_feedback') }}</button></div>
        </form>
    </section>
</main>
</body>
</html>
