@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">

            @component('admin.layouts.components.title-meta')
                @slot('title')
                    Central Subscriptions
                @endslot
            @endcomponent

            <div class="page-header">
                <div class="content-page-header">
                    <h5>Central Subscriptions</h5>
                    <p class="text-muted mb-0">
                        Review all tenant subscriptions, billing statuses, and Stripe linkage from the central admin.
                    </p>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="fw-semibold">Total</div>
                            <h3 class="mb-0">{{ number_format((int) ($statusCounts['total'] ?? 0)) }}</h3>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="fw-semibold">Active</div>
                            <h3 class="mb-0 text-success">{{ number_format((int) ($statusCounts['active'] ?? 0)) }}</h3>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="fw-semibold">Trialing</div>
                            <h3 class="mb-0 text-info">{{ number_format((int) ($statusCounts['trialing'] ?? 0)) }}</h3>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="fw-semibold">Past Due</div>
                            <h3 class="mb-0 text-warning">{{ number_format((int) ($statusCounts['past_due'] ?? 0)) }}</h3>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="fw-semibold">Suspended</div>
                            <h3 class="mb-0 text-danger">{{ number_format((int) ($statusCounts['suspended'] ?? 0)) }}</h3>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="fw-semibold">Canceled</div>
                            <h3 class="mb-0 text-secondary">{{ number_format((int) ($statusCounts['canceled'] ?? 0)) }}</h3>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="fw-semibold">Expired</div>
                            <h3 class="mb-0 text-dark">{{ number_format((int) ($statusCounts['expired'] ?? 0)) }}</h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.subscriptions.index') }}">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Tenant ID</label>
                                <input
                                    type="text"
                                    name="tenant_id"
                                    class="form-control"
                                    value="{{ $filters['tenant_id'] ?? '' }}"
                                    placeholder="Search by tenant id"
                                >
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All statuses</option>
                                    @foreach($statusOptions as $status)
                                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>
                                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Plan</label>
                                <select name="plan_id" class="form-select">
                                    <option value="">All plans</option>
                                    @foreach($plans as $plan)
                                        <option value="{{ $plan->id }}" @selected((int) ($filters['plan_id'] ?? 0) === (int) $plan->id)>
                                        {{ $plan->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Gateway</label>
                                <select name="gateway" class="form-select">
                                    <option value="">All gateways</option>
                                    @foreach($gatewayOptions as $gateway)
                                        <option value="{{ $gateway }}" @selected(($filters['gateway'] ?? '') === $gateway)>
                                        {{ strtoupper($gateway) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12 d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Apply Filters</button>
                                <a href="{{ route('admin.subscriptions.index') }}" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Tenant</th>
                                <th>Plan</th>
                                <th>Status</th>
                                <th>Gateway</th>
                                <th>Customer ID</th>
                                <th>Subscription ID</th>
                                <th>Price ID</th>
                                <th>Ends At</th>
                                <th>Grace Ends</th>
                                <th>Updated</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($subscriptions as $subscription)
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
                                @endphp

                                <tr>
                                    <td>{{ $subscription->id }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $subscription->tenant_id }}</div>
                                    </td>
                                    <td>
                                        @if(!empty($subscription->plan_name))
                                            <div class="fw-semibold">{{ $subscription->plan_name }}</div>
                                            <div class="small text-muted">
                                                {{ ucfirst((string) ($subscription->plan_billing_period ?? '')) }}
                                                @if(isset($subscription->plan_price))
                                                    — {{ number_format((float) $subscription->plan_price, 2) }} {{ strtoupper((string) ($subscription->plan_currency ?? 'USD')) }}
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-muted">No plan</span>
                                        @endif
                                    </td>
                                    <td>
                                            <span class="badge {{ $badgeClass }}">
                                                {{ ucfirst(str_replace('_', ' ', $status)) }}
                                            </span>
                                    </td>
                                    <td>{{ strtoupper((string) ($subscription->gateway ?? '-')) }}</td>
                                    <td>
                                        <span class="small">{{ $subscription->gateway_customer_id ?: '-' }}</span>
                                    </td>
                                    <td>
                                        <span class="small">{{ $subscription->gateway_subscription_id ?: '-' }}</span>
                                    </td>
                                    <td>
                                        <span class="small">{{ $subscription->gateway_price_id ?: '-' }}</span>
                                    </td>
                                    <td>
                                        {{ !empty($subscription->ends_at) ? \Carbon\Carbon::parse($subscription->ends_at)->format('Y-m-d H:i') : '-' }}
                                    </td>
                                    <td>
                                        {{ !empty($subscription->grace_ends_at) ? \Carbon\Carbon::parse($subscription->grace_ends_at)->format('Y-m-d H:i') : '-' }}
                                    </td>
                                    <td>
                                        {{ !empty($subscription->updated_at) ? \Carbon\Carbon::parse($subscription->updated_at)->format('Y-m-d H:i') : '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center text-muted">No subscriptions matched the selected filters.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $subscriptions->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
