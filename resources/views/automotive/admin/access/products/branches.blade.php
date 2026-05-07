<?php $page = 'access-control'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex d-block align-items-center justify-content-between flex-wrap gap-3 mb-3">
                <div>
                    <h4 class="mb-1">{{ __('access.product_branches') }}</h4>
                    <p class="mb-0 text-muted">{{ __('access.product_branches_subtitle') }}</p>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                    <a href="{{ route('automotive.admin.access.index') }}" class="btn btn-outline-white d-inline-flex align-items-center">
                        <i class="isax isax-arrow-left me-1"></i>{{ __('access.back_to_access_center') }}
                    </a>
                </div>
            </div>

            @include('automotive.admin.partials.alerts')

            <div class="row">
                <div class="col-xl-3 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body">
                            <span class="text-gray-6">{{ __('access.product') }}</span>
                            <h5 class="mb-1">{{ $subscription->product?->name ?? $productKey }}</h5>
                            <span class="badge bg-success">{{ $subscription->status }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body">
                            <span class="text-gray-6">{{ __('access.plan') }}</span>
                            <h5 class="mb-1">{{ $subscription->plan?->name ?: __('access.no_plan') }}</h5>
                            <p class="mb-0 text-muted">{{ $productKey }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body">
                            <span class="text-gray-6">{{ __('access.enabled_branches') }}</span>
                            <h3 class="mb-1">
                                {{ $usage['enabled'] }} / {{ $usage['limit'] === null ? __('access.unlimited') : $usage['limit'] }}
                            </h3>
                            <p class="mb-0 text-muted">{{ __('access.extra_branches') }}: {{ max(0, (int) ($usage['limit'] ?? 0) - (int) ($subscription->branch_limit ?? 0)) }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body">
                            <span class="text-gray-6">{{ __('access.available_branches') }}</span>
                            <h3 class="mb-1">{{ $usage['available'] === null ? __('access.unlimited') : $usage['available'] }}</h3>
                            @if($usage['available'] !== null && (int) $usage['available'] <= 0)
                                <span class="badge bg-warning">{{ __('access.branch_limit_reached') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('automotive.admin.access.products.branches.update', $productKey) }}">
                @csrf
                @method('PUT')

                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <h5 class="card-title mb-0">{{ __('access.central_branches') }}</h5>
                            <p class="mb-0 text-muted small">{{ __('access.product_branches_table_hint') }}</p>
                        </div>
                        <button type="submit" class="btn btn-primary d-inline-flex align-items-center">
                            <i class="isax isax-tick-circle me-1"></i>{{ __('tenant.save_changes') }}
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive table-nowrap">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('shared.branch') }}</th>
                                        <th>{{ __('shared.contact') }}</th>
                                        <th>{{ __('tenant.status') }}</th>
                                        <th>{{ __('access.product_branch_status') }}</th>
                                        <th class="text-end">{{ __('access.enable') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($branchRows as $row)
                                        @php($branch = $row['branch'])
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="avatar avatar-sm rounded-circle bg-primary-transparent me-2">
                                                        <i class="isax isax-buildings text-primary"></i>
                                                    </span>
                                                    <div>
                                                        <h6 class="fs-14 fw-medium mb-0">{{ $branch->name }}</h6>
                                                        <span class="text-muted small">{{ $branch->code ?: __('access.no_code') }}</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div>{{ $branch->phone ?: '-' }}</div>
                                                <span class="text-muted small">{{ $branch->email ?: '-' }}</span>
                                            </td>
                                            <td>
                                                <span class="badge {{ $branch->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }}">
                                                    {{ $branch->is_active ? __('tenant.active') : __('tenant.inactive') }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge {{ $row['is_enabled'] ? 'bg-success-transparent text-success border' : 'bg-light text-muted border' }}">
                                                    {{ $row['is_enabled'] ? __('access.enabled') : __('access.disabled') }}
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <div class="form-check form-switch d-inline-flex justify-content-end">
                                                    <input
                                                        class="form-check-input"
                                                        type="checkbox"
                                                        name="branches[]"
                                                        value="{{ $branch->id }}"
                                                        @checked($row['is_enabled'])
                                                        @disabled(! $branch->is_active)
                                                    >
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">{{ __('access.no_branches_found') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        @include('automotive.admin.components.page-footer')
    </div>
@endsection
