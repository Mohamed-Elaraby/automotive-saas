<?php $page = 'admin-tenant-product-subscriptions-index'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    @php
        $statusBadgeClass = function (?string $status): string {
            return match (strtolower((string) $status)) {
                'active' => 'bg-success',
                'trialing' => 'bg-info text-dark',
                'past_due' => 'bg-warning text-dark',
                'suspended' => 'bg-danger',
                'canceled', 'cancelled' => 'bg-secondary',
                'expired' => 'bg-dark',
                default => 'bg-light text-dark',
            };
        };
    @endphp

    <div class="page-wrapper">
        <div class="content">
            <div class="page-header">
                <div class="content-page-header">
                    <h5>Product Subscriptions</h5>
                    <p class="text-muted mb-0">
                        Central view for product-level tenant subscriptions across all tenants.
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
                            <h3 class="mb-0 text-secondary">{{ number_format((int) ($statusCounts['cancelled'] ?? 0)) }}</h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.tenants.product-subscriptions.index') }}">
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
                                <label class="form-label">Product</label>
                                <select name="product_id" class="form-select">
                                    <option value="">All products</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}" @selected((int) ($filters['product_id'] ?? 0) === (int) $product->id)>
                                            {{ $product->name ?: $product->slug ?: ('Product #' . $product->id) }}
                                        </option>
                                    @endforeach
                                </select>
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
                                <a href="{{ route('admin.tenants.product-subscriptions.index') }}" class="btn btn-outline-secondary">Reset</a>
                                <a href="{{ route('admin.tenants.index') }}" class="btn btn-light">Back to Tenants</a>
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
                                <th>Product</th>
                                <th>Plan</th>
                                <th>Status</th>
                                <th>Gateway</th>
                                <th>Identifiers</th>
                                <th>Updated</th>
                                <th class="text-end">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($subscriptions as $subscription)
                                @php
                                    $status = strtolower((string) ($subscription->status ?? 'unknown'));
                                    $productName = $subscription->product_name ?: $subscription->product_slug ?: $subscription->product_code ?: ('Product #' . ($subscription->product_id ?? '?'));
                                    $planName = $subscription->plan_name ?: $subscription->plan_slug ?: ($subscription->plan_id ? ('Plan #' . $subscription->plan_id) : 'No plan');
                                @endphp
                                <tr>
                                    <td>{{ $subscription->id }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $subscription->tenant_id }}</div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $productName }}</div>
                                        @if(!empty($subscription->product_code))
                                            <div class="small text-muted">{{ $subscription->product_code }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $planName }}</div>
                                        <div class="small text-muted">
                                            {{ $subscription->plan_billing_period ? strtoupper((string) $subscription->plan_billing_period) : '-' }}
                                            @if(isset($subscription->plan_price))
                                                | {{ number_format((float) $subscription->plan_price, 2) }} {{ strtoupper((string) ($subscription->plan_currency ?? 'USD')) }}
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge {{ $statusBadgeClass($status) }}">
                                            {{ ucfirst(str_replace('_', ' ', $status)) }}
                                        </span>
                                        @if((int) ($subscription->payment_failures_count ?? 0) > 0)
                                            <div class="small text-danger mt-1">
                                                Failures: {{ (int) $subscription->payment_failures_count }}
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $subscription->gateway ? strtoupper((string) $subscription->gateway) : 'LOCAL' }}</div>
                                        <div class="small text-muted">{{ $subscription->gateway_price_id ?: '-' }}</div>
                                    </td>
                                    <td>
                                        <div class="small d-flex flex-column gap-1">
                                            <span><span class="fw-semibold">Customer:</span> {{ $subscription->gateway_customer_id ?: '-' }}</span>
                                            <span><span class="fw-semibold">Subscription:</span> {{ $subscription->gateway_subscription_id ?: '-' }}</span>
                                            <span><span class="fw-semibold">Checkout:</span> {{ $subscription->gateway_checkout_session_id ?: '-' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        @if($subscription->updated_at)
                                            {{ \Carbon\Carbon::parse($subscription->updated_at)->format('Y-m-d H:i') }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex flex-wrap gap-2 justify-content-end">
                                            <a href="{{ route('admin.tenants.product-subscriptions.show', $subscription->id) }}" class="btn btn-sm btn-primary">
                                                Open Record
                                            </a>
                                            <a href="{{ route('admin.tenants.show', $subscription->tenant_id) }}" class="btn btn-sm btn-outline-primary">
                                                Open Tenant
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        No product subscriptions matched the current filters.
                                    </td>
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
