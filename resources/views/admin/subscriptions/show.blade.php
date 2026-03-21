@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    @php
        $status = strtolower((string) ($subscription->status ?? 'unknown'));

        $badgeClass = match ($status) {
            'active' => 'bg-success',
            'trialing' => 'bg-info text-dark',
            'past_due' => 'bg-warning text-dark',
            'suspended' => 'bg-danger',
            'canceled' => 'bg-secondary',
            'expired' => 'bg-dark',
            default => 'bg-light text-dark',
        };

        $invoiceRows = $invoiceHistory['invoices'] ?? [];
        $resolvedStatus = strtolower((string) ($resolvedState['status'] ?? 'unknown'));

        $resolvedBadgeClass = match ($resolvedStatus) {
            'active' => 'bg-success',
            'trialing' => 'bg-info text-dark',
            'grace_period' => 'bg-warning text-dark',
            'past_due' => 'bg-warning text-dark',
            'suspended' => 'bg-danger',
            'canceled' => 'bg-secondary',
            'expired' => 'bg-dark',
            default => 'bg-light text-dark',
        };

        $normalizationChanges = $normalizationPreview['changes'] ?? [];
    @endphp

    <div class="page-wrapper">
        <div class="content">

            @component('admin.layouts.components.title-meta')
                @slot('title')
                    Subscription Details
                @endslot
            @endcomponent

            <div class="page-header">
                <div class="content-page-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5>Subscription #{{ $subscription->id }}</h5>
                        <p class="text-muted mb-0">
                            Central subscription snapshot for tenant <strong>{{ $subscription->tenant_id }}</strong>.
                        </p>
                    </div>

                    <div>
                        <a href="{{ route('admin.subscriptions.index') }}" class="btn btn-outline-secondary">
                            Back to Subscriptions
                        </a>
                    </div>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="row">
                <div class="col-xl-3 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted mb-1">Tenant</div>
                            <h5 class="mb-0">{{ $subscription->tenant_id }}</h5>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted mb-1">Plan</div>
                            <h5 class="mb-0">{{ $subscription->plan_name ?: 'No plan' }}</h5>
                            @if(!empty($subscription->plan_billing_period))
                                <div class="small text-muted mt-1">
                                    {{ ucfirst((string) $subscription->plan_billing_period) }}
                                    @if(isset($subscription->plan_price))
                                        — {{ number_format((float) $subscription->plan_price, 2) }} {{ strtoupper((string) ($subscription->plan_currency ?? 'USD')) }}
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted mb-1">Stored Status</div>
                            <span class="badge {{ $badgeClass }}">
                                {{ ucfirst(str_replace('_', ' ', $status)) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted mb-1">Gateway</div>
                            <h5 class="mb-0">{{ strtoupper((string) ($subscription->gateway ?? '-')) }}</h5>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3">Admin Actions</h6>

                    <div class="d-flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('admin.subscriptions.sync-stripe', $subscription->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary">
                                Force Sync from Stripe
                            </button>
                        </form>

                        <form method="POST" action="{{ route('admin.subscriptions.refresh-state', $subscription->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary">
                                Refresh Local Billing State
                            </button>
                        </form>

                        <form method="POST" action="{{ route('admin.subscriptions.normalize-lifecycle', $subscription->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary">
                                Normalize Lifecycle Fields
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3">Lifecycle Normalization Preview</h6>

                    @if(empty($normalizationChanges))
                        <div class="alert alert-light mb-0">
                            No lifecycle normalization changes are currently needed for this subscription.
                        </div>
                    @else
                        <div class="alert alert-warning">
                            The following fields can be normalized safely based on the current stored status.
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Field</th>
                                    <th>Normalized Value</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($normalizationChanges as $field => $value)
                                    <tr>
                                        <td>{{ $field }}</td>
                                        <td>{{ is_null($value) ? 'NULL' : $value }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3">Resolved Billing State</h6>

                    <div class="row">
                        <div class="col-md-4">
                            <p class="mb-2">
                                <strong>Resolved Status:</strong><br>
                                <span class="badge {{ $resolvedBadgeClass }}">
                                    {{ ucfirst(str_replace('_', ' ', (string) ($resolvedState['status'] ?? 'unknown'))) }}
                                </span>
                            </p>
                        </div>

                        <div class="col-md-4">
                            <p class="mb-2">
                                <strong>Allow Access:</strong><br>
                                {{ !empty($resolvedState['allow_access']) ? 'Yes' : 'No' }}
                            </p>
                        </div>

                        <div class="col-md-4">
                            <p class="mb-2">
                                <strong>Is Trial:</strong><br>
                                {{ !empty($resolvedState['is_trial']) ? 'Yes' : 'No' }}
                            </p>
                        </div>
                    </div>

                    <p class="mb-2">
                        <strong>Resolved Message:</strong><br>
                        {{ $resolvedState['message'] ?? '-' }}
                    </p>

                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-2">
                                <strong>Resolved Period Ends At:</strong><br>
                                {{ !empty($resolvedState['period_ends_at']) ? $resolvedState['period_ends_at']->format('Y-m-d H:i:s') : '-' }}
                            </p>
                        </div>

                        <div class="col-md-6">
                            <p class="mb-2">
                                <strong>Resolved Grace Ends At:</strong><br>
                                {{ !empty($resolvedState['grace_ends_at']) ? $resolvedState['grace_ends_at']->format('Y-m-d H:i:s') : '-' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="mb-3">Subscription Snapshot</h6>

                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <tbody>
                                    <tr>
                                        <th width="35%">Subscription ID</th>
                                        <td>{{ $subscription->id }}</td>
                                    </tr>
                                    <tr>
                                        <th>Tenant ID</th>
                                        <td>{{ $subscription->tenant_id }}</td>
                                    </tr>
                                    <tr>
                                        <th>Plan ID</th>
                                        <td>{{ $subscription->plan_id ?: '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Plan Name</th>
                                        <td>{{ $subscription->plan_name ?: '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td>{{ ucfirst(str_replace('_', ' ', $status)) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Gateway</th>
                                        <td>{{ strtoupper((string) ($subscription->gateway ?? '-')) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Payment Failures Count</th>
                                        <td>{{ (int) ($subscription->payment_failures_count ?? 0) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Created At</th>
                                        <td>{{ !empty($subscription->created_at) ? \Carbon\Carbon::parse($subscription->created_at)->format('Y-m-d H:i:s') : '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Updated At</th>
                                        <td>{{ !empty($subscription->updated_at) ? \Carbon\Carbon::parse($subscription->updated_at)->format('Y-m-d H:i:s') : '-' }}</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="mb-3">Gateway References</h6>

                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <tbody>
                                    <tr>
                                        <th width="35%">Gateway Customer ID</th>
                                        <td><span class="small">{{ $subscription->gateway_customer_id ?: '-' }}</span></td>
                                    </tr>
                                    <tr>
                                        <th>Gateway Subscription ID</th>
                                        <td><span class="small">{{ $subscription->gateway_subscription_id ?: '-' }}</span></td>
                                    </tr>
                                    <tr>
                                        <th>Gateway Price ID</th>
                                        <td><span class="small">{{ $subscription->gateway_price_id ?: '-' }}</span></td>
                                    </tr>
                                    <tr>
                                        <th>Checkout Session ID</th>
                                        <td><span class="small">{{ $subscription->gateway_checkout_session_id ?: '-' }}</span></td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3">Billing Lifecycle Fields</h6>

                    <div class="row">
                        <div class="col-md-4">
                            <p class="mb-2"><strong>Trial Ends At:</strong><br>{{ !empty($subscription->trial_ends_at) ? \Carbon\Carbon::parse($subscription->trial_ends_at)->format('Y-m-d H:i:s') : '-' }}</p>
                            <p class="mb-2"><strong>Grace Ends At:</strong><br>{{ !empty($subscription->grace_ends_at) ? \Carbon\Carbon::parse($subscription->grace_ends_at)->format('Y-m-d H:i:s') : '-' }}</p>
                            <p class="mb-2"><strong>Ends At:</strong><br>{{ !empty($subscription->ends_at) ? \Carbon\Carbon::parse($subscription->ends_at)->format('Y-m-d H:i:s') : '-' }}</p>
                        </div>

                        <div class="col-md-4">
                            <p class="mb-2"><strong>Past Due Started At:</strong><br>{{ !empty($subscription->past_due_started_at) ? \Carbon\Carbon::parse($subscription->past_due_started_at)->format('Y-m-d H:i:s') : '-' }}</p>
                            <p class="mb-2"><strong>Last Payment Failed At:</strong><br>{{ !empty($subscription->last_payment_failed_at) ? \Carbon\Carbon::parse($subscription->last_payment_failed_at)->format('Y-m-d H:i:s') : '-' }}</p>
                            <p class="mb-2"><strong>Suspended At:</strong><br>{{ !empty($subscription->suspended_at) ? \Carbon\Carbon::parse($subscription->suspended_at)->format('Y-m-d H:i:s') : '-' }}</p>
                        </div>

                        <div class="col-md-4">
                            <p class="mb-2"><strong>Cancelled At:</strong><br>{{ !empty($subscription->cancelled_at) ? \Carbon\Carbon::parse($subscription->cancelled_at)->format('Y-m-d H:i:s') : '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3">Invoice History</h6>

                    @if(!($invoiceHistory['ok'] ?? true))
                        <div class="alert alert-warning mb-0">
                            {{ $invoiceHistory['message'] ?? 'Unable to load invoice history right now.' }}
                        </div>
                    @elseif(empty($subscription->gateway_customer_id))
                        <div class="alert alert-info mb-0">
                            No Stripe customer is linked to this subscription yet.
                        </div>
                    @elseif(empty($invoiceRows))
                        <div class="alert alert-light mb-0">
                            No invoices were found for this subscription/customer.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <thead>
                                <tr>
                                    <th>Invoice</th>
                                    <th>Status</th>
                                    <th>Total</th>
                                    <th>Paid</th>
                                    <th>Due</th>
                                    <th>Created</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($invoiceRows as $invoice)
                                    @php
                                        $invoiceStatus = strtolower((string) ($invoice['status'] ?? 'unknown'));

                                        $invoiceBadgeClass = match ($invoiceStatus) {
                                            'paid' => 'bg-success',
                                            'open' => 'bg-warning text-dark',
                                            'draft' => 'bg-secondary',
                                            'void' => 'bg-dark',
                                            'uncollectible' => 'bg-danger',
                                            default => 'bg-light text-dark',
                                        };
                                    @endphp

                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $invoice['number'] ?? ($invoice['id'] ?? 'Stripe invoice') }}</div>
                                            <div class="small text-muted">{{ $invoice['id'] ?? '-' }}</div>
                                        </td>
                                        <td>
                                                <span class="badge {{ $invoiceBadgeClass }}">
                                                    {{ ucfirst($invoice['status'] ?? 'unknown') }}
                                                </span>
                                        </td>
                                        <td>{{ number_format((float) ($invoice['total_decimal'] ?? 0), 2) }} {{ $invoice['currency'] ?? 'USD' }}</td>
                                        <td>{{ number_format((float) ($invoice['amount_paid_decimal'] ?? 0), 2) }} {{ $invoice['currency'] ?? 'USD' }}</td>
                                        <td>{{ number_format((float) ($invoice['amount_due_decimal'] ?? 0), 2) }} {{ $invoice['currency'] ?? 'USD' }}</td>
                                        <td>{{ !empty($invoice['created_at']) ? \Carbon\Carbon::createFromTimestamp($invoice['created_at'])->format('Y-m-d H:i') : '-' }}</td>
                                        <td class="text-end">
                                            <div class="d-flex flex-wrap gap-2 justify-content-end">
                                                @if(!empty($invoice['hosted_invoice_url']))
                                                    <a
                                                        href="{{ $invoice['hosted_invoice_url'] }}"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="btn btn-sm btn-outline-primary"
                                                    >
                                                        View
                                                    </a>
                                                @endif

                                                @if(!empty($invoice['invoice_pdf']))
                                                    <a
                                                        href="{{ $invoice['invoice_pdf'] }}"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="btn btn-sm btn-outline-secondary"
                                                    >
                                                        PDF
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
