<?php $page = 'admin-tenant-product-subscription-show'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    @php
        $statusBadgeClass = match (strtolower((string) ($subscription['status'] ?? ''))) {
            'active' => 'bg-success',
            'trialing' => 'bg-info text-dark',
            'past_due' => 'bg-warning text-dark',
            'suspended' => 'bg-danger',
            'canceled', 'cancelled' => 'bg-secondary',
            'expired' => 'bg-dark',
            default => 'bg-light text-dark',
        };

        $yesNoBadge = function (bool $value): string {
            return $value
                ? '<span class="badge bg-success">Yes</span>'
                : '<span class="badge bg-danger">No</span>';
        };

        $productName = $subscription['product_name']
            ?: $subscription['product_slug']
            ?: $subscription['product_code']
            ?: ('Product #' . ($subscription['product_id'] ?? '?'));

        $planName = $subscription['plan_name']
            ?: $subscription['plan_slug']
            ?: ($subscription['plan_id'] ? ('Plan #' . $subscription['plan_id']) : '-');

        $hintAlertClass = function (string $severity): string {
            return match ($severity) {
                'success' => 'alert-success',
                'warning' => 'alert-warning',
                'error' => 'alert-danger',
                default => 'alert-info',
            };
        };

        $canSyncFromStripe = ($subscription['gateway'] ?? null) === 'stripe'
            || !empty($subscription['gateway_subscription_id'])
            || !empty($subscription['gateway_customer_id'])
            || !empty($subscription['gateway_checkout_session_id']);
    @endphp

    <div class="page-wrapper">
        <div class="content">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>Product Subscription Details</h5>
                    <p class="text-muted mb-0">Operational snapshot for a single tenant product subscription record.</p>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('admin.tenants.product-subscriptions.index', ['tenant_id' => $subscription['tenant_id']]) }}" class="btn btn-light">Back</a>
                    @if($canSyncFromStripe)
                        <form method="POST" action="{{ route('admin.tenants.product-subscriptions.sync-stripe', $subscription['id']) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                Sync From Stripe
                            </button>
                        </form>
                    @endif
                    @if(!empty($subscription['legacy_subscription_id']))
                        <a href="{{ route('admin.subscriptions.show', $subscription['legacy_subscription_id']) }}" class="btn btn-primary">Open Legacy Subscription</a>
                    @endif
                    <a href="{{ route('admin.tenants.show', $subscription['tenant_id']) }}" class="btn btn-outline-primary">Open Tenant</a>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="row mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted mb-1">Record ID</div>
                            <h6 class="mb-0">{{ $subscription['id'] }}</h6>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted mb-1">Tenant</div>
                            <h6 class="mb-0">{{ $subscription['tenant_id'] }}</h6>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted mb-1">Product</div>
                            <h6 class="mb-0">{{ $productName }}</h6>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted mb-1">Status</div>
                            <span class="badge {{ $statusBadgeClass }}">
                                {{ !empty($subscription['status']) ? strtoupper(str_replace('_', ' ', $subscription['status'])) : 'UNKNOWN' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-8">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">Subscription Overview</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <tbody>
                                <tr>
                                    <th style="width: 240px;">Record ID</th>
                                    <td>{{ $subscription['id'] }}</td>
                                </tr>
                                <tr>
                                    <th>Tenant ID</th>
                                    <td>{{ $subscription['tenant_id'] }}</td>
                                </tr>
                                <tr>
                                    <th>Product</th>
                                    <td>
                                        {{ $productName }}
                                        @if(!empty($subscription['product_code']))
                                            <div class="small text-muted">{{ $subscription['product_code'] }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Plan</th>
                                    <td>
                                        {{ $planName }}
                                        <div class="small text-muted">
                                            {{ $subscription['plan_billing_period'] ? strtoupper((string) $subscription['plan_billing_period']) : '-' }}
                                            @if($subscription['plan_price'] !== null)
                                                | {{ number_format((float) $subscription['plan_price'], 2) }} {{ strtoupper((string) ($subscription['plan_currency'] ?? 'USD')) }}
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td>
                                        <span class="badge {{ $statusBadgeClass }}">
                                            {{ !empty($subscription['status']) ? strtoupper(str_replace('_', ' ', $subscription['status'])) : 'UNKNOWN' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Gateway</th>
                                    <td>{{ $subscription['gateway'] ? strtoupper((string) $subscription['gateway']) : '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Legacy Subscription ID</th>
                                    <td>
                                        @if(!empty($subscription['legacy_subscription_id']))
                                            <a href="{{ route('admin.subscriptions.show', $subscription['legacy_subscription_id']) }}">
                                                {{ $subscription['legacy_subscription_id'] }}
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>External ID</th>
                                    <td>{{ $subscription['external_id'] ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Stripe Customer ID</th>
                                    <td>{{ $subscription['gateway_customer_id'] ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Stripe Subscription ID</th>
                                    <td>{{ $subscription['gateway_subscription_id'] ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Stripe Checkout Session ID</th>
                                    <td>{{ $subscription['gateway_checkout_session_id'] ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Stripe Price ID</th>
                                    <td>{{ $subscription['gateway_price_id'] ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Last Synced From Stripe At</th>
                                    <td>{{ $subscription['last_synced_from_stripe_at'] ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Last Sync Status</th>
                                    <td>{{ $subscription['last_sync_status'] ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Last Sync Error</th>
                                    <td>{{ $subscription['last_sync_error'] ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Payment Failures Count</th>
                                    <td>{{ (int) ($subscription['payment_failures_count'] ?? 0) }}</td>
                                </tr>
                                <tr>
                                    <th>Created At</th>
                                    <td>{{ $subscription['created_at'] ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Updated At</th>
                                    <td>{{ $subscription['updated_at'] ?: '-' }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">Lifecycle Timeline</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <tbody>
                                <tr>
                                    <th style="width: 240px;">Trial Ends At</th>
                                    <td>{{ $subscription['trial_ends_at'] ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Grace Ends At</th>
                                    <td>{{ $subscription['grace_ends_at'] ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Last Payment Failed At</th>
                                    <td>{{ $subscription['last_payment_failed_at'] ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Past Due Started At</th>
                                    <td>{{ $subscription['past_due_started_at'] ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Suspended At</th>
                                    <td>{{ $subscription['suspended_at'] ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Cancelled At</th>
                                    <td>{{ $subscription['cancelled_at'] ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Ends At</th>
                                    <td>{{ $subscription['ends_at'] ?: '-' }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">Latest Synced Invoice</h6>
                        </div>
                        <div class="card-body">
                            @if($latestInvoice)
                                <table class="table table-sm table-striped align-middle mb-0">
                                    <tbody>
                                    <tr>
                                        <th style="width: 240px;">Invoice ID</th>
                                        <td>{{ $latestInvoice['gateway_invoice_id'] ?: ('Local #' . $latestInvoice['id']) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Invoice Number</th>
                                        <td>{{ $latestInvoice['invoice_number'] ?: '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td>{{ $latestInvoice['status'] ?: '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Billing Reason</th>
                                        <td>{{ $latestInvoice['billing_reason'] ?: '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Total</th>
                                        <td>
                                            {{ $latestInvoice['total_decimal'] !== null ? number_format((float) $latestInvoice['total_decimal'], 2) : '-' }}
                                            {{ strtoupper((string) ($latestInvoice['currency'] ?? '')) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Amount Paid</th>
                                        <td>
                                            {{ $latestInvoice['amount_paid_decimal'] !== null ? number_format((float) $latestInvoice['amount_paid_decimal'], 2) : '-' }}
                                            {{ strtoupper((string) ($latestInvoice['currency'] ?? '')) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Amount Due</th>
                                        <td>
                                            {{ $latestInvoice['amount_due_decimal'] !== null ? number_format((float) $latestInvoice['amount_due_decimal'], 2) : '-' }}
                                            {{ strtoupper((string) ($latestInvoice['currency'] ?? '')) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Issued At</th>
                                        <td>{{ $latestInvoice['issued_at'] ?: '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Paid At</th>
                                        <td>{{ $latestInvoice['paid_at'] ?: '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Links</th>
                                        <td>
                                            <div class="d-flex flex-wrap gap-2">
                                                @if(!empty($latestInvoice['hosted_invoice_url']))
                                                    <a href="{{ $latestInvoice['hosted_invoice_url'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">
                                                        Hosted Invoice
                                                    </a>
                                                @endif
                                                @if(!empty($latestInvoice['invoice_pdf']))
                                                    <a href="{{ $latestInvoice['invoice_pdf'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary">
                                                        Invoice PDF
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            @else
                                <div class="alert alert-warning mb-0">
                                    No synced invoice snapshot was found for this product subscription yet.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">Tenant Snapshot</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm align-middle mb-0">
                                <tbody>
                                <tr>
                                    <th style="width: 180px;">Tenant ID</th>
                                    <td>{{ $subscription['tenant_id'] }}</td>
                                </tr>
                                <tr>
                                    <th>Company</th>
                                    <td>{{ $ownerSnapshot['company_name'] ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Owner Name</th>
                                    <td>{{ $ownerSnapshot['owner_name'] ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Owner Email</th>
                                    <td>{{ $ownerSnapshot['owner_email'] ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Phone</th>
                                    <td>{{ $ownerSnapshot['phone'] ?: '-' }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">Health Hints</h6>
                        </div>
                        <div class="card-body">
                            @if(!empty($healthHints))
                                <div class="d-grid gap-2">
                                    @foreach($healthHints as $hint)
                                        <div class="alert {{ $hintAlertClass((string) ($hint['severity'] ?? 'info')) }} mb-0">
                                            {{ $hint['message'] ?? '' }}
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="alert alert-light mb-0">
                                    No immediate health hints were generated for this record.
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">Diagnostics</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm align-middle mb-0">
                                <tbody>
                                <tr>
                                    <th style="width: 220px;">Has Product</th>
                                    <td>{!! $yesNoBadge((bool) ($diagnostics['has_product'] ?? false)) !!}</td>
                                </tr>
                                <tr>
                                    <th>Has Plan</th>
                                    <td>{!! $yesNoBadge((bool) ($diagnostics['has_plan'] ?? false)) !!}</td>
                                </tr>
                                <tr>
                                    <th>Has Gateway</th>
                                    <td>{!! $yesNoBadge((bool) ($diagnostics['has_gateway'] ?? false)) !!}</td>
                                </tr>
                                <tr>
                                    <th>Stripe-linked</th>
                                    <td>{!! $yesNoBadge((bool) ($diagnostics['is_stripe_linked'] ?? false)) !!}</td>
                                </tr>
                                <tr>
                                    <th>Stripe Customer ID</th>
                                    <td>{!! $yesNoBadge((bool) ($diagnostics['has_gateway_customer_id'] ?? false)) !!}</td>
                                </tr>
                                <tr>
                                    <th>Stripe Subscription ID</th>
                                    <td>{!! $yesNoBadge((bool) ($diagnostics['has_gateway_subscription_id'] ?? false)) !!}</td>
                                </tr>
                                <tr>
                                    <th>Checkout Session ID</th>
                                    <td>{!! $yesNoBadge((bool) ($diagnostics['has_gateway_checkout_session_id'] ?? false)) !!}</td>
                                </tr>
                                <tr>
                                    <th>Stripe Price ID</th>
                                    <td>{!! $yesNoBadge((bool) ($diagnostics['has_gateway_price_id'] ?? false)) !!}</td>
                                </tr>
                                <tr>
                                    <th>Last Sync Timestamp</th>
                                    <td>{!! $yesNoBadge((bool) ($diagnostics['has_last_sync_timestamp'] ?? false)) !!}</td>
                                </tr>
                                <tr>
                                    <th>Last Sync Status</th>
                                    <td>{{ $diagnostics['last_sync_status'] ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Last Sync Error</th>
                                    <td>{!! $yesNoBadge((bool) ($diagnostics['has_last_sync_error'] ?? false)) !!}</td>
                                </tr>
                                <tr>
                                    <th>Legacy Subscription ID</th>
                                    <td>{!! $yesNoBadge((bool) ($diagnostics['has_legacy_subscription_id'] ?? false)) !!}</td>
                                </tr>
                                <tr>
                                    <th>Payment Failures</th>
                                    <td>{!! $yesNoBadge((bool) ($diagnostics['has_payment_failures'] ?? false)) !!}</td>
                                </tr>
                                <tr>
                                    <th>Has End Date</th>
                                    <td>{!! $yesNoBadge((bool) ($diagnostics['has_end_date'] ?? false)) !!}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">Quick Links</h6>
                        </div>
                        <div class="card-body d-grid gap-2">
                            <a href="{{ route('admin.tenants.product-subscriptions.index', ['tenant_id' => $subscription['tenant_id']]) }}" class="btn btn-light">Back to Product Subscriptions</a>
                            @if($canSyncFromStripe)
                                <form method="POST" action="{{ route('admin.tenants.product-subscriptions.sync-stripe', $subscription['id']) }}">
                                    @csrf
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-success">Sync From Stripe</button>
                                    </div>
                                </form>
                            @endif
                            @if(!empty($subscription['legacy_subscription_id']))
                                <a href="{{ route('admin.subscriptions.show', $subscription['legacy_subscription_id']) }}" class="btn btn-primary">Open Legacy Subscription</a>
                            @endif
                            <a href="{{ route('admin.tenants.show', $subscription['tenant_id']) }}" class="btn btn-outline-primary">Open Tenant Details</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
