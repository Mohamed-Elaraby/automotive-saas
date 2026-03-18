<?php $page = 'membership-plans'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two pb-0">

            <div class="d-flex d-block align-items-center justify-content-between flex-wrap gap-3 mb-3">
                <div>
                    <h6>Plans</h6>
                    <p class="mb-0">Manage your central billing catalog.</p>
                </div>
                <div>
                    <a href="{{ route('admin.plans.create') }}" class="btn btn-primary d-flex align-items-center">
                        <i class="isax isax-add-circle5 me-1"></i>New Plan
                    </a>
                </div>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if ($errors->has('delete'))
                <div class="alert alert-danger">{{ $errors->first('delete') }}</div>
            @endif

            <div class="table-responsive">
                <table class="table table-nowrap datatable">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Billing</th>
                        <th>Price</th>
                        <th>Stripe Price</th>
                        <th>Limits</th>
                        <th>Status</th>
                        <th>Subscriptions</th>
                        <th>Order</th>
                        <th class="no-sort"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($plans as $plan)
                        <tr>
                            <td>
                                <div>
                                    <p class="text-dark fw-medium mb-1">{{ $plan->name }}</p>
                                    @if($plan->description)
                                        <small class="text-muted">{{ $plan->description }}</small>
                                    @endif
                                </div>
                            </td>
                            <td>{{ $plan->slug }}</td>
                            <td>
                                <span class="badge badge-soft-info d-inline-flex align-items-center">
                                    {{ $plan->billing_period_label }}
                                </span>
                            </td>
                            <td>
                                <p class="text-dark mb-0">{{ $plan->display_price }}</p>
                            </td>
                            <td>
                                @if($plan->stripe_price_id)
                                    <small class="text-dark">{{ $plan->stripe_price_id }}</small>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <small class="d-block">Users: {{ $plan->max_users ?? '—' }}</small>
                                <small class="d-block">Branches: {{ $plan->max_branches ?? '—' }}</small>
                                <small class="d-block">Products: {{ $plan->max_products ?? '—' }}</small>
                                <small class="d-block">Storage: {{ $plan->max_storage_mb ?? '—' }} MB</small>
                            </td>
                            <td>
                                @if ($plan->is_active)
                                    <span class="badge badge-soft-success d-inline-flex align-items-center">
                                        Active <i class="isax isax-tick-circle ms-1"></i>
                                    </span>
                                @else
                                    <span class="badge badge-soft-danger d-inline-flex align-items-center">
                                        Inactive <i class="isax isax-close-circle ms-1"></i>
                                    </span>
                                @endif
                            </td>
                            <td>{{ $plan->subscriptions_count }}</td>
                            <td>{{ $plan->sort_order }}</td>
                            <td class="action-item">
                                <a href="javascript:void(0);" data-bs-toggle="dropdown">
                                    <i class="isax isax-more"></i>
                                </a>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a href="{{ route('admin.plans.edit', $plan) }}" class="dropdown-item d-flex align-items-center">
                                            <i class="isax isax-edit me-2"></i>Edit
                                        </a>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('admin.plans.toggle-active', $plan) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="dropdown-item d-flex align-items-center">
                                                <i class="isax isax-refresh me-2"></i>{{ $plan->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" onsubmit="return confirm('Are you sure you want to delete this plan?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item d-flex align-items-center text-danger">
                                                <i class="isax isax-trash me-2"></i>Delete
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">
                                <div class="text-center py-4">
                                    <p class="mb-0">No plans found.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

        </div>

        <div class="footer d-sm-flex align-items-center justify-content-between bg-white py-2 px-4 border-top">
            <p class="text-dark mb-0">&copy; 2025 <a href="javascript:void(0);" class="link-primary">Kanakku</a>, All Rights Reserved</p>
            <p class="text-dark">Version : 1.3.8</p>
        </div>
    </div>
@endsection
