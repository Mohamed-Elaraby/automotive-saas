<?php $page = 'access-control'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex d-block align-items-center justify-content-between flex-wrap gap-3 mb-3">
                <div>
                    <h4 class="mb-1">{{ __('access.roles_permission_matrix') }}</h4>
                    <p class="mb-0 text-muted">{{ __('access.roles_permission_matrix_subtitle') }}</p>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                    <div class="dropdown">
                        <a href="javascript:void(0);" class="btn btn-outline-white d-inline-flex align-items-center" data-bs-toggle="dropdown">
                            <i class="isax isax-export-1 me-1"></i>{{ __('access.export') }}
                        </a>
                        <ul class="dropdown-menu">
                            <li><span class="dropdown-item">{{ __('access.download_pdf') }}</span></li>
                            <li><span class="dropdown-item">{{ __('access.download_excel') }}</span></li>
                        </ul>
                    </div>
                    <a href="{{ route('automotive.admin.access.index') }}" class="btn btn-outline-white d-inline-flex align-items-center">
                        <i class="isax isax-arrow-left me-1"></i>{{ __('access.back_to_access_center') }}
                    </a>
                    <a href="{{ route('automotive.admin.access.roles.create') }}" class="btn btn-primary d-flex align-items-center">
                        <i class="isax isax-add-circle5 me-1"></i>{{ __('access.new_role') }}
                    </a>
                </div>
            </div>

            @include('automotive.admin.access.roles.partials._alerts')

            <form method="GET" action="{{ route('automotive.admin.access.roles.index') }}" class="mb-3">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="isax isax-search-normal fs-12"></i>
                            </span>
                            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control border-start-0 ps-0 bg-white" placeholder="{{ __('tenant.search') }}">
                        </div>
                        <select name="product_key" class="form-select">
                            <option value="">{{ __('access.all_products') }}</option>
                            @foreach($productOptions as $product)
                                <option value="{{ $product['key'] }}" @selected(($filters['product_key'] ?? '') === $product['key'])>{{ $product['name'] }}</option>
                            @endforeach
                        </select>
                        <select name="status" class="form-select">
                            <option value="">{{ __('access.all_statuses') }}</option>
                            <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('tenant.active') }}</option>
                            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('tenant.inactive') }}</option>
                        </select>
                        <button type="submit" class="btn btn-outline-white d-inline-flex align-items-center">
                            <i class="isax isax-filter me-1"></i>{{ __('access.filter') }}
                        </button>
                    </div>
                    <a href="{{ route('automotive.admin.access.roles.index') }}" class="btn btn-outline-white d-inline-flex align-items-center">
                        <i class="isax isax-refresh me-1"></i>{{ __('access.clear_filters') }}
                    </a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-nowrap datatable">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ __('access.role_name') }}</th>
                            <th>{{ __('access.product') }}</th>
                            <th>{{ __('access.users_count') }}</th>
                            <th>{{ __('access.permissions_count') }}</th>
                            <th>{{ __('access.type') }}</th>
                            <th>{{ __('tenant.status') }}</th>
                            <th>{{ __('access.created_at') }}</th>
                            <th class="no-sort"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($roles as $role)
                            @php($isTemplate = (bool) ($role->metadata['is_template'] ?? false))
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="avatar avatar-sm bg-primary-transparent rounded-circle me-2">
                                            <i class="isax isax-shield-tick text-primary"></i>
                                        </span>
                                        <div>
                                            <h6 class="fs-14 fw-medium mb-0">{{ $role->name }}</h6>
                                            <span class="text-muted small">{{ $role->description ?: __('access.no_description') }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border">{{ $role->product_key }}</span></td>
                                <td>{{ $role->users_count }}</td>
                                <td>{{ $role->permissions_count }}</td>
                                <td>
                                    @if($role->is_system)
                                        <span class="badge bg-warning-transparent text-warning border">{{ __('access.system_role') }}</span>
                                    @endif
                                    @if($isTemplate)
                                        <span class="badge bg-info-transparent text-info border">{{ __('access.template_role') }}</span>
                                    @endif
                                    @if(! $role->is_system && ! $isTemplate)
                                        <span class="badge bg-light text-muted border">{{ __('access.custom_role') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($role->is_active)
                                        <span class="badge badge-soft-success d-inline-flex align-items-center">{{ __('tenant.active') }} <i class="isax isax-tick-circle ms-1"></i></span>
                                    @else
                                        <span class="badge badge-soft-danger d-inline-flex align-items-center">{{ __('tenant.inactive') }} <i class="isax isax-close-circle ms-1"></i></span>
                                    @endif
                                </td>
                                <td>{{ optional($role->created_at)->format('d M Y') }}</td>
                                <td class="text-end action-item">
                                    <a href="{{ route('automotive.admin.access.roles.permissions.edit', $role) }}" class="btn btn-outline-white d-inline-flex align-items-center me-2">
                                        <i class="isax isax-shield-tick me-1"></i>{{ __('access.permissions') }}
                                    </a>
                                    <a href="javascript:void(0);" data-bs-toggle="dropdown">
                                        <i class="fa-solid fa-ellipsis-vertical"></i>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a href="{{ route('automotive.admin.access.roles.edit', $role) }}" class="dropdown-item d-flex align-items-center">
                                                <i class="isax isax-edit me-2"></i>{{ __('tenant.edit') }}
                                            </a>
                                        </li>
                                        <li>
                                            <form method="POST" action="{{ route('automotive.admin.access.roles.duplicate', $role) }}">
                                                @csrf
                                                <button type="submit" class="dropdown-item d-flex align-items-center">
                                                    <i class="isax isax-copy me-2"></i>{{ __('access.duplicate') }}
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <form method="POST" action="{{ route('automotive.admin.access.roles.destroy', $role) }}" onsubmit="return confirm('{{ __('access.delete_role_confirmation') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item d-flex align-items-center text-danger" @disabled($role->is_system || $role->users_count > 0)>
                                                    <i class="isax isax-trash me-2"></i>{{ __('tenant.delete') }}
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <span class="avatar avatar-lg bg-primary-transparent rounded-circle mb-2">
                                        <i class="isax isax-shield-search text-primary"></i>
                                    </span>
                                    <h6 class="mb-1">{{ __('access.no_roles_found') }}</h6>
                                    <p class="text-muted mb-3">{{ __('access.no_roles_found_hint') }}</p>
                                    <a href="{{ route('automotive.admin.access.roles.create') }}" class="btn btn-primary d-inline-flex align-items-center">
                                        <i class="isax isax-add-circle5 me-1"></i>{{ __('access.new_role') }}
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $roles->links() }}
            </div>
        </div>

        @include('automotive.admin.components.page-footer')
    </div>
@endsection
