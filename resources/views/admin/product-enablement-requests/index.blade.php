<?php $page = 'product-enablement-requests-index'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="mb-1">Product Enablement Requests</h4>
                    <p class="text-muted mb-0">Review additional product requests submitted from the customer portal.</p>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.product-enablement-requests.index') }}" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="Tenant, user, email, product">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All statuses</option>
                                @foreach($statusOptions as $statusOption)
                                    <option value="{{ $statusOption }}" @selected($filters['status'] === $statusOption)>
                                        {{ ucfirst($statusOption) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Product</label>
                            <select name="product_id" class="form-select">
                                <option value="">All products</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}" @selected((int) ($filters['product_id'] ?? 0) === (int) $product->id)>
                                        {{ $product->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                            <a href="{{ route('admin.product-enablement-requests.index') }}" class="btn btn-outline-white w-100">Reset</a>
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
                                    <th>Requested At</th>
                                    <th>Product</th>
                                    <th>Tenant</th>
                                    <th>User</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($requests as $requestRow)
                                    <tr>
                                        <td>{{ optional($requestRow->requested_at)->format('Y-m-d H:i') ?: '-' }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $requestRow->product_name ?? '-' }}</div>
                                            <div class="text-muted small">{{ strtoupper((string) ($requestRow->product_code ?? '')) }}</div>
                                        </td>
                                        <td>{{ $requestRow->tenant_id }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $requestRow->user_name ?? '-' }}</div>
                                            <div class="text-muted small">{{ $requestRow->user_email ?? '-' }}</div>
                                        </td>
                                        <td>
                                            <span class="badge
                                                @if($requestRow->status === 'approved') bg-success
                                                @elseif($requestRow->status === 'rejected') bg-danger
                                                @else bg-warning
                                                @endif">
                                                {{ strtoupper((string) $requestRow->status) }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            @if($requestRow->status === 'pending')
                                                <div class="d-inline-flex gap-2">
                                                    <form method="POST" action="{{ route('admin.product-enablement-requests.approve', $requestRow->id) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('admin.product-enablement-requests.reject', $requestRow->id) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                                                    </form>
                                                </div>
                                            @else
                                                <span class="text-muted small">No pending action</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No product enablement requests found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $requests->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
