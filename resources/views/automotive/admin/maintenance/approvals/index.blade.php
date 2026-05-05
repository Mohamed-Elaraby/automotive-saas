@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper"><div class="content content-two">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div><h4 class="mb-1">{{ __('maintenance.customer_approvals') }}</h4><p class="mb-0 text-muted">{{ __('maintenance.customer_approvals_subtitle') }}</p></div>
            <a href="{{ route('automotive.admin.maintenance.index') }}" class="btn btn-outline-light">{{ __('tenant.back') }}</a>
        </div>

        <div class="row">
            <div class="col-xl-7 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.pending_estimates') }}</h5></div><div class="card-body">
                @forelse($pendingEstimates as $estimate)
                    <div class="border-bottom pb-3 mb-3">
                        <div class="d-flex justify-content-between gap-2 mb-2">
                            <div><h6 class="mb-1">{{ $estimate->estimate_number }}</h6><div class="text-muted small">{{ $estimate->customer?->name }} · {{ $estimate->vehicle?->plate_number }} · {{ number_format((float) $estimate->grand_total, 2) }}</div></div>
                            <span class="badge bg-primary">{{ strtoupper(str_replace('_', ' ', $estimate->status)) }}</span>
                        </div>
                        <form method="POST" action="{{ route('automotive.admin.maintenance.approvals.send', $estimate) }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="approval_method" value="portal">
                            <button type="submit" class="btn btn-sm btn-outline-light">{{ __('maintenance.send_for_approval') }}</button>
                        </form>
                        @if($estimate->approval_token)
                            <a href="{{ route('automotive.customer.maintenance.estimate', $estimate->approval_token) }}" target="_blank" class="btn btn-sm btn-outline-light">{{ __('maintenance.customer_portal.open_approval_link') }}</a>
                        @endif
                        @if($estimate->workOrder?->customer_tracking_token)
                            <a href="{{ route('automotive.customer.maintenance.tracking', $estimate->workOrder->customer_tracking_token) }}" target="_blank" class="btn btn-sm btn-outline-light">{{ __('maintenance.customer_portal.open_tracking_link') }}</a>
                        @endif
                        <form method="POST" action="{{ route('automotive.admin.maintenance.approvals.approve', $estimate) }}" class="mt-2">
                            @csrf
                            <input type="hidden" name="method" value="manual">
                            <input type="hidden" name="terms_accepted" value="1">
                            <div class="row g-2">
                                @foreach($estimate->lines as $line)
                                    <div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="approved_line_ids[]" value="{{ $line->id }}" checked><label class="form-check-label">{{ $line->description }} · {{ number_format((float) $line->total_price, 2) }}</label></div></div>
                                @endforeach
                                <div class="col-md-6"><select name="rejection_reason" class="form-select form-select-sm"><option value="other">{{ __('maintenance.rejection_reasons.other') }}</option><option value="price_too_high">{{ __('maintenance.rejection_reasons.price_too_high') }}</option><option value="not_needed_now">{{ __('maintenance.rejection_reasons.not_needed_now') }}</option><option value="repair_outside">{{ __('maintenance.rejection_reasons.repair_outside') }}</option><option value="needs_time">{{ __('maintenance.rejection_reasons.needs_time') }}</option></select></div>
                                <div class="col-md-6"><button type="submit" class="btn btn-sm btn-success w-100">{{ __('maintenance.approve_selected') }}</button></div>
                            </div>
                        </form>
                    </div>
                @empty
                    <p class="text-muted mb-0">{{ __('maintenance.no_pending_approvals') }}</p>
                @endforelse
            </div></div></div>
            <div class="col-xl-5 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.lost_sales') }}</h5></div><div class="card-body">
                @forelse($lostSales as $lostSale)
                    <div class="border-bottom pb-2 mb-2"><h6 class="mb-1">{{ $lostSale->item_description }}</h6><div class="text-muted small">{{ $lostSale->customer?->name }} · {{ __('maintenance.rejection_reasons.' . $lostSale->reason) }} · {{ number_format((float) $lostSale->amount, 2) }}</div></div>
                @empty
                    <p class="text-muted mb-0">{{ __('maintenance.no_lost_sales') }}</p>
                @endforelse
            </div></div></div>
        </div>

        <div class="card"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.approval_history') }}</h5></div><div class="card-body">
            @forelse($approvalRecords as $record)
                <div class="border-bottom pb-2 mb-2"><strong>{{ $record->estimate?->estimate_number ?: $record->approval_type }}</strong><div class="text-muted small">{{ $record->customer?->name }} · {{ strtoupper(str_replace('_', ' ', $record->status)) }} · {{ number_format((float) $record->approved_amount, 2) }} · {{ optional($record->approved_at)->format('Y-m-d H:i') }}</div></div>
            @empty
                <p class="text-muted mb-0">{{ __('maintenance.no_approval_history') }}</p>
            @endforelse
        </div></div>
    </div></div>
@endsection
