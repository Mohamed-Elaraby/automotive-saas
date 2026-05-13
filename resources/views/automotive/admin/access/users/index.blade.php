<?php $page = 'access-control'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex d-block align-items-center justify-content-between flex-wrap gap-3 mb-3">
                <div>
                    <h4 class="mb-1">{{ __('access.users_product_access') }}</h4>
                    <p class="mb-0 text-muted">{{ __('access.users_product_access_subtitle') }}</p>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                    <a href="{{ route('automotive.admin.access.index') }}" class="btn btn-outline-white d-inline-flex align-items-center">
                        <i class="isax isax-arrow-left me-1"></i>{{ __('access.back_to_access_center') }}
                    </a>
                    <a href="{{ route('automotive.admin.users.create') }}" class="btn btn-primary d-flex align-items-center">
                        <i class="isax isax-add-circle5 me-1"></i>{{ __('tenant.add_user') }}
                    </a>
                </div>
            </div>

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

            <div class="mb-3">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="isax isax-search-normal fs-12"></i>
                            </span>
                            <input type="text" class="form-control border-start-0 ps-0 bg-white" placeholder="{{ __('tenant.search') }}">
                        </div>
                    </div>
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <div class="dropdown">
                            <a href="javascript:void(0);" class="dropdown-toggle btn btn-outline-white d-inline-flex align-items-center" data-bs-toggle="dropdown">
                                <i class="isax isax-sort me-1"></i>{{ __('access.sort_by_latest') }}
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><span class="dropdown-item">{{ __('access.latest') }}</span></li>
                                <li><span class="dropdown-item">{{ __('access.oldest') }}</span></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-nowrap datatable">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ __('tenant.name') }}</th>
                            <th>{{ __('tenant.email') }}</th>
                            <th>{{ __('access.enabled_products') }}</th>
                            <th>{{ __('access.branch_access') }}</th>
                            <th>{{ __('access.roles') }}</th>
                            <th>{{ __('access.access_warnings') }}</th>
                            <th>{{ __('tenant.status') }}</th>
                            <th class="no-sort"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            @php($products = $userAccessSummary[$user->id] ?? [])
                            @php($branchSummary = $userBranchAccessSummary[$user->id] ?? ['count' => 0, 'product_keys' => []])
                            @php($roleSummary = $userRoleSummary[$user->id] ?? ['count' => 0, 'product_keys' => []])
                            @php($warningCount = (int) ($userWarningSummary[$user->id] ?? 0))
                            @php($isOwner = in_array((int) $user->id, $ownerUserIds ?? [], true))
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="avatar avatar-sm rounded-circle bg-primary-transparent me-2 flex-shrink-0">
                                            <i class="isax isax-user text-primary"></i>
                                        </span>
                                        <div>
                                            <h6 class="fs-14 fw-medium mb-0">{{ $user->name }}</h6>
                                            @if((int) $user->id === 1)
                                                <span class="badge bg-primary-light text-primary">{{ __('tenant.workspace_owner_account') }}</span>
                                            @endif
                                            @if((int) $user->id === (int) $currentUserId)
                                                <span class="badge bg-success-light text-success">{{ __('tenant.current_login_account') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    @forelse($products as $productKey)
                                        <span class="badge bg-success-transparent text-success border me-1">{{ $productKey }}</span>
                                    @empty
                                        @if($isOwner)
                                            <span class="badge bg-primary-transparent text-primary border me-1">{{ __('access.owner_access') }}</span>
                                            <span class="badge bg-info-transparent text-info border">{{ __('access.does_not_consume_product_seat') }}</span>
                                        @else
                                            <span class="text-muted">{{ __('access.no_product_access') }}</span>
                                        @endif
                                    @endforelse
                                </td>
                                <td>
                                    @if($isOwner && (int) $branchSummary['count'] === 0)
                                        <div class="fw-semibold">{{ __('access.implicit_full_access') }}</div>
                                        <span class="badge bg-primary-transparent text-primary border">{{ __('access.owner_implicit_branch_access') }}</span>
                                    @else
                                        <div class="fw-semibold">{{ $branchSummary['count'] }} {{ __('access.branches') }}</div>
                                    @endif
                                    @if(! $isOwner && !empty($products) && (int) $branchSummary['count'] === 0)
                                        <span class="badge bg-warning-transparent text-warning border">{{ __('access.product_access_without_branch_access') }}</span>
                                    @else
                                        @foreach($branchSummary['product_keys'] as $productKey)
                                            <span class="badge bg-light text-muted border me-1">{{ $productKey }}</span>
                                        @endforeach
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $roleSummary['count'] }} {{ __('access.roles') }}</div>
                                    @foreach($roleSummary['product_keys'] as $productKey)
                                        <span class="badge bg-light text-muted border me-1">{{ $productKey }}</span>
                                    @endforeach
                                    @if($isOwner && (int) $roleSummary['count'] === 0)
                                        <span class="badge bg-primary-transparent text-primary border">{{ __('access.owner_implicit_roles') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $warningCount > 0 ? 'bg-warning-transparent text-warning' : 'bg-success-transparent text-success' }} border">
                                        {{ $warningCount }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-soft-success d-inline-flex align-items-center">
                                        {{ __('tenant.active') }} <i class="isax isax-tick-circle ms-1"></i>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('automotive.admin.access.users.show', $user) }}" class="btn btn-outline-white d-inline-flex align-items-center me-2">
                                        <i class="isax isax-eye me-1"></i>{{ __('access.view_access_profile') }}
                                    </a>
                                    @if($isOwner)
                                        <form method="POST" action="{{ route('automotive.admin.access.users.owner.sync', $user) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-white d-inline-flex align-items-center me-2">
                                                <i class="isax isax-refresh me-1"></i>{{ __('access.sync_owner_access') }}
                                            </button>
                                        </form>
                                    @endif
                                    <a href="{{ route('automotive.admin.access.users.branches.edit', $user) }}" class="btn btn-outline-white d-inline-flex align-items-center me-2">
                                        <i class="isax isax-buildings me-1"></i>{{ __('access.manage_branch_access') }}
                                    </a>
                                    <a href="{{ route('automotive.admin.access.users.products.edit', $user) }}" class="btn btn-outline-white d-inline-flex align-items-center">
                                        <i class="isax isax-layer me-1"></i>{{ __('access.manage_product_access') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">{{ __('tenant.no_users_found') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @include('automotive.admin.components.page-footer')
    </div>
@endsection
