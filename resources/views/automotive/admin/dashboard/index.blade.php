<?php $page = 'dashboard'; ?>
@extends('automotive.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">

            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="mb-1">Dashboard</h4>
                    <p class="mb-0 text-muted">Overview of your tenant, subscription, limits, stock activity, and operational shortcuts.</p>
                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <a href="{{ route('automotive.admin.products.create') }}" class="btn btn-primary d-inline-flex align-items-center">
                        <i class="isax isax-box-add me-1"></i>Add Product
                    </a>
                    <a href="{{ route('automotive.admin.inventory-adjustments.create') }}" class="btn btn-outline-white d-inline-flex align-items-center">
                        <i class="isax isax-arrows-swap me-1"></i>Adjustment
                    </a>
                    <a href="{{ route('automotive.admin.stock-transfers.create') }}" class="btn btn-outline-white d-inline-flex align-items-center">
                        <i class="isax isax-arrow-right-3 me-1"></i>Transfer
                    </a>
                </div>
            </div>

            <div class="row">
                <div class="col-xxl-3 col-xl-4 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-gray-6">Users</span>
                                <span class="avatar avatar-sm bg-primary-transparent rounded-circle">
                                    <i class="isax isax-profile-2user text-primary"></i>
                                </span>
                            </div>
                            <h3 class="mb-1">{{ $usersCount }}</h3>
                            <p class="mb-0 text-muted">
                                @if (!is_null($userLimit['limit']))
                                    Limit: {{ $userLimit['limit'] }} | Remaining: {{ $userLimit['remaining'] }}
                                @else
                                    Unlimited
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-xxl-3 col-xl-4 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-gray-6">Branches</span>
                                <span class="avatar avatar-sm bg-secondary-transparent rounded-circle">
                                    <i class="isax isax-buildings text-secondary"></i>
                                </span>
                            </div>
                            <h3 class="mb-1">{{ $branchesCount }}</h3>
                            <p class="mb-0 text-muted">
                                @if (!is_null($branchLimit['limit']))
                                    Limit: {{ $branchLimit['limit'] }} | Remaining: {{ $branchLimit['remaining'] }}
                                @else
                                    Unlimited
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-xxl-3 col-xl-4 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-gray-6">Products</span>
                                <span class="avatar avatar-sm bg-info-transparent rounded-circle">
                                    <i class="isax isax-box text-info"></i>
                                </span>
                            </div>
                            <h3 class="mb-1">{{ $productsCount }}</h3>
                            <p class="mb-0 text-muted">
                                @if (!is_null($productLimit['limit']))
                                    Limit: {{ $productLimit['limit'] }} | Remaining: {{ $productLimit['remaining'] }}
                                @else
                                    Unlimited
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-xxl-3 col-xl-4 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-gray-6">Low Stock Items</span>
                                <span class="avatar avatar-sm bg-danger-transparent rounded-circle">
                                    <i class="isax isax-warning-2 text-danger"></i>
                                </span>
                            </div>
                            <h3 class="mb-1">{{ $lowStockItems->count() }}</h3>
                            <p class="mb-0 text-muted">Products at or below minimum alert</p>
                        </div>
                    </div>
                </div>

                <div class="col-xxl-4 col-xl-6 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-gray-6">Inventory Records</span>
                                <span class="avatar avatar-sm bg-success-transparent rounded-circle">
                                    <i class="isax isax-archive text-success"></i>
                                </span>
                            </div>
                            <h3 class="mb-1">{{ $inventoriesCount }}</h3>
                            <p class="mb-0 text-muted">Branch-product stock records</p>
                        </div>
                    </div>
                </div>

                <div class="col-xxl-4 col-xl-6 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-gray-6">Stock Transfers</span>
                                <span class="avatar avatar-sm bg-warning-transparent rounded-circle">
                                    <i class="isax isax-arrow-right-3 text-warning"></i>
                                </span>
                            </div>
                            <h3 class="mb-1">{{ $stockTransfersCount }}</h3>
                            <p class="mb-0 text-muted">Draft + posted transfers between branches</p>
                        </div>
                    </div>
                </div>

                <div class="col-xxl-4 col-xl-6 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-gray-6">Stock Movements</span>
                                <span class="avatar avatar-sm bg-danger-transparent rounded-circle">
                                    <i class="isax isax-arrows-swap text-danger"></i>
                                </span>
                            </div>
                            <h3 class="mb-1">{{ $stockMovementsCount }}</h3>
                            <p class="mb-0 text-muted">Opening stock, adjustments, and transfers</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xxl-4 col-xl-5 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Tenant Overview</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between border-bottom pb-2 mb-3">
                                <span class="text-gray-6">Tenant ID</span>
                                <strong>{{ $tenant->id }}</strong>
                            </div>
                            <div class="d-flex justify-content-between border-bottom pb-2 mb-3">
                                <span class="text-gray-6">Company</span>
                                <strong>{{ data_get($tenant->data, 'company_name', '—') }}</strong>
                            </div>
                            <div class="d-flex justify-content-between border-bottom pb-2 mb-3">
                                <span class="text-gray-6">Database</span>
                                <strong>{{ data_get($tenant->data, 'db_name', '—') }}</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-gray-6">Logged User</span>
                                <strong>{{ auth('automotive_admin')->user()?->name ?? '—' }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xxl-4 col-xl-7 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Current Plan</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between border-bottom pb-2 mb-3">
                                <span class="text-gray-6">Plan</span>
                                <strong>{{ $plan?->name ?? 'No plan assigned' }}</strong>
                            </div>
                            <div class="d-flex justify-content-between border-bottom pb-2 mb-3">
                                <span class="text-gray-6">Billing</span>
                                <strong>{{ $plan ? ucfirst($plan->billing_period) : '—' }}</strong>
                            </div>
                            <div class="d-flex justify-content-between border-bottom pb-2 mb-3">
                                <span class="text-gray-6">Price</span>
                                <strong>{{ $plan ? $plan->price . ' ' . $plan->currency : '—' }}</strong>
                            </div>
                            <div class="d-flex justify-content-between border-bottom pb-2 mb-3">
                                <span class="text-gray-6">Status</span>
                                <strong>
                                    @php $status = $subscription->status ?? 'unknown'; @endphp
                                    @if ($status === 'active')
                                        <span class="badge bg-success">Active</span>
                                    @elseif ($status === 'trialing')
                                        <span class="badge bg-info">Trialing</span>
                                    @elseif ($status === 'expired')
                                        <span class="badge bg-danger">Expired</span>
                                    @else
                                        <span class="badge bg-secondary">{{ ucfirst($status) }}</span>
                                    @endif
                                </strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-gray-6">Trial Ends At</span>
                                <strong>{{ !empty($subscription?->trial_ends_at) ? \Carbon\Carbon::parse($subscription->trial_ends_at)->format('Y-m-d H:i') : '—' }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xxl-4 col-xl-12 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Quick Navigation</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="{{ route('automotive.admin.users.index') }}" class="btn btn-outline-light text-start">
                                    <i class="isax isax-profile-2user me-2"></i>Manage Users
                                </a>
                                <a href="{{ route('automotive.admin.branches.index') }}" class="btn btn-outline-light text-start">
                                    <i class="isax isax-buildings me-2"></i>Manage Branches
                                </a>
                                <a href="{{ route('automotive.admin.products.index') }}" class="btn btn-outline-light text-start">
                                    <i class="isax isax-box me-2"></i>Manage Products
                                </a>
                                <a href="{{ route('automotive.admin.inventory-adjustments.index') }}" class="btn btn-outline-light text-start">
                                    <i class="isax isax-arrows-swap me-2"></i>Inventory Adjustments
                                </a>
                                <a href="{{ route('automotive.admin.stock-transfers.index') }}" class="btn btn-outline-light text-start">
                                    <i class="isax isax-arrow-right-3 me-2"></i>Stock Transfers
                                </a>
                                <a href="{{ route('automotive.admin.inventory-report.index') }}" class="btn btn-outline-light text-start">
                                    <i class="isax isax-chart-35 me-2"></i>Inventory Report
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xxl-4 col-xl-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Low Stock Snapshot</h5>
                        </div>
                        <div class="card-body">
                            @forelse ($lowStockItems as $item)
                                <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-2">
                                    <div>
                                        <h6 class="fs-14 mb-1">{{ $item->product?->name ?? '—' }}</h6>
                                        <p class="mb-0 fs-13 text-muted">{{ $item->branch?->name ?? '—' }}</p>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-danger">{{ $item->quantity }}</span>
                                        <p class="mb-0 fs-12 text-muted">Min: {{ $item->product?->min_stock_alert ?? 0 }}</p>
                                    </div>
                                </div>
                            @empty
                                <p class="mb-0 text-muted">No low stock items right now.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="col-xxl-4 col-xl-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Recent Transfers</h5>
                        </div>
                        <div class="card-body">
                            @forelse ($recentTransfers as $transfer)
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="fs-14 mb-1">
                                            Transfer #{{ $transfer->id }}
                                        </h6>
                                        <span class="badge {{ ($transfer->status ?? 'draft') === 'posted' ? 'bg-success' : 'bg-warning' }}">
                                            {{ ucfirst($transfer->status ?? 'draft') }}
                                        </span>
                                    </div>
                                    <p class="mb-1 fs-13 text-muted">
                                        {{ $transfer->fromBranch?->name ?? '—' }} → {{ $transfer->toBranch?->name ?? '—' }}
                                    </p>
                                    <p class="mb-0 fs-12 text-muted">
                                        {{ optional($transfer->created_at)->format('Y-m-d H:i') }}
                                    </p>
                                </div>
                            @empty
                                <p class="mb-0 text-muted">No stock transfers yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="col-xxl-4 col-xl-12 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Recent Stock Movements</h5>
                        </div>
                        <div class="card-body">
                            @forelse ($recentMovements as $movement)
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="fs-14 mb-1">{{ $movement->product?->name ?? '—' }}</h6>
                                        <span class="badge bg-secondary">{{ strtoupper($movement->type ?? 'N/A') }}</span>
                                    </div>
                                    <p class="mb-1 fs-13 text-muted">{{ $movement->branch?->name ?? '—' }}</p>
                                    <p class="mb-0 fs-12 text-muted">
                                        Qty: {{ $movement->quantity }} | {{ optional($movement->created_at)->format('Y-m-d H:i') }}
                                    </p>
                                </div>
                            @empty
                                <p class="mb-0 text-muted">No stock movements found.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            @include('automotive.admin.components.page-footer')
        </div>
    </div>
@endsection
