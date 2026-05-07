<?php $page = 'access-control'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex d-block align-items-center justify-content-between flex-wrap gap-3 mb-3">
                <div>
                    <h4 class="mb-1">{{ __('access.title') }}</h4>
                    <p class="mb-0 text-muted">{{ __('access.subtitle') }}</p>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                    <a href="{{ route('automotive.admin.access.diagnostics.index') }}" class="btn btn-outline-white d-inline-flex align-items-center">
                        <i class="isax isax-search-status me-1"></i>{{ __('access.open_diagnostics') }}
                    </a>
                    <a href="{{ route('automotive.admin.users.index') }}" class="btn btn-primary d-flex align-items-center">
                        <i class="isax isax-profile-2user me-1"></i>{{ __('access.manage_workspace_users') }}
                    </a>
                </div>
            </div>

            <div class="row g-3 mb-3">
                @foreach($quickLinks as $link)
                    <div class="col-xxl col-xl-4 col-md-6 d-flex">
                        <a href="{{ route($link['route']) }}" class="card flex-fill text-decoration-none {{ $activePanel === $link['key'] ? 'border-primary shadow-sm' : '' }}">
                            <div class="card-body">
                                <div class="d-flex align-items-start gap-3">
                                    <span class="avatar avatar-md bg-primary-transparent rounded-circle">
                                        <i class="isax {{ $link['icon'] }} text-primary"></i>
                                    </span>
                                    <div>
                                        <h6 class="mb-1 text-dark">{{ $link['label'] }}</h6>
                                        <p class="mb-0 text-muted small">{{ $link['description'] }}</p>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>

            <div class="row">
                @include('automotive.admin.access.partials._metric-card', [
                    'label' => __('access.users'),
                    'value' => $usersCount,
                    'hint' => __('access.central_tenant_users'),
                    'icon' => 'isax-profile-2user',
                    'iconBg' => 'bg-primary-transparent',
                    'iconColor' => 'text-primary',
                ])
                @include('automotive.admin.access.partials._metric-card', [
                    'label' => __('access.products'),
                    'value' => $subscriptions->count(),
                    'hint' => __('access.active_or_trialing_products'),
                    'icon' => 'isax-layer',
                    'iconBg' => 'bg-success-transparent',
                    'iconColor' => 'text-success',
                ])
                @include('automotive.admin.access.partials._metric-card', [
                    'label' => __('access.branches'),
                    'value' => $branchesCount,
                    'hint' => __('access.central_branches'),
                    'icon' => 'isax-buildings',
                    'iconBg' => 'bg-secondary-transparent',
                    'iconColor' => 'text-secondary',
                ])
                @include('automotive.admin.access.partials._metric-card', [
                    'label' => __('access.roles'),
                    'value' => $rolesCount,
                    'hint' => __('access.product_scoped_roles'),
                    'icon' => 'isax-shield-tick',
                    'iconBg' => 'bg-warning-transparent',
                    'iconColor' => 'text-warning',
                ])
                @include('automotive.admin.access.partials._metric-card', [
                    'label' => __('access.permissions'),
                    'value' => $permissionsCount,
                    'hint' => __('access.product_scoped_permissions'),
                    'icon' => 'isax-lock',
                    'iconBg' => 'bg-info-transparent',
                    'iconColor' => 'text-info',
                ])
                @include('automotive.admin.access.partials._metric-card', [
                    'label' => __('access.product_access'),
                    'value' => $productAccessCount,
                    'hint' => __('access.enabled_user_product_access_records'),
                    'icon' => 'isax-user-tick',
                    'iconBg' => 'bg-success-transparent',
                    'iconColor' => 'text-success',
                ])
                @include('automotive.admin.access.partials._metric-card', [
                    'label' => __('access.branch_usage'),
                    'value' => $productBranchCount,
                    'hint' => __('access.enabled_product_branch_records'),
                    'icon' => 'isax-routing-2',
                    'iconBg' => 'bg-purple-transparent',
                    'iconColor' => 'text-purple',
                ])
                @include('automotive.admin.access.partials._metric-card', [
                    'label' => __('access.user_branch_access'),
                    'value' => $branchAccessCount,
                    'hint' => __('access.user_branch_assignment_records'),
                    'icon' => 'isax-location-tick',
                    'iconBg' => 'bg-danger-transparent',
                    'iconColor' => 'text-danger',
                ])
            </div>

            @if(!empty($seatUsageRows))
                <div class="row">
                    @foreach($seatUsageRows as $row)
                        <div class="col-xxl-3 col-xl-4 col-md-6 d-flex">
                            <div class="card flex-fill">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="text-gray-6">{{ $row['product_name'] }}</span>
                                        <span class="avatar avatar-sm bg-primary-transparent rounded-circle">
                                            <i class="isax isax-layer text-primary"></i>
                                        </span>
                                    </div>
                                    <h3 class="mb-1">
                                        {{ $row['used'] }} / {{ $row['limit'] === null ? __('access.unlimited') : $row['limit'] }}
                                    </h3>
                                    <p class="mb-0 text-muted">
                                        {{ __('access.extra_seats') }}: {{ $row['extra'] }} ·
                                        {{ __('access.available') }}: {{ $row['available'] === null ? __('access.unlimited') : $row['available'] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if(!empty($branchUsageRows))
                <div class="row">
                    @foreach($branchUsageRows as $row)
                        <div class="col-xxl-3 col-xl-4 col-md-6 d-flex">
                            <div class="card flex-fill">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="text-gray-6">{{ $row['product_name'] }}</span>
                                        <span class="avatar avatar-sm bg-secondary-transparent rounded-circle">
                                            <i class="isax isax-buildings text-secondary"></i>
                                        </span>
                                    </div>
                                    <h3 class="mb-1">
                                        {{ $row['enabled'] }} / {{ $row['limit'] === null ? __('access.unlimited') : $row['limit'] }}
                                    </h3>
                                    <p class="mb-2 text-muted">
                                        {{ __('access.available_branches') }}:
                                        {{ $row['available'] === null ? __('access.unlimited') : $row['available'] }}
                                    </p>
                                    <a href="{{ route('automotive.admin.access.products.branches.index', $row['product_key']) }}" class="btn btn-outline-white btn-sm d-inline-flex align-items-center">
                                        <i class="isax isax-setting-2 me-1"></i>{{ __('access.manage_product_branches') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <div class="col-xxl-3 col-xl-4 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="text-gray-6">{{ __('access.users_without_branch_access') }}</span>
                                    <span class="avatar avatar-sm bg-warning-transparent rounded-circle">
                                        <i class="isax isax-location-cross text-warning"></i>
                                    </span>
                                </div>
                                <h3 class="mb-1">{{ $usersWithoutBranchAccessCount }}</h3>
                                <p class="mb-2 text-muted">{{ __('access.users_without_branch_access_hint') }}</p>
                                <a href="{{ route('automotive.admin.access.users.index') }}" class="btn btn-outline-white btn-sm d-inline-flex align-items-center">
                                    <i class="isax isax-user-edit me-1"></i>{{ __('access.assign_user_branches') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="row">
                <div class="col-xl-7 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <h5 class="card-title mb-0">{{ __('access.seat_usage') }}</h5>
                                <p class="mb-0 text-muted small">{{ __('access.seat_usage_hint') }}</p>
                            </div>
                            <a href="{{ route('automotive.admin.access.products.index') }}" class="btn btn-outline-white btn-sm d-inline-flex align-items-center">
                                <i class="isax isax-layer me-1"></i>{{ __('access.products') }}
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive table-nowrap">
                                <table class="table border mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>{{ __('access.product') }}</th>
                                            <th>{{ __('access.plan') }}</th>
                                            <th>{{ __('access.status') }}</th>
                                            <th>{{ __('access.seats') }}</th>
                                            <th>{{ __('access.available') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($seatUsageRows as $row)
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold">{{ $row['product_name'] }}</div>
                                                    <div class="text-muted small">{{ $row['product_key'] }}</div>
                                                </td>
                                                <td>{{ $row['plan_name'] ?: __('access.no_plan') }}</td>
                                                <td><span class="badge bg-success">{{ $row['status'] }}</span></td>
                                                <td>
                                                    {{ $row['used'] }} /
                                                    {{ $row['limit'] === null ? __('access.unlimited') : $row['limit'] }}
                                                    <div class="text-muted small">{{ __('access.included_extra', ['included' => $row['included'], 'extra' => $row['extra']]) }}</div>
                                                </td>
                                                <td>{{ $row['available'] === null ? __('access.unlimited') : $row['available'] }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">{{ __('access.no_product_subscriptions') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-5 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <h5 class="card-title mb-0">{{ __('access.branch_usage') }}</h5>
                                <p class="mb-0 text-muted small">{{ __('access.branch_usage_hint') }}</p>
                            </div>
                            <a href="{{ route('automotive.admin.access.branches.index') }}" class="btn btn-outline-white btn-sm d-inline-flex align-items-center">
                                <i class="isax isax-buildings me-1"></i>{{ __('access.branches') }}
                            </a>
                        </div>
                        <div class="card-body">
                            @forelse($branchUsageRows as $row)
                                <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
                                    <div>
                                        <div class="fw-semibold">{{ $row['product_name'] }}</div>
                                        <div class="text-muted small">{{ $row['product_key'] }}</div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-semibold">
                                            {{ $row['enabled'] }} /
                                            {{ $row['limit'] === null ? __('access.unlimited') : $row['limit'] }}
                                        </div>
                                        <div class="text-muted small">
                                            {{ __('access.available') }}:
                                            {{ $row['available'] === null ? __('access.unlimited') : $row['available'] }}
                                        </div>
                                        <a href="{{ route('automotive.admin.access.products.branches.index', $row['product_key']) }}" class="small">
                                            {{ __('access.manage_product_branches') }}
                                        </a>
                                    </div>
                                </div>
                            @empty
                                <p class="mb-0 text-muted">{{ __('access.no_branch_usage') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('access.enforcement_order') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach(__('access.enforcement_steps') as $index => $step)
                            <div class="col-xl-2 col-md-4 col-sm-6">
                                <div class="border rounded p-3 h-100">
                                    <div class="avatar avatar-sm bg-light rounded-circle mb-2">{{ $index + 1 }}</div>
                                    <div class="fw-semibold">{{ $step }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        @include('automotive.admin.components.page-footer')
    </div>
@endsection
