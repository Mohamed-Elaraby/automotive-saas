<?php $page = 'admin-coupons-index'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>Coupons</h5>
                    <p class="text-muted mb-0">Manage SaaS discount and promotion coupons.</p>
                </div>

                <a href="{{ route('admin.coupons.create') }}" class="btn btn-primary">Create Coupon</a>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.coupons.index') }}">
                        <div class="row g-3">
                            <div class="col-xl-4">
                                <label class="form-label">Search</label>
                                <input
                                    type="text"
                                    name="q"
                                    value="{{ $filters['q'] ?? '' }}"
                                    class="form-control"
                                    placeholder="Search by code or name"
                                >
                            </div>

                            <div class="col-xl-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All Statuses</option>
                                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                                </select>
                            </div>

                            <div class="col-xl-3">
                                <label class="form-label">Discount Type</label>
                                <select name="discount_type" class="form-select">
                                    <option value="">All Types</option>
                                    @foreach($discountTypeOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(($filters['discount_type'] ?? '') === $value)>
                                        {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-xl-2 d-flex align-items-end">
                                <div class="d-grid w-100">
                                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    @if($coupons->count() === 0)
                        <div class="alert alert-warning mb-0">No coupons matched the current filters.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Value</th>
                                    <th>Status</th>
                                    <th>Plans Scope</th>
                                    <th>Usage</th>
                                    <th>Window</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($coupons as $coupon)
                                    <tr>
                                        <td><strong>{{ $coupon->code }}</strong></td>
                                        <td>{{ $coupon->name }}</td>
                                        <td>
                                            <span class="badge bg-info text-dark">
                                                {{ strtoupper($coupon->discount_type) }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($coupon->discount_type === \App\Models\Coupon::TYPE_PERCENTAGE)
                                                {{ rtrim(rtrim(number_format((float) $coupon->discount_value, 2, '.', ''), '0'), '.') }}%
                                            @else
                                                {{ rtrim(rtrim(number_format((float) $coupon->discount_value, 2, '.', ''), '0'), '.') }}
                                                {{ $coupon->currency_code ?: '' }}
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge {{ $coupon->is_active ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $coupon->is_active ? 'ACTIVE' : 'INACTIVE' }}
                                            </span>
                                        </td>
                                        <td>
                                            {{ $coupon->applies_to_all_plans ? 'All Plans' : 'Selected Plans' }}
                                        </td>
                                        <td>
                                            {{ (int) $coupon->times_redeemed }}
                                            @if($coupon->max_redemptions)
                                                / {{ (int) $coupon->max_redemptions }}
                                            @else
                                                / Unlimited
                                            @endif
                                        </td>
                                        <td>
                                            <div>Start: {{ optional($coupon->starts_at)->format('Y-m-d H:i') ?: '-' }}</div>
                                            <div>End: {{ optional($coupon->ends_at)->format('Y-m-d H:i') ?: '-' }}</div>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                                <a href="{{ route('admin.coupons.show', $coupon) }}" class="btn btn-sm btn-outline-primary">
                                                    View
                                                </a>

                                                <a href="{{ route('admin.coupons.edit', $coupon) }}" class="btn btn-sm btn-primary">
                                                    Edit
                                                </a>

                                                <form method="POST" action="{{ route('admin.coupons.toggle-active', $coupon) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn btn-sm btn-light">
                                                        {{ $coupon->is_active ? 'Deactivate' : 'Activate' }}
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            {{ $coupons->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
