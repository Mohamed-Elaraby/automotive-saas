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
                <div class="content-page-header">
                    <h5>Billing Reports</h5>
                    <p class="text-muted mb-0">
                        Central revenue snapshot, subscription health, active plan distribution, and invoice activity.
                    </p>
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
                            <div class="text-muted mb-1">Estimated MRR</div>
                            <h3 class="mb-0 text-primary">
                                {{ number_format((float) ($summary['estimated_mrr'] ?? 0), 2) }} USD
                            </h3>
                            <div class="small text-muted mt-1">
                                Active monthly paid subscriptions only
                            </div>
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
                                                <td>{{ number_format((float) $row->price, 2) }} {{ strtoupper((string) ($row->currency ?? 'USD')) }}</td>
                                                <td>{{ number_format((int) $row->active_subscriptions_count) }}</td>
                                                <td>{{ number_format((int) $row->active_tenants_count) }}</td>
                                                <td>{{ number_format((float) $row->estimated_monthly_revenue, 2) }} {{ strtoupper((string) ($row->currency ?? 'USD')) }}</td>
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
                                    No recent invoices were found in the local billing ledger.
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-striped align-middle">
                                        <thead>
                                        <tr>
                                            <th>Invoice</th>
                                            <th>Tenant</th>
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
                                                <td>
                                                    <span class="badge {{ $badgeClass }}">
                                                        {{ ucfirst($invoice['status'] ?? 'unknown') }}
                                                    </span>
                                                </td>
                                                <td>{{ number_format((float) ($invoice['total_decimal'] ?? 0), 2) }} {{ $invoice['currency'] ?? 'USD' }}</td>
                                                <td>{{ number_format((float) ($invoice['amount_paid_decimal'] ?? 0), 2) }} {{ $invoice['currency'] ?? 'USD' }}</td>
                                                <td>{{ number_format((float) ($invoice['amount_due_decimal'] ?? 0), 2) }} {{ $invoice['currency'] ?? 'USD' }}</td>
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
                                    No invoice trend data is available yet.
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Invoices</th>
                                            <th>Paid</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($monthlyInvoiceTrend as $row)
                                            <tr>
                                                <td>{{ $row['month'] }}</td>
                                                <td>{{ number_format((int) ($row['invoices_count'] ?? 0)) }}</td>
                                                <td>{{ number_format((float) ($row['amount_paid_decimal'] ?? 0), 2) }} {{ $row['currency'] ?? 'USD' }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <hr>

                                <div class="small text-muted">
                                    This trend is built from the local billing invoice ledger synced from Stripe.
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-body">
                            <h6 class="mb-3">Interpretation Notes</h6>
                            <ul class="mb-0 ps-3">
                                <li class="mb-2">Estimated MRR currently counts only active monthly paid subscriptions.</li>
                                <li class="mb-2">Trial plans are excluded from revenue estimates.</li>
                                <li class="mb-2">Canceled, past_due, suspended, and expired subscriptions are excluded from MRR in this version.</li>
                                <li class="mb-2">Recent invoices and monthly invoice trend are derived from the local billing invoice ledger synced from Stripe.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection
