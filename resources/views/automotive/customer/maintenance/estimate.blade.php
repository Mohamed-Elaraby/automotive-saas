<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('maintenance.customer_portal.estimate_title') }} - {{ $estimate->estimate_number }}</title>
    <style>
        body { margin: 0; font-family: DejaVu Sans, Arial, sans-serif; background: #f8fafc; color: #0f172a; }
        .wrap { max-width: 1040px; margin: 0 auto; padding: 24px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 18px; margin-bottom: 16px; }
        .grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
        .muted { color: #64748b; font-size: 13px; }
        .value { font-weight: 700; margin-top: 3px; }
        .badge { display: inline-block; border-radius: 999px; padding: 6px 10px; background: #dbeafe; color: #1d4ed8; font-size: 12px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 10px; text-align: start; vertical-align: top; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .button { border: 0; border-radius: 6px; background: #16a34a; color: #fff; padding: 10px 14px; font-weight: 700; cursor: pointer; }
        .button.reject { background: #dc2626; }
        textarea, select { width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 9px; }
        .alert { border-radius: 8px; padding: 12px; margin-bottom: 16px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        @media (max-width: 800px) { .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    </style>
</head>
<body>
<main class="wrap">
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;">
        <h1>{{ __('maintenance.customer_portal.estimate_title') }}</h1>
        <button type="button" class="button" onclick="window.print()">{{ __('maintenance.print') }}</button>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <section class="card">
        <div class="grid">
            <div><div class="muted">{{ __('maintenance.document_number') }}</div><div class="value">{{ $estimate->estimate_number }}</div></div>
            <div><div class="muted">{{ __('tenant.customer') }}</div><div class="value">{{ $estimate->customer?->name }}</div></div>
            <div><div class="muted">{{ __('maintenance.vehicle') }}</div><div class="value">{{ $estimate->vehicle?->make }} {{ $estimate->vehicle?->model }}</div></div>
            <div><div class="muted">{{ __('maintenance.status') }}</div><div class="value"><span class="badge">{{ strtoupper(str_replace('_', ' ', $estimate->status)) }}</span></div></div>
        </div>
        @if($estimate->customer_visible_notes)
            <p>{{ $estimate->customer_visible_notes }}</p>
        @endif
    </section>

    <form method="POST" action="{{ route('automotive.customer.maintenance.estimate.decision', $approvalToken) }}">
        @csrf
        <section class="card">
            <h2>{{ __('maintenance.estimate_lines') }}</h2>
            <table>
                <thead>
                <tr>
                    <th>{{ __('maintenance.approve_selected') }}</th>
                    <th>{{ __('tenant.description') }}</th>
                    <th>{{ __('maintenance.quantity') }}</th>
                    <th>{{ __('maintenance.total') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($estimate->lines as $line)
                    <tr>
                        <td>
                            <input type="checkbox" name="approved_line_ids[]" value="{{ $line->id }}" @checked($line->approval_status !== 'rejected' && in_array($estimate->status, ['sent', 'viewed'], true)) @disabled(! in_array($estimate->status, ['sent', 'viewed'], true))>
                        </td>
                        <td>
                            <strong>{{ $line->description }}</strong>
                            @if($line->notes)
                                <div class="muted">{{ $line->notes }}</div>
                            @endif
                        </td>
                        <td>{{ number_format((float) $line->quantity, 2) }}</td>
                        <td>{{ number_format((float) $line->total_price, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>

        <section class="card">
            <div class="grid">
                <div><div class="muted">{{ __('maintenance.subtotal') }}</div><div class="value">{{ number_format((float) $estimate->subtotal, 2) }}</div></div>
                <div><div class="muted">{{ __('maintenance.discount') }}</div><div class="value">{{ number_format((float) $estimate->discount_total, 2) }}</div></div>
                <div><div class="muted">{{ __('maintenance.tax') }}</div><div class="value">{{ number_format((float) $estimate->tax_total, 2) }}</div></div>
                <div><div class="muted">{{ __('maintenance.total') }}</div><div class="value">{{ number_format((float) $estimate->grand_total, 2) }}</div></div>
            </div>
            @if($estimate->terms)
                <p class="muted">{{ $estimate->terms }}</p>
            @endif
        </section>

        @if(in_array($estimate->status, ['sent', 'viewed'], true))
            <section class="card">
                <div style="margin-bottom:12px;">
                    <label><input type="checkbox" name="terms_accepted" value="1"> {{ __('maintenance.customer_portal.accept_terms') }}</label>
                </div>
                <div style="margin-bottom:12px;">
                    <label class="muted">{{ __('maintenance.customer_portal.rejection_reason') }}</label>
                    <select name="rejection_reason">
                        @foreach(['price_too_high','not_needed_now','repair_outside','needs_time','no_parts_available','not_convinced','other'] as $reason)
                            <option value="{{ $reason }}">{{ __('maintenance.rejection_reasons.'.$reason) }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="margin-bottom:12px;">
                    <label class="muted">{{ __('maintenance.customer_portal.customer_note') }}</label>
                    <textarea name="reason" rows="3"></textarea>
                </div>
                <div class="actions">
                    <button class="button" type="submit" name="decision" value="approve">{{ __('maintenance.customer_portal.approve_selected') }}</button>
                    <button class="button reject" type="submit" name="decision" value="reject">{{ __('maintenance.customer_portal.reject_all') }}</button>
                </div>
            </section>
        @endif
    </form>
</main>
</body>
</html>
