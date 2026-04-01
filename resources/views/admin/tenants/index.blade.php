<?php $page = 'admin-tenants-index'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    @php
        $statusOptions = [
            '' => 'All Subscription Statuses',
            'trialing' => 'Trialing',
            'active' => 'Active',
            'past_due' => 'Past Due',
            'suspended' => 'Suspended',
            'cancelled' => 'Cancelled',
            'expired' => 'Expired',
        ];

        $statusBadgeClass = function (?string $status): string {
            return match ($status) {
                'active' => 'bg-success',
                'trialing' => 'bg-info text-dark',
                'past_due' => 'bg-warning text-dark',
                'suspended' => 'bg-danger',
                'cancelled' => 'bg-secondary',
                'expired' => 'bg-dark',
                default => 'bg-light text-dark',
            };
        };

        $gatewayBadgeClass = function (?string $gateway): string {
            return match ($gateway) {
                'stripe' => 'bg-primary',
                null, '' => 'bg-light text-dark',
                default => 'bg-dark',
            };
        };
    @endphp

    <div class="page-wrapper">
        <div class="content">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>Tenants</h5>
                    <p class="text-muted mb-0">Operational view of all SaaS tenants, linked domains, and current subscription state.</p>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="text-muted mb-1">Total Tenants</div>
                            <h4 class="mb-0">{{ number_format((int) ($stats['total_tenants'] ?? 0)) }}</h4>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="text-muted mb-1">With Domains</div>
                            <h4 class="mb-0 text-primary">{{ number_format((int) ($stats['tenants_with_domains'] ?? 0)) }}</h4>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="text-muted mb-1">Active</div>
                            <h4 class="mb-0 text-success">{{ number_format((int) ($stats['active_subscriptions'] ?? 0)) }}</h4>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="text-muted mb-1">Trialing</div>
                            <h4 class="mb-0 text-info">{{ number_format((int) ($stats['trialing_subscriptions'] ?? 0)) }}</h4>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="text-muted mb-1">Past Due</div>
                            <h4 class="mb-0 text-warning">{{ number_format((int) ($stats['past_due_subscriptions'] ?? 0)) }}</h4>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="text-muted mb-1">Suspended</div>
                            <h4 class="mb-0 text-danger">{{ number_format((int) ($stats['suspended_subscriptions'] ?? 0)) }}</h4>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.tenants.index') }}">
                        <div class="row g-3">
                            <div class="col-xl-4 col-lg-6">
                                <label class="form-label">Search</label>
                                <input
                                    type="text"
                                    name="q"
                                    value="{{ $filters['q'] ?? '' }}"
                                    class="form-control"
                                    placeholder="Tenant ID, domain, company, owner email"
                                >
                            </div>

                            <div class="col-xl-2 col-lg-3">
                                <label class="form-label">Subscription Status</label>
                                <select name="status" class="form-select">
                                    @foreach($statusOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-xl-2 col-lg-3">
                                <label class="form-label">Plan</label>
                                <select name="plan_id" class="form-select">
                                    <option value="">All Plans</option>
                                    @foreach(($filterOptions['plans'] ?? []) as $plan)
                                        <option value="{{ $plan['id'] }}" @selected(($filters['plan_id'] ?? '') === (string) $plan['id'])>{{ $plan['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-xl-2 col-lg-3">
                                <label class="form-label">Gateway</label>
                                <select name="gateway" class="form-select">
                                    <option value="">All Gateways</option>
                                    @foreach(($filterOptions['gateway_options'] ?? []) as $gateway)
                                        <option value="{{ $gateway }}" @selected(($filters['gateway'] ?? '') === $gateway)>{{ strtoupper($gateway) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-xl-2 col-lg-3">
                                <label class="form-label">Has Domain</label>
                                <select name="has_domain" class="form-select">
                                    <option value="">All</option>
                                    <option value="yes" @selected(($filters['has_domain'] ?? '') === 'yes')>Yes</option>
                                    <option value="no" @selected(($filters['has_domain'] ?? '') === 'no')>No</option>
                                </select>
                            </div>

                            <div class="col-xl-2 col-lg-3">
                                <label class="form-label">Created From</label>
                                <input type="date" name="created_from" value="{{ $filters['created_from'] ?? '' }}" class="form-control">
                            </div>

                            <div class="col-xl-2 col-lg-3">
                                <label class="form-label">Created To</label>
                                <input type="date" name="created_to" value="{{ $filters['created_to'] ?? '' }}" class="form-control">
                            </div>

                            <div class="col-xl-2 col-lg-3 d-flex align-items-end">
                                <div class="d-grid w-100">
                                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                                </div>
                            </div>

                            <div class="col-xl-2 col-lg-3 d-flex align-items-end">
                                <div class="d-grid w-100">
                                    <a href="{{ route('admin.tenants.index') }}" class="btn btn-light">Reset</a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    @if($tenants->count() === 0)
                        <div class="alert alert-warning mb-0">No tenants matched the current filters.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Tenant ID</th>
                                    <th>Company</th>
                                    <th>Primary Domain</th>
                                    <th>Owner/Admin</th>
                                    <th>Plan</th>
                                    <th>Status</th>
                                    <th>Gateway</th>
                                    <th>Billing Period</th>
                                    <th>Created At</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($tenants as $tenant)
                                    @php
                                        $row = $tenantRows[(string) $tenant->getKey()] ?? null;
                                        $status = $row['subscription_status'] ?? null;
                                    @endphp

                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $row['tenant_id'] ?? $tenant->getKey() }}</div>
                                            @if(!empty($row['domains_count']))
                                                <small class="text-muted">{{ $row['domains_count'] }} domain(s)</small>
                                            @endif
                                        </td>

                                        <td>
                                            <div>{{ $row['company_name'] ?: '-' }}</div>
                                        </td>

                                        <td>
                                            @if(!empty($row['primary_domain']))
                                                <div>{{ $row['primary_domain'] }}</div>
                                                @if(!empty($row['open_url']))
                                                    <a href="{{ $row['open_url'] }}" target="_blank" class="small text-primary">Open Tenant</a>
                                                @endif
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>

                                        <td>{{ $row['owner_email'] ?: '-' }}</td>

                                        <td>{{ $row['plan_name'] ?: '-' }}</td>

                                        <td>
                                            <span class="badge {{ $statusBadgeClass($status) }}">
                                                {{ $status ? strtoupper(str_replace('_', ' ', $status)) : 'NO SUBSCRIPTION' }}
                                            </span>
                                        </td>

                                        <td>
                                            <div class="d-flex flex-column gap-1">
                                                <span class="badge {{ $gatewayBadgeClass($row['gateway'] ?? null) }}">
                                                    {{ !empty($row['gateway']) ? strtoupper($row['gateway']) : 'LOCAL' }}
                                                </span>
                                                @if(!empty($row['is_stripe_linked']))
                                                    <small class="text-muted">Stripe-linked</small>
                                                @endif
                                            </div>
                                        </td>

                                        <td>{{ $row['billing_period'] ? strtoupper($row['billing_period']) : '-' }}</td>

                                        <td>
                                            @if(!empty($row['created_at']))
                                                {{ \Illuminate\Support\Carbon::parse($row['created_at'])->format('Y-m-d H:i') }}
                                            @else
                                                -
                                            @endif
                                        </td>

                                        <td class="text-end">
                                            <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                                <a href="{{ $row['show_url'] }}" class="btn btn-sm btn-primary">View</a>

                                                @if(!empty($row['subscription_show_url']))
                                                    <a href="{{ $row['subscription_show_url'] }}" class="btn btn-sm btn-light">Subscription</a>
                                                @endif

                                                @if(!empty($row['admin_login_url']))
                                                    <form method="POST" action="{{ route('admin.tenants.impersonate', $row['tenant_id']) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-warning">Impersonate</button>
                                                    </form>
                                                @endif

                                                @if(($row['subscription_status'] ?? null) === 'suspended')
                                                    <form method="POST" action="{{ route('admin.tenants.activate', $row['tenant_id']) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success">Activate</button>
                                                    </form>
                                                @elseif(!empty($row['subscription_status']) && !in_array($row['subscription_status'], ['cancelled', 'expired'], true))
                                                    <form method="POST" action="{{ route('admin.tenants.suspend', $row['tenant_id']) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Suspend</button>
                                                    </form>
                                                @endif

                                                @if(!empty($row['open_url']))
                                                    <a href="{{ $row['open_url'] }}" target="_blank" class="btn btn-sm btn-outline-secondary">Open</a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            {{ $tenants->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
