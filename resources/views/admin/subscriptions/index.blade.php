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
                                <th>Lifecycle Timeline</th>
                                <th>Identifiers</th>
                                <th>Updated</th>
                                <th class="text-end">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($subscriptions as $subscription)
                                @php
                                    $status = strtolower((string) ($subscription->status ?? 'unknown'));
                                    $badgeClass = $statusBadgeClass($status);
                                    $isStripeLinked = (($subscription->gateway ?? null) === 'stripe')
                                        || !empty($subscription->gateway_subscription_id)
                                        || !empty($subscription->gateway_customer_id);
                                    $timeline = [
                                        'Trial ends' => $subscription->trial_ends_at ?? null,
                                        'Past due' => $subscription->past_due_started_at ?? null,
                                        'Grace ends' => $subscription->grace_ends_at ?? null,
                                        'Suspended' => $subscription->suspended_at ?? null,
                                        'Canceled' => $subscription->cancelled_at ?? null,
                                        'Ends' => $subscription->ends_at ?? null,
                                    ];
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
                                    <td>
                                        <div class="d-flex flex-column gap-1">
                                            <span class="badge {{ $isStripeLinked ? 'bg-primary' : 'bg-light text-dark' }}">
                                                {{ !empty($subscription->gateway) ? strtoupper((string) $subscription->gateway) : 'LOCAL' }}
                                            </span>
                                            <span class="small text-muted">
                                                {{ $isStripeLinked ? 'Stripe-linked' : 'Local-managed' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small d-flex flex-column gap-1">
                                            @php
                                                $visibleTimeline = collect($timeline)->filter();
                                            @endphp

                                            @if($visibleTimeline->isEmpty())
                                                <span class="text-muted">No lifecycle dates recorded.</span>
                                            @else
                                                @foreach($visibleTimeline as $label => $value)
                                                    <span>
                                                        <span class="fw-semibold">{{ $label }}:</span>
                                                        {{ \Carbon\Carbon::parse($value)->format('Y-m-d H:i') }}
                                                    </span>
                                                @endforeach
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small d-flex flex-column gap-1">
                                            <span><span class="fw-semibold">Customer:</span> {{ $subscription->gateway_customer_id ?: '-' }}</span>
                                            <span><span class="fw-semibold">Subscription:</span> {{ $subscription->gateway_subscription_id ?: '-' }}</span>
                                            <span><span class="fw-semibold">Price:</span> {{ $subscription->gateway_price_id ?: '-' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        {{ !empty($subscription->updated_at) ? \Carbon\Carbon::parse($subscription->updated_at)->format('Y-m-d H:i') : '-' }}
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                            <a href="{{ route('admin.subscriptions.show', $subscription->id) }}" class="btn btn-sm btn-primary">
                                                View
                                            </a>

                                            @if($isStripeLinked)
                                                <form method="POST" action="{{ route('admin.subscriptions.sync-stripe', $subscription->id) }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="redirect_to" value="index">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">Sync Stripe</button>
                                                </form>

                                                <form method="POST" action="{{ route('admin.subscriptions.backfill-invoices', $subscription->id) }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="redirect_to" value="index">
                                                    <button type="submit" class="btn btn-sm btn-outline-dark">Backfill</button>
                                                </form>
                                            @else
                                                @if($status === 'suspended' || $status === 'canceled' || $status === 'cancelled' || $status === 'expired')
                                                    <form method="POST" action="{{ route('admin.subscriptions.manual-action', $subscription->id) }}" class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="action" value="resume">
                                                        <input type="hidden" name="redirect_to" value="index">
                                                        <button type="submit" class="btn btn-sm btn-outline-success">Resume</button>
                                                    </form>

                                                    <form method="POST" action="{{ route('admin.subscriptions.manual-action', $subscription->id) }}" class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="action" value="renew">
                                                        <input type="hidden" name="redirect_to" value="index">
                                                        <button type="submit" class="btn btn-sm btn-outline-dark">Renew</button>
                                                    </form>
                                                @elseif($status === 'active' || $status === 'trialing' || $status === 'past_due')
                                                    <form method="POST" action="{{ route('admin.subscriptions.manual-action', $subscription->id) }}" class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="action" value="cancel">
                                                        <input type="hidden" name="redirect_to" value="index">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button>
                                                    </form>
                                                @endif
                                            @endif

                                            <form method="POST" action="{{ route('admin.subscriptions.refresh-state', $subscription->id) }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="redirect_to" value="index">
                                                <button type="submit" class="btn btn-sm btn-light">Refresh State</button>
                                            </form>

                                            <form method="POST" action="{{ route('admin.subscriptions.normalize-lifecycle', $subscription->id) }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="redirect_to" value="index">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">Normalize</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted">No subscriptions matched the selected filters.</td>
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
