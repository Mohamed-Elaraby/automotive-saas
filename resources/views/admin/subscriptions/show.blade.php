<?php $page = 'subscription-show'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">

            @component('admin.layouts.components.title-meta')
                @slot('title')
                    Subscription Details
                @endslot
            @endcomponent

            <div class="page-header">
                <div class="content-page-header">
                    <h5>Subscription Details</h5>
                    <p class="text-muted mb-0">
                        Subscription lifecycle, Stripe linkage, invoice history, and administrative actions.
                    </p>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="row">
                <div class="col-xl-8">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="mb-3">Subscription Overview</h6>

                            <div class="table-responsive">
                                <table class="table table-striped align-middle mb-0">
                                    <tbody>
                                    <tr>
                                        <th style="width: 240px;">Subscription ID</th>
                                        <td>{{ $subscription->id }}</td>
                                    </tr>
                                    <tr>
                                        <th>Tenant ID</th>
                                        <td>{{ $subscription->tenant_id }}</td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td>{{ ucfirst((string) $subscription->status) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Plan</th>
                                        <td>
                                            {{ $subscription->plan_name ?? '-' }}
                                            @if(!empty($subscription->plan_slug))
                                                <div class="small text-muted">{{ $subscription->plan_slug }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Billing Period</th>
                                        <td>{{ $subscription->plan_billing_period ? ucfirst((string) $subscription->plan_billing_period) : '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Plan Price</th>
                                        <td>
                                            @if(isset($subscription->plan_price))
                                                {{ number_format((float) $subscription->plan_price, 2) }} {{ strtoupper((string) ($subscription->plan_currency ?? 'USD')) }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Gateway</th>
                                        <td>{{ strtoupper((string) ($subscription->gateway ?? '-')) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Stripe Customer ID</th>
                                        <td>{{ $subscription->gateway_customer_id ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Stripe Subscription ID</th>
                                        <td>{{ $subscription->gateway_subscription_id ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Stripe Checkout Session ID</th>
                                        <td>{{ $subscription->gateway_checkout_session_id ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Stripe Price ID</th>
                                        <td>{{ $subscription->gateway_price_id ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Trial Ends At</th>
                                        <td>{{ $subscription->trial_ends_at ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Grace Ends At</th>
                                        <td>{{ $subscription->grace_ends_at ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Past Due Started At</th>
                                        <td>{{ $subscription->past_due_started_at ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Last Payment Failed At</th>
                                        <td>{{ $subscription->last_payment_failed_at ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Suspended At</th>
                                        <td>{{ $subscription->suspended_at ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Cancelled At</th>
                                        <td>{{ $subscription->cancelled_at ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Ends At</th>
                                        <td>{{ $subscription->ends_at ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Payment Failures Count</th>
                                        <td>{{ (int) ($subscription->payment_failures_count ?? 0) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Created At</th>
                                        <td>{{ $subscription->created_at ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Updated At</th>
                                        <td>{{ $subscription->updated_at ?? '-' }}</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-body">
                            <h6 class="mb-3">Invoice History</h6>

                            @php
                                $invoiceHistory = is_array($invoiceHistory ?? null) ? $invoiceHistory : ['ok' => false, 'invoices' => [], 'message' => null];
                                $invoices = $invoiceHistory['invoices'] ?? [];
                            @endphp

                            @if(!empty($invoiceHistory['message']))
                                <div class="alert alert-info">
                                    {{ $invoiceHistory['message'] }}
                                </div>
                            @endif

                            @if(empty($invoices))
                                <div class="alert alert-light mb-0">
                                    No invoices were found for this subscription.
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-striped align-middle mb-0">
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
                                        @foreach($invoices as $invoice)
                                            @php
                                                $invoiceStatus = strtolower((string) ($invoice['status'] ?? 'unknown'));

                                                $badgeClass = match ($invoiceStatus) {
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
                                                    <div class="fw-semibold">
                                                        {{ $invoice['number'] ?? ($invoice['id'] ?? 'Stripe invoice') }}
                                                    </div>
                                                    <div class="small text-muted">
                                                        {{ $invoice['id'] ?? '-' }}
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge {{ $badgeClass }}">
                                                        {{ ucfirst((string) ($invoice['status'] ?? 'unknown')) }}
                                                    </span>
                                                </td>
                                                <td>{{ number_format((float) ($invoice['total_decimal'] ?? 0), 2) }} {{ strtoupper((string) ($invoice['currency'] ?? 'USD')) }}</td>
                                                <td>{{ number_format((float) ($invoice['amount_paid_decimal'] ?? 0), 2) }} {{ strtoupper((string) ($invoice['currency'] ?? 'USD')) }}</td>
                                                <td>{{ number_format((float) ($invoice['amount_due_decimal'] ?? 0), 2) }} {{ strtoupper((string) ($invoice['currency'] ?? 'USD')) }}</td>
                                                <td>
                                                    {{ !empty($invoice['created_at']) ? \Carbon\Carbon::createFromTimestamp($invoice['created_at'])->format('Y-m-d H:i') : '-' }}
                                                </td>
                                                <td class="text-end">
                                                    <div class="d-flex flex-wrap gap-2 justify-content-end">
                                                        @if(!empty($invoice['hosted_invoice_url']))
                                                            <a href="{{ $invoice['hosted_invoice_url'] }}"
                                                               class="btn btn-sm btn-outline-primary"
                                                               target="_blank"
                                                               rel="noopener noreferrer">
                                                                View
                                                            </a>
                                                        @endif

                                                        @if(!empty($invoice['invoice_pdf']))
                                                            <a href="{{ $invoice['invoice_pdf'] }}"
                                                               class="btn btn-sm btn-outline-secondary"
                                                               target="_blank"
                                                               rel="noopener noreferrer">
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

                <div class="col-xl-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="mb-3">Resolved Billing State</h6>

                            @php
                                $resolvedState = is_array($resolvedState ?? null) ? $resolvedState : [];
                            @endphp

                            <table class="table table-sm align-middle mb-0">
                                <tbody>
                                <tr>
                                    <th style="width: 180px;">Resolved Status</th>
                                    <td>{{ ucfirst(str_replace('_', ' ', (string) ($resolvedState['status'] ?? 'unknown'))) }}</td>
                                </tr>
                                <tr>
                                    <th>Reason</th>
                                    <td>{{ $resolvedState['reason'] ?? '-' }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-body">
                            <h6 class="mb-3">Normalization Preview</h6>

                            @php
                                $normalizationPreview = is_array($normalizationPreview ?? null) ? $normalizationPreview : [];
                                $previewChanges = $normalizationPreview['changes'] ?? [];
                            @endphp

                            <div class="small text-muted mb-3">
                                Review the current lifecycle normalization preview before applying any corrections.
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm table-striped align-middle mb-0">
                                    <tbody>
                                    <tr>
                                        <th style="width: 180px;">OK</th>
                                        <td>
                                            @if(($normalizationPreview['ok'] ?? false) === true)
                                                <span class="badge bg-success">Yes</span>
                                            @else
                                                <span class="badge bg-danger">No</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Applied</th>
                                        <td>
                                            @if(($normalizationPreview['applied'] ?? false) === true)
                                                <span class="badge bg-success">Yes</span>
                                            @else
                                                <span class="badge bg-secondary">No</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Subscription ID</th>
                                        <td>{{ $normalizationPreview['subscription_id'] ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td>{{ ucfirst(str_replace('_', ' ', (string) ($normalizationPreview['status'] ?? 'unknown'))) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Message</th>
                                        <td>{{ $normalizationPreview['message'] ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Changes Count</th>
                                        <td>{{ is_array($previewChanges) ? count($previewChanges) : 0 }}</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>

                            @if(!empty($previewChanges) && is_array($previewChanges))
                                <div class="mt-3">
                                    <h6 class="mb-2">Pending Changes</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered align-middle mb-0">
                                            <thead>
                                            <tr>
                                                <th>Field</th>
                                                <th>From</th>
                                                <th>To</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @foreach($previewChanges as $change)
                                                <tr>
                                                    <td>{{ $change['field'] ?? '-' }}</td>
                                                    <td>{{ $change['from'] ?? '-' }}</td>
                                                    <td>{{ $change['to'] ?? '-' }}</td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-body">
                            <h6 class="mb-3">Advanced Control</h6>

                            @php
                                $stripeLinkDiagnostics = is_array($stripeLinkDiagnostics ?? null) ? $stripeLinkDiagnostics : ['signals' => []];
                            @endphp

                            @if($isStripeLinked)
                                <div class="alert alert-warning">
                                    {{ $stripeLinkDiagnostics['reason'] ?? 'This subscription is Stripe-linked. Manual lifecycle forcing and local timestamp overrides are blocked to avoid drift from Stripe.' }}
                                </div>
                            @else
                                <div class="alert alert-info">
                                    {{ $stripeLinkDiagnostics['reason'] ?? 'Manual local controls are currently allowed.' }}
                                </div>
                            @endif

                            <div class="table-responsive mb-4">
                                <table class="table table-sm table-striped align-middle mb-0">
                                    <tbody>
                                    <tr>
                                        <th style="width: 220px;">Gateway Value</th>
                                        <td>{{ $stripeLinkDiagnostics['signals']['gateway'] ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Has Customer ID</th>
                                        <td>{{ !empty($stripeLinkDiagnostics['signals']['has_gateway_customer_id']) ? 'Yes' : 'No' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Has Subscription ID</th>
                                        <td>{{ !empty($stripeLinkDiagnostics['signals']['has_gateway_subscription_id']) ? 'Yes' : 'No' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Has Checkout Session ID</th>
                                        <td>{{ !empty($stripeLinkDiagnostics['signals']['has_gateway_checkout_session_id']) ? 'Yes' : 'No' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Local Controls Blocked</th>
                                        <td>{{ !empty($stripeLinkDiagnostics['is_blocked']) ? 'Yes' : 'No' }}</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>

                            <form method="POST" action="{{ route('admin.subscriptions.manual-action', $subscription->id) }}" class="mb-4">
                                @csrf
                                <input type="hidden" name="action" value="force_lifecycle">

                                <div class="mb-2">
                                    <label class="form-label">Force Lifecycle State</label>
                                    <select name="target_status" class="form-select" @disabled($isStripeLinked)>
                                        @foreach($statusOptions as $statusOption)
                                            <option value="{{ $statusOption }}" @selected(($subscription->status ?? null) === $statusOption)>
                                                {{ ucfirst(str_replace('_', ' ', $statusOption)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-outline-primary w-100" @disabled($isStripeLinked)>
                                    Apply Lifecycle State
                                </button>
                            </form>

                            <div class="d-grid gap-2 mb-4">
                                <form method="POST" action="{{ route('admin.subscriptions.manual-action', $subscription->id) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="cancel">
                                    <button type="submit" class="btn btn-outline-danger w-100" @disabled($isStripeLinked)>
                                        Cancel Locally
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('admin.subscriptions.manual-action', $subscription->id) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="resume">
                                    <button type="submit" class="btn btn-outline-success w-100" @disabled($isStripeLinked)>
                                        Resume Locally
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('admin.subscriptions.manual-action', $subscription->id) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="renew">
                                    <button type="submit" class="btn btn-outline-dark w-100" @disabled($isStripeLinked)>
                                        Renew Locally
                                    </button>
                                </form>
                            </div>

                            <form method="POST" action="{{ route('admin.subscriptions.timestamps', $subscription->id) }}">
                                @csrf

                                <div class="mb-2">
                                    <label class="form-label">Trial Ends At</label>
                                    <input
                                        type="datetime-local"
                                        name="trial_ends_at"
                                        class="form-control"
                                        value="{{ !empty($subscription->trial_ends_at) ? \Carbon\Carbon::parse($subscription->trial_ends_at)->format('Y-m-d\TH:i') : '' }}"
                                        @disabled($isStripeLinked)
                                    >
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">Grace Ends At</label>
                                    <input
                                        type="datetime-local"
                                        name="grace_ends_at"
                                        class="form-control"
                                        value="{{ !empty($subscription->grace_ends_at) ? \Carbon\Carbon::parse($subscription->grace_ends_at)->format('Y-m-d\TH:i') : '' }}"
                                        @disabled($isStripeLinked)
                                    >
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Ends At</label>
                                    <input
                                        type="datetime-local"
                                        name="ends_at"
                                        class="form-control"
                                        value="{{ !empty($subscription->ends_at) ? \Carbon\Carbon::parse($subscription->ends_at)->format('Y-m-d\TH:i') : '' }}"
                                        @disabled($isStripeLinked)
                                    >
                                </div>

                                <button type="submit" class="btn btn-outline-secondary w-100" @disabled($isStripeLinked)>
                                    Update Lifecycle Dates
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-body">
                            <h6 class="mb-3">Admin Actions</h6>

                            <div class="d-grid gap-2">
                                <form method="POST" action="{{ route('admin.subscriptions.sync-stripe', $subscription->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary w-100">
                                        Sync Subscription from Stripe
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('admin.subscriptions.backfill-invoices', $subscription->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-dark w-100">
                                        Backfill Invoices from Stripe
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('admin.subscriptions.refresh-state', $subscription->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-warning w-100">
                                        Refresh Local Billing State
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('admin.subscriptions.normalize-lifecycle', $subscription->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-secondary w-100">
                                        Normalize Lifecycle Fields
                                    </button>
                                </form>

                                <a href="{{ route('admin.subscriptions.index') }}" class="btn btn-light w-100">
                                    Back to Subscriptions
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection
