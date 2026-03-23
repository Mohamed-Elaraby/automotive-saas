<?php $page = 'billing-reports'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">

            @component('admin.layouts.components.title-meta')
                @slot('title')
                    Billing Reports
                @endslot
            @endcomponent

            <div class="page-header">
                <div class="content-page-header d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <h5>Billing Reports</h5>
                        <p class="text-muted mb-0">
                            Central revenue snapshot, subscription health, active plan distribution, and invoice activity.
                        </p>
                    </div>

                    <div>
                        <a
                            href="{{ route('admin.reports.billing.export-csv', request()->query()) }}"
                            class="btn btn-outline-primary"
                        >
                            Export CSV
                        </a>
                    </div>
                </div>
            </div>

            @php
                $filters = is_array($filters ?? null) ? $filters : [];
                $filterOptions = is_array($filterOptions ?? null) ? $filterOptions : [];
                $statusOptions = $filterOptions['statuses'] ?? collect();
                $gatewayOptions = $filterOptions['gateways'] ?? collect();
                $currencyOptions = $filterOptions['currencies'] ?? collect();
                $monthOptions = $filterOptions['months'] ?? collect();
                $estimatedMrrByCurrency = $summary['estimated_mrr_by_currency'] ?? collect();
            @endphp

            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h6 class="mb-0">Invoice Filters</h6>

                        <a
                            href="{{ route('admin.reports.billing.export-csv', request()->query()) }}"
                            class="btn btn-sm btn-outline-dark"
                        >
                            Export Current Filter Set
                        </a>
                    </div>

                    <form method="GET" action="{{ route('admin.reports.billing') }}">
                        <div class="row g-3">
                            <div class="col-xl-3 col-md-6">
                                <label class="form-label">Tenant ID</label>
                                <input
                                    type="text"
                                    name="tenant_id"
                                    value="{{ $filters['tenant_id'] ?? '' }}"
                                    class="form-control"
                                    placeholder="Search tenant id"
                                >
                            </div>

                            <div class="col-xl-2 col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All</option>
                                    @foreach($statusOptions as $status)
                                        <option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>
                                            {{ ucfirst((string) $status) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-xl-2 col-md-6">
                                <label class="form-label">Gateway</label>
                                <select name="gateway" class="form-select">
                                    <option value="">All</option>
                                    @foreach($gatewayOptions as $gateway)
                                        <option value="{{ $gateway }}" {{ ($filters['gateway'] ?? '') === $gateway ? 'selected' : '' }}>
                                            {{ strtoupper((string) $gateway) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-xl-2 col-md-6">
                                <label class="form-label">Currency</label>
                                <select name="currency" class="form-select">
                                    <option value="">All</option>
                                    @foreach($currencyOptions as $currency)
                                        <option value="{{ $currency }}" {{ strtoupper((string) ($filters['currency'] ?? '')) === strtoupper((string) $currency) ? 'selected' : '' }}>
                                            {{ strtoupper((string) $currency) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-xl-3 col-md-6">
                                <label class="form-label">Month</label>
                                <select name="month" class="form-select">
                                    <option value="">All</option>
                                    @foreach($monthOptions as $month)
                                        <option value="{{ $month }}" {{ ($filters['month'] ?? '') === $month ? 'selected' : '' }}>
                                            {{ $month }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12 d-flex flex-wrap gap-2">
                                <button type="submit" class="btn btn-primary">
                                    Apply Filters
                                </button>

                                <a href="{{ route('admin.reports.billing') }}" class="btn btn-light">
                                    Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-3 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted mb-1">Total Subscriptions</div>
                            <h3 class="mb-0">{{ number_format((int) ($summary['total_subscriptions'] ?? 0)) }}</h3>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted mb-1">Active Paid</div>
                            <h3 class="mb-0 text-success">{{ number_format((int) ($summary['active_paid_subscriptions'] ?? 0)) }}</h3>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted mb-1">Trialing</div>
                            <h3 class="mb-0 text-info">{{ number_format((int) ($summary['trialing_subscriptions'] ?? 0)) }}</h3>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted mb-2">Estimated MRR</div>

                            @if(collect($estimatedMrrByCurrency)->isEmpty())
                                <div class="text-muted">No monthly recurring revenue found.</div>
                            @else
                                @foreach($estimatedMrrByCurrency as $mrrRow)
                                    <div class="mb-2">
                                        <div class="fw-semibold">
                                            {{ number_format((float) ($mrrRow['estimated_mrr'] ?? 0), (int) ($mrrRow['decimal_places'] ?? 2)) }}
                                            {{ $mrrRow['currency'] ?? '' }}
                                        </div>
                                        <div class="small text-muted">
                                            {{ $mrrRow['currency_name'] ?? ($mrrRow['currency'] ?? '') }}
                                        </div>
                                    </div>
                                @endforeach
                                <div class="small text-muted mt-2">
                                    Active monthly paid subscriptions only
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-4 col-sm-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted mb-1">Past Due</div>
                            <h4 class="mb-0 text-warning">{{ number_format((int) ($summary['past_due_subscriptions'] ?? 0)) }}</h4>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-4 col-sm-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted mb-1">Suspended</div>
                            <h4 class="mb-0 text-danger">{{ number_format((int) ($summary['suspended_subscriptions'] ?? 0)) }}</h4>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-4 col-sm-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted mb-1">Canceled</div>
                            <h4 class="mb-0 text-secondary">{{ number_format((int) ($summary['canceled_subscriptions'] ?? 0)) }}</h4>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-4 col-sm-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted mb-1">Expired</div>
                            <h4 class="mb-0 text-dark">{{ number_format((int) ($summary['expired_subscriptions'] ?? 0)) }}</h4>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-8">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="mb-3">Active Plan Distribution</h6>

                            @if($activePlanDistribution->isEmpty())
                                <div class="alert alert-light mb-0">
                                    No active paid subscriptions were found.
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-striped align-middle">
                                        <thead>
                                        <tr>
                                            <th>Plan</th>
                                            <th>Billing Period</th>
                                            <th>Price</th>
                                            <th>Active Subscriptions</th>
                                            <th>Active Tenants</th>
                                            <th>Estimated Monthly Revenue</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($activePlanDistribution as $row)
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold">{{ $row->plan_name }}</div>
                                                    <div class="small text-muted">{{ $row->plan_slug }}</div>
                                                </td>
                                                <td>{{ ucfirst((string) $row->billing_period) }}</td>
                                                <td>
                                                    {{ number_format((float) $row->price, 2) }}
                                                    {{ strtoupper((string) ($row->currency ?? '')) }}
                                                    @if(!empty($row->currency_name))
                                                        <div class="small text-muted">{{ $row->currency_name }}</div>
                                                    @endif
                                                </td>
                                                <td>{{ number_format((int) $row->active_subscriptions_count) }}</td>
                                                <td>{{ number_format((int) $row->active_tenants_count) }}</td>
                                                <td>
                                                    {{ number_format((float) $row->estimated_monthly_revenue, 2) }}
                                                    {{ strtoupper((string) ($row->currency ?? '')) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-body">
                            <h6 class="mb-3">Recent Invoices</h6>

                            @if($recentInvoices->isEmpty())
                                <div class="alert alert-light mb-0">
                                    No recent invoices were found for the current filters.
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-striped align-middle">
                                        <thead>
                                        <tr>
                                            <th>Invoice</th>
                                            <th>Tenant</th>
                                            <th>Gateway</th>
                                            <th>Status</th>
                                            <th>Total</th>
                                            <th>Paid</th>
                                            <th>Due</th>
                                            <th>Created</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($recentInvoices as $invoice)
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
                                                    <div class="fw-semibold">{{ $invoice['number'] ?? ($invoice['id'] ?? 'Stripe invoice') }}</div>
                                                    <div class="small text-muted">{{ $invoice['id'] ?? '-' }}</div>
                                                </td>
                                                <td>{{ $invoice['tenant_id'] ?? '-' }}</td>
                                                <td>{{ $invoice['gateway'] ?? 'STRIPE' }}</td>
                                                <td>
                                                    <span class="badge {{ $badgeClass }}">
                                                        {{ ucfirst((string) ($invoice['status'] ?? 'unknown')) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    {{ number_format((float) ($invoice['total_decimal'] ?? 0), 2) }}
                                                    {{ $invoice['currency'] ?? '' }}
                                                    @if(!empty($invoice['currency_name']))
                                                        <div class="small text-muted">{{ $invoice['currency_name'] }}</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    {{ number_format((float) ($invoice['amount_paid_decimal'] ?? 0), 2) }}
                                                    {{ $invoice['currency'] ?? '' }}
                                                </td>
                                                <td>
                                                    {{ number_format((float) ($invoice['amount_due_decimal'] ?? 0), 2) }}
                                                    {{ $invoice['currency'] ?? '' }}
                                                </td>
                                                <td>
                                                    {{ !empty($invoice['created_at']) ? \Carbon\Carbon::createFromTimestamp($invoice['created_at'])->format('Y-m-d H:i') : '-' }}
                                                </td>
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

                <div class="col-xl-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="mb-3">Gateway Breakdown</h6>

                            @if($gatewayBreakdown->isEmpty())
                                <div class="alert alert-light mb-0">
                                    No gateway-linked subscriptions were found.
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                        <tr>
                                            <th>Gateway</th>
                                            <th>Subscriptions</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($gatewayBreakdown as $row)
                                            <tr>
                                                <td>{{ strtoupper((string) $row->gateway) }}</td>
                                                <td>{{ number_format((int) $row->subscriptions_count) }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-body">
                            <h6 class="mb-3">Monthly Invoice Trend</h6>

                            @if($monthlyInvoiceTrend->isEmpty())
                                <div class="alert alert-light mb-0">
                                    No invoice trend data is available for the current filters.
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Currency</th>
                                            <th>Invoices</th>
                                            <th>Total</th>
                                            <th>Paid</th>
                                            <th>Due</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($monthlyInvoiceTrend as $row)
                                            <tr>
                                                <td>{{ $row['month'] }}</td>
                                                <td>
                                                    {{ $row['currency'] ?? '' }}
                                                    @if(!empty($row['currency_name']))
                                                        <div class="small text-muted">{{ $row['currency_name'] }}</div>
                                                    @endif
                                                </td>
                                                <td>{{ number_format((int) ($row['invoices_count'] ?? 0)) }}</td>
                                                <td>{{ number_format((float) ($row['total_decimal'] ?? 0), 2) }} {{ $row['currency'] ?? '' }}</td>
                                                <td>{{ number_format((float) ($row['amount_paid_decimal'] ?? 0), 2) }} {{ $row['currency'] ?? '' }}</td>
                                                <td>{{ number_format((float) ($row['amount_due_decimal'] ?? 0), 2) }} {{ $row['currency'] ?? '' }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <hr>

                                <div class="small text-muted">
                                    This trend is built from the local billing invoice ledger synced from Stripe and grouped by month and currency.
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-body">
                            <h6 class="mb-3">Interpretation Notes</h6>
                            <ul class="mb-0 ps-3">
                                <li class="mb-2">Estimated MRR is now grouped by currency instead of assuming a single static currency.</li>
                                <li class="mb-2">Trial plans are excluded from revenue estimates.</li>
                                <li class="mb-2">Canceled, past_due, suspended, and expired subscriptions are excluded from MRR in this version.</li>
                                <li class="mb-2">Recent invoices and monthly invoice trend are derived from the local billing invoice ledger synced from Stripe.</li>
                                <li class="mb-2">CSV export respects the current report filters and downloads the current invoice slice directly from the billing report screen.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection
