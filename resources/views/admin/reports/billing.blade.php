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
                        Central revenue snapshot, subscription health, and active plan distribution.
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
                            <h6 class="mb-3">Interpretation Notes</h6>
                            <ul class="mb-0 ps-3">
                                <li class="mb-2">Estimated MRR currently counts only active monthly paid subscriptions.</li>
                                <li class="mb-2">Trial plans are excluded from revenue estimates.</li>
                                <li class="mb-2">Canceled, past_due, suspended, and expired subscriptions are excluded from MRR in this first version.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection
