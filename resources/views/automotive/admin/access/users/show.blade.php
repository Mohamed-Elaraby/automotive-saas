<?php $page = 'access-control'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex d-block align-items-center justify-content-between flex-wrap gap-3 mb-3">
                <div>
                    <h4 class="mb-1">{{ __('access.user_access_profile') }}</h4>
                    <p class="mb-0 text-muted">{{ __('access.user_access_profile_subtitle') }}</p>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                    <a href="{{ route('automotive.admin.access.users.index') }}" class="btn btn-outline-white d-inline-flex align-items-center">
                        <i class="isax isax-arrow-left me-1"></i>{{ __('access.back_to_users') }}
                    </a>
                    @productCan('automotive_service.access.roles.manage', 'automotive_service')
                        <a href="{{ route('automotive.admin.access.users.roles.edit', $user) }}" class="btn btn-outline-white d-inline-flex align-items-center">
                            <i class="isax isax-shield-tick me-1"></i>{{ __('access.assign_roles') }}
                        </a>
                    @endproductCan
                    @productCan('automotive_service.access.users.manage', 'automotive_service')
                        <a href="{{ route('automotive.admin.access.users.products.edit', $user) }}" class="btn btn-primary d-inline-flex align-items-center">
                            <i class="isax isax-layer me-1"></i>{{ __('access.manage_product_access') }}
                        </a>
                    @endproductCan
                </div>
            </div>

            @include('automotive.admin.access.users.partials._alerts')

            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="d-flex align-items-center">
                            <span class="avatar avatar-xl bg-primary-transparent rounded-circle me-3">
                                <i class="isax isax-user text-primary fs-24"></i>
                            </span>
                            <div>
                                <h5 class="mb-1">{{ $user->name }}</h5>
                                <p class="mb-2 text-muted">{{ $user->email }}</p>
                                <span class="badge badge-soft-success d-inline-flex align-items-center me-1">{{ __('tenant.active') }} <i class="isax isax-tick-circle ms-1"></i></span>
                                @if($isOwner)
                                    <span class="badge bg-primary-transparent text-primary border me-1">{{ __('access.workspace_owner') }}</span>
                                    <span class="badge bg-info-transparent text-info border me-1">{{ __('access.implicit_full_access') }}</span>
                                    <span class="badge bg-success-transparent text-success border">{{ __('access.does_not_consume_product_seat') }}</span>
                                @endif
                                @if((int) $user->id === (int) $currentUserId)
                                    <span class="badge bg-success-light text-success">{{ __('tenant.current_login_account') }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            @productCan('automotive_service.access.branches.manage', 'automotive_service')
                                <a href="{{ route('automotive.admin.access.users.branches.edit', $user) }}" class="btn btn-outline-white d-inline-flex align-items-center">
                                    <i class="isax isax-buildings me-1"></i>{{ __('access.manage_branch_access') }}
                                </a>
                            @endproductCan
                            @if($isOwner)
                                @ownerAccess
                                <form method="POST" action="{{ route('automotive.admin.access.users.owner.sync', $user) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-white d-inline-flex align-items-center">
                                        <i class="isax isax-refresh me-1"></i>{{ __('access.sync_owner_access') }}
                                    </button>
                                </form>
                                @endownerAccess
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <ul class="nav nav-tabs nav-solid-primary nav-justified mb-3" role="tablist">
                <li class="nav-item"><a class="nav-link active" href="#overview" data-bs-toggle="tab">{{ __('access.overview') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="#products" data-bs-toggle="tab">{{ __('access.products') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="#branches" data-bs-toggle="tab">{{ __('access.branches') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="#roles" data-bs-toggle="tab">{{ __('access.roles') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="#effective-permissions" data-bs-toggle="tab">{{ __('access.effective_permissions') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="#warnings" data-bs-toggle="tab">{{ __('access.access_warnings') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="#activity" data-bs-toggle="tab">{{ __('access.activity') }}</a></li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane show active" id="overview">
                    <div class="row">
                        @foreach([
                            ['label' => __('access.product_access'), 'value' => $profile['summary']['product_count'], 'hint' => __('access.enabled_products'), 'icon' => 'isax-layer', 'class' => 'bg-primary-transparent text-primary'],
                            ['label' => __('access.branch_access'), 'value' => $profile['summary']['branch_count'], 'hint' => __('access.assigned_branches'), 'icon' => 'isax-buildings', 'class' => 'bg-secondary-transparent text-secondary'],
                            ['label' => __('access.roles'), 'value' => $profile['summary']['role_count'], 'hint' => __('access.assigned_roles'), 'icon' => 'isax-shield-tick', 'class' => 'bg-warning-transparent text-warning'],
                            ['label' => __('access.effective_permissions'), 'value' => $profile['summary']['permission_count'], 'hint' => __('access.granted_permissions'), 'icon' => 'isax-lock', 'class' => 'bg-info-transparent text-info'],
                            ['label' => __('access.access_warnings'), 'value' => $profile['summary']['warning_count'], 'hint' => __('access.items_need_review'), 'icon' => 'isax-danger', 'class' => 'bg-danger-transparent text-danger'],
                            ['label' => __('access.seat_impact'), 'value' => $profile['summary']['consumes_seats'] ? __('access.yes') : __('access.no'), 'hint' => $isOwner ? __('access.does_not_consume_product_seat') : __('access.product_wise_seat_usage'), 'icon' => 'isax-user-tick', 'class' => 'bg-success-transparent text-success'],
                        ] as $card)
                            <div class="col-xxl-2 col-xl-4 col-md-6 d-flex">
                                <div class="card flex-fill">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span class="text-gray-6">{{ $card['label'] }}</span>
                                            <span class="avatar avatar-sm {{ explode(' ', $card['class'])[0] }} rounded-circle">
                                                <i class="isax {{ $card['icon'] }} {{ explode(' ', $card['class'])[1] }}"></i>
                                            </span>
                                        </div>
                                        <h4 class="mb-1">{{ $card['value'] }}</h4>
                                        <p class="mb-0 text-muted small">{{ $card['hint'] }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($isOwner)
                        <div class="alert alert-info d-flex align-items-start gap-2">
                            <i class="isax isax-shield-tick mt-1"></i>
                            <div>
                                <strong>{{ __('access.owner_access') }}</strong>
                                <div>{{ __('access.owner_product_access_hint') }} {{ __('access.owner_branch_access_hint') }}</div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="tab-pane" id="products">
                    <div class="table-responsive">
                        <table class="table table-nowrap">
                            <thead class="thead-light">
                                <tr>
                                    <th>{{ __('access.product') }}</th>
                                    <th>{{ __('access.status') }}</th>
                                    <th>{{ __('access.access_state') }}</th>
                                    <th>{{ __('access.seats') }}</th>
                                    <th>{{ __('access.consumes_seat') }}</th>
                                    <th>{{ __('access.access_source') }}</th>
                                    <th class="no-sort"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($profile['products'] as $product)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $product['product_name'] }}</div>
                                            <span class="badge bg-light text-dark border">{{ $product['product_key'] }}</span>
                                        </td>
                                        <td>{{ $product['subscription_status'] }}<br><span class="text-muted small">{{ $product['plan_name'] ?: __('access.no_plan') }}</span></td>
                                        <td>
                                            <span class="badge {{ $product['has_access'] ? 'bg-success-transparent text-success' : 'bg-light text-muted' }} border">{{ __('access.' . $product['access_state']) }}</span>
                                        </td>
                                        <td>{{ $product['used_seats'] }} / {{ $product['seat_limit'] === null ? __('access.unlimited') : $product['seat_limit'] }}</td>
                                        <td>{{ $product['consumes_seat'] ? __('access.yes') : __('access.no') }}</td>
                                        <td><span class="badge bg-light text-dark border">{{ $product['access_source'] }}</span></td>
                                        <td class="text-end">
                                            @productCan('automotive_service.access.users.manage', 'automotive_service')
                                                <a href="{{ route('automotive.admin.access.users.products.edit', $user) }}" class="btn btn-outline-white btn-sm">{{ __('access.manage_product_access') }}</a>
                                            @endproductCan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane" id="branches">
                    @foreach($profile['branches'] as $productKey => $branches)
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">{{ $productKey }}</h6>
                                @productCan('automotive_service.access.branches.manage', 'automotive_service')
                                    <a href="{{ route('automotive.admin.access.users.branches.edit', $user) }}" class="btn btn-outline-white btn-sm">{{ __('access.manage_branch_access') }}</a>
                                @endproductCan
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-nowrap mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>{{ __('access.branch') }}</th>
                                                <th>{{ __('tenant.status') }}</th>
                                                <th>{{ __('access.product_branch_status') }}</th>
                                                <th>{{ __('access.assigned') }}</th>
                                                <th>{{ __('access.current_branch_eligible') }}</th>
                                                <th>{{ __('access.access_source') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($branches as $branch)
                                                <tr>
                                                    <td>{{ $branch['branch_name'] }}</td>
                                                    <td>{{ $branch['branch_active'] ? __('tenant.active') : __('tenant.inactive') }}</td>
                                                    <td>{{ $branch['product_branch_enabled'] ? __('access.enabled') : __('access.disabled') }}</td>
                                                    <td>{{ $branch['assigned'] ? __('access.yes') : __('access.no') }}</td>
                                                    <td>{{ $branch['current_branch_eligible'] ? __('access.yes') : __('access.no') }}</td>
                                                    <td><span class="badge bg-light text-dark border">{{ $branch['source'] }}</span></td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="6" class="text-center text-muted py-4">{{ __('access.no_branch_access_assigned') }}</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="tab-pane" id="roles">
                    <div class="d-flex justify-content-end mb-2">
                        @productCan('automotive_service.access.roles.manage', 'automotive_service')
                            <a href="{{ route('automotive.admin.access.users.roles.edit', $user) }}" class="btn btn-primary btn-sm d-inline-flex align-items-center">
                                <i class="isax isax-shield-tick me-1"></i>{{ __('access.assign_roles') }}
                            </a>
                        @endproductCan
                    </div>
                    <div class="table-responsive">
                        <table class="table table-nowrap">
                            <thead class="thead-light">
                                <tr>
                                    <th>{{ __('access.product') }}</th>
                                    <th>{{ __('access.role_name') }}</th>
                                    <th>{{ __('access.permissions_count') }}</th>
                                    <th>{{ __('access.access_source') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($profile['roles']->flatten(1) as $role)
                                    <tr>
                                        <td>{{ $role['product_key'] }}</td>
                                        <td>{{ $role['role_name'] }}</td>
                                        <td>{{ $role['permissions_count'] }}</td>
                                        <td><span class="badge bg-light text-dark border">{{ $role['source'] }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-4">{{ __('access.no_roles_assigned') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane" id="effective-permissions">
                    <div class="accordion" id="effectivePermissionAccordion">
                        @foreach($profile['permissions'] as $productKey => $groups)
                            @foreach($groups as $group)
                                @php($moduleId = 'effective-' . md5($productKey . $group['module_key']))
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="{{ $moduleId }}-heading">
                                        <button class="accordion-button {{ ! ($loop->parent->first && $loop->first) ? 'collapsed bg-light' : '' }} text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $moduleId }}">
                                            <span class="fw-bold">{{ $productKey }} / {{ $group['module'] }}</span>
                                        </button>
                                    </h2>
                                    <div id="{{ $moduleId }}" class="accordion-collapse collapse {{ $loop->parent->first && $loop->first ? 'show' : '' }}" data-bs-parent="#effectivePermissionAccordion">
                                        <div class="accordion-body">
                                            <div class="table-responsive table-nowrap">
                                                <table class="table border mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>{{ __('access.key') }}</th>
                                                            <th>{{ __('access.action') }}</th>
                                                            <th>{{ __('access.granted') }}</th>
                                                            <th>{{ __('access.source') }}</th>
                                                            <th>{{ __('access.related_role') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($group['permissions'] as $permission)
                                                            <tr>
                                                                <td><code>{{ $permission['permission_key'] }}</code></td>
                                                                <td><span class="badge bg-light text-dark border">{{ $permission['action'] }}</span></td>
                                                                <td>{{ $permission['granted'] ? __('access.yes') : __('access.no') }}</td>
                                                                <td><span class="badge {{ $permission['granted'] ? 'bg-success-transparent text-success' : 'bg-warning-transparent text-warning' }} border">{{ $permission['source'] }}</span></td>
                                                                <td>{{ implode(', ', $permission['role_names']) ?: '-' }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>

                <div class="tab-pane" id="warnings">
                    <div class="table-responsive">
                        <table class="table table-nowrap">
                            <thead class="thead-light">
                                <tr>
                                    <th>{{ __('access.severity') }}</th>
                                    <th>{{ __('access.product') }}</th>
                                    <th>{{ __('access.branch') }}</th>
                                    <th>{{ __('access.message') }}</th>
                                    <th>{{ __('access.suggested_action') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($profile['warnings'] as $warning)
                                    <tr>
                                        <td><span class="badge bg-{{ $warning['severity'] === 'danger' ? 'danger' : ($warning['severity'] === 'warning' ? 'warning' : 'info') }}-transparent text-{{ $warning['severity'] === 'danger' ? 'danger' : ($warning['severity'] === 'warning' ? 'warning' : 'info') }} border">{{ $warning['severity'] }}</span></td>
                                        <td>{{ $warning['product_key'] ?: '-' }}</td>
                                        <td>{{ $warning['branch_id'] ?: '-' }}</td>
                                        <td>{{ $warning['message'] }}</td>
                                        <td>{{ $warning['suggested_action'] }}</td>
                                        <td class="text-end">
                                            @if($warning['action_url'])
                                                <a href="{{ $warning['action_url'] }}" class="btn btn-outline-white btn-sm">{{ __('access.open') }}</a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-4">{{ __('access.no_access_warnings') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane" id="activity">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <span class="avatar avatar-lg bg-light rounded-circle mb-2">
                                <i class="isax isax-document-text text-muted"></i>
                            </span>
                            <h6>{{ __('access.activity_placeholder_title') }}</h6>
                            <p class="text-muted mb-0">{{ __('access.activity_placeholder_hint') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @include('automotive.admin.components.page-footer')
    </div>
@endsection
