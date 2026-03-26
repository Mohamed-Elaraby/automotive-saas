<?php $page = 'admin-tenants-show'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    @php
        $subscriptionStatus = $subscription['status'] ?? null;

        $statusBadgeClass = match ($subscriptionStatus) {
            'active' => 'bg-success',
            'trialing' => 'bg-info text-dark',
            'past_due' => 'bg-warning text-dark',
            'suspended' => 'bg-danger',
            'cancelled' => 'bg-secondary',
            'expired' => 'bg-dark',
            default => 'bg-light text-dark',
        };
    @endphp

    <div class="page-wrapper">
        <div class="content">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>Tenant Details</h5>
                    <p class="text-muted mb-0">Operational tenant snapshot for central SaaS administration.</p>
                </div>

                <div class="d-flex gap-2">
                    <a href="{{ route('admin.tenants.index') }}" class="btn btn-light">Back</a>

                    @if(!empty($row['subscription_show_url']))
                        <a href="{{ $row['subscription_show_url'] }}" class="btn btn-primary">Open Subscription</a>
                    @endif
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="row mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted mb-1">Tenant ID</div>
                            <h6 class="mb-0">{{ $row['tenant_id'] ?? $tenant->getKey() }}</h6>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted mb-1">Company</div>
                            <h6 class="mb-0">{{ $row['company_name'] ?: '-' }}</h6>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted mb-1">Owner/Admin</div>
                            <h6 class="mb-0">{{ $row['owner_email'] ?: '-' }}</h6>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted mb-1">Subscription Status</div>
                            <span class="badge {{ $statusBadgeClass }}">
                                {{ $subscriptionStatus ? strtoupper(str_replace('_', ' ', $subscriptionStatus)) : 'NO SUBSCRIPTION' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            @if($subscription)
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">Lifecycle Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-xl-4">
                                <form method="POST" action="{{ route('admin.tenants.suspend', $row['tenant_id'] ?? $tenant->getKey()) }}">
                                    @csrf
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-danger">
                                            Suspend Latest Subscription
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <div class="col-xl-4">
                                <form method="POST" action="{{ route('admin.tenants.activate', $row['tenant_id'] ?? $tenant->getKey()) }}">
                                    @csrf
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-success">
                                            Activate Latest Subscription
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <div class="col-xl-4">
                                <form method="POST" action="{{ route('admin.tenants.extend-trial', $row['tenant_id'] ?? $tenant->getKey()) }}">
                                    @csrf
                                    <div class="input-group">
                                        <input
                                            type="number"
                                            min="1"
                                            max="90"
                                            step="1"
                                            name="days"
                                            value="7"
                                            class="form-control"
                                            placeholder="Days"
                                        >
                                        <button type="submit" class="btn btn-primary">
                                            Extend Trial
                                        </button>
                                    </div>
                                    <small class="text-muted d-block mt-2">Adds the selected number of days to the current trial end date, or from now if the trial already expired.</small>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="row">
                <div class="col-xl-8">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">Overview</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <tbody>
                                <tr>
                                    <th style="width: 240px;">Tenant ID</th>
                                    <td>{{ $row['tenant_id'] ?? $tenant->getKey() }}</td>
                                </tr>
                                <tr>
                                    <th>Company</th>
                                    <td>{{ $row['company_name'] ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Owner/Admin</th>
                                    <td>{{ $row['owner_email'] ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Primary Domain</th>
                                    <td>
                                        @if(!empty($row['primary_domain']))
                                            <div>{{ $row['primary_domain'] }}</div>
                                            @if(!empty($row['open_url']))
                                                <a href="{{ $row['open_url'] }}" target="_blank">Open Tenant</a>
                                            @endif
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Domains Count</th>
                                    <td>{{ $row['domains_count'] ?? 0 }}</td>
                                </tr>
                                <tr>
                                    <th>Created At</th>
                                    <td>
                                        @if(!empty($row['created_at']))
                                            {{ \Illuminate\Support\Carbon::parse($row['created_at'])->format('Y-m-d H:i:s') }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">Subscription Snapshot</h6>
                        </div>
                        <div class="card-body">
                            @if($subscription)
                                <table class="table table-sm table-striped align-middle mb-0">
                                    <tbody>
                                    <tr>
                                        <th style="width: 240px;">Subscription ID</th>
                                        <td>{{ $subscription['id'] ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Plan</th>
                                        <td>{{ $subscription['plan_name'] ?: '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td>
                                            <span class="badge {{ $statusBadgeClass }}">
                                                {{ $subscriptionStatus ? strtoupper(str_replace('_', ' ', $subscriptionStatus)) : 'NO SUBSCRIPTION' }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Billing Period</th>
                                        <td>{{ !empty($subscription['billing_period']) ? strtoupper($subscription['billing_period']) : '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Gateway</th>
                                        <td>{{ $subscription['gateway'] ?: '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Trial Ends At</th>
                                        <td>{{ $subscription['trial_ends_at'] ?: '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Grace Ends At</th>
                                        <td>{{ $subscription['grace_ends_at'] ?: '-' }}</td>
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
                                    <tr>
                                        <th>Stripe Customer ID</th>
                                        <td>{{ $subscription['gateway_customer_id'] ?: '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Stripe Subscription ID</th>
                                        <td>{{ $subscription['gateway_subscription_id'] ?: '-' }}</td>
                                    </tr>
                                    </tbody>
                                </table>
                            @else
                                <div class="alert alert-warning mb-0">No linked subscription was found for this tenant.</div>
                            @endif
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">Tenant Payload</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <h6 class="mb-2">Model Attributes</h6>
                                <pre class="bg-light p-3 rounded mb-0">{{ json_encode($tenantData['attributes'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </div>

                            <div>
                                <h6 class="mb-2">Tenant Data</h6>
                                <pre class="bg-light p-3 rounded mb-0">{{ json_encode($tenantData['data'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">Domains</h6>
                        </div>
                        <div class="card-body">
                            @if($domains->count() > 0)
                                <div class="list-group list-group-flush">
                                    @foreach($domains as $domain)
                                        <div class="list-group-item px-0">
                                            <div class="fw-semibold">{{ $domain['domain'] }}</div>
                                            @if(!empty($domain['url']))
                                                <a href="{{ $domain['url'] }}" target="_blank" class="small">Open Domain</a>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="alert alert-warning mb-0">No domains were found for this tenant.</div>
                            @endif
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">Quick Links</h6>
                        </div>
                        <div class="card-body d-grid gap-2">
                            <a href="{{ route('admin.tenants.index') }}" class="btn btn-light">Back to Tenants</a>

                            @if(!empty($row['subscription_show_url']))
                                <a href="{{ $row['subscription_show_url'] }}" class="btn btn-primary">Open Linked Subscription</a>
                            @endif

                            @if(!empty($row['open_url']))
                                <a href="{{ $row['open_url'] }}" target="_blank" class="btn btn-outline-primary">Open Tenant Domain</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
