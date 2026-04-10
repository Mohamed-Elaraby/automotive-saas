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
                    <a href="{{ route('admin.tenants.show', $subscription['tenant_id']) }}" class="btn btn-outline-primary">Open Tenant</a>
                </div>
            </div>

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
                                    <td>{{ $subscription['legacy_subscription_id'] ?: '-' }}</td>
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
                            <a href="{{ route('admin.tenants.show', $subscription['tenant_id']) }}" class="btn btn-outline-primary">Open Tenant Details</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
