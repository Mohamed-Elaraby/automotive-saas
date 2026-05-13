<?php $page = 'access-control'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex d-block align-items-center justify-content-between flex-wrap gap-3 mb-3">
                <div>
                    <h4 class="mb-1">
                        <a href="{{ route('automotive.admin.access.roles.index') }}" class="text-dark">
                            <i class="isax isax-arrow-left me-1"></i>{{ __('access.roles') }}
                        </a>
                    </h4>
                    <p class="mb-0 text-muted">{{ __('access.permission_matrix') }}</p>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                    @productCan('automotive_service.access.roles.manage', 'automotive_service')
                        <a href="{{ route('automotive.admin.access.roles.edit', $role) }}" class="btn btn-outline-white d-inline-flex align-items-center">
                            <i class="isax isax-edit me-1"></i>{{ __('tenant.edit') }}
                        </a>
                    @endproductCan
                    <a href="{{ route('automotive.admin.access.roles.index') }}" class="btn btn-outline-white d-inline-flex align-items-center">
                        <i class="isax isax-arrow-left me-1"></i>{{ __('access.back_to_roles') }}
                    </a>
                </div>
            </div>

            @include('automotive.admin.access.roles.partials._alerts')

            <div class="row">
                <div class="col-xl-3 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body">
                            <span class="text-gray-6">{{ __('access.role_name') }}</span>
                            <h5 class="mb-1">{{ $role->name }}</h5>
                            <span class="badge bg-light text-dark border">{{ $role->product_key }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body">
                            <span class="text-gray-6">{{ __('access.selected_permissions') }}</span>
                            <h5 class="mb-1"><span data-selected-count>{{ count($selectedPermissionKeys) }}</span> / {{ $totalPermissions }}</h5>
                            <span class="text-muted small">{{ __('access.permissions_selected_hint') }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body">
                            <span class="text-gray-6">{{ __('access.assigned_users') }}</span>
                            <h5 class="mb-1">{{ $assignedUsersCount }}</h5>
                            <span class="text-muted small">{{ __('access.package_14_handles_full_user_profile') }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body">
                            <span class="text-gray-6">{{ __('tenant.status') }}</span>
                            <h5 class="mb-1">{{ $role->is_active ? __('tenant.active') : __('tenant.inactive') }}</h5>
                            @if($role->is_system)
                                <span class="badge bg-warning-transparent text-warning border">{{ __('access.system_role') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('automotive.admin.access.roles.permissions.update', $role) }}" data-permission-form>
                @csrf
                @method('PUT')

                @include('automotive.admin.access.roles.partials._permission-matrix', [
                    'groupedPermissions' => $groupedPermissions,
                    'selectedPermissionKeys' => $selectedPermissionKeys,
                    'totalPermissions' => $totalPermissions,
                ])

                <div class="d-flex justify-content-between flex-wrap gap-2 mt-3">
                    <a href="{{ route('automotive.admin.access.roles.index') }}" class="btn btn-outline-white d-inline-flex align-items-center">
                        <i class="isax isax-arrow-left me-1"></i>{{ __('access.back_to_roles') }}
                    </a>
                    <div class="d-flex gap-2">
                        @productCan('automotive_service.access.roles.manage', 'automotive_service')
                            <button type="reset" class="btn btn-outline-white d-inline-flex align-items-center">
                                <i class="isax isax-refresh me-1"></i>{{ __('access.reset_changes') }}
                            </button>
                            <button type="submit" class="btn btn-primary d-inline-flex align-items-center">
                                <i class="isax isax-save-2 me-1"></i>{{ __('access.save_permissions') }}
                            </button>
                        @else
                            @include('automotive.admin.access.partials._access-denied-hint', [
                                'label' => __('access.save_permissions'),
                                'icon' => 'isax-lock',
                                'permission' => 'automotive_service.access.roles.manage',
                            ])
                        @endproductCan
                    </div>
                </div>
            </form>
        </div>

        @include('automotive.admin.components.page-footer')
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('[data-permission-form]');
            if (!form) {
                return;
            }

            const boxes = Array.from(form.querySelectorAll('[data-permission-checkbox]'));
            const countTarget = document.querySelector('[data-selected-count]');
            const search = form.querySelector('[data-permission-search]');

            const updateCount = () => {
                countTarget.textContent = boxes.filter((box) => box.checked).length;
            };

            const setModule = (moduleKey, checked) => {
                boxes.filter((box) => box.dataset.module === moduleKey).forEach((box) => box.checked = checked);
                updateCount();
            };

            form.querySelectorAll('[data-select-module]').forEach((button) => {
                button.addEventListener('click', () => setModule(button.dataset.selectModule, true));
            });

            form.querySelectorAll('[data-clear-module]').forEach((button) => {
                button.addEventListener('click', () => setModule(button.dataset.clearModule, false));
            });

            const selectAll = form.querySelector('[data-select-all]');
            if (selectAll) {
                selectAll.addEventListener('click', () => {
                    boxes.forEach((box) => box.checked = true);
                    updateCount();
                });
            }

            const clearAll = form.querySelector('[data-clear-all]');
            if (clearAll) {
                clearAll.addEventListener('click', () => {
                    boxes.forEach((box) => box.checked = false);
                    updateCount();
                });
            }

            form.querySelectorAll('[data-preset]').forEach((button) => {
                button.addEventListener('click', () => {
                    const preset = button.dataset.preset;
                    boxes.forEach((box) => {
                        const action = box.dataset.action;
                        box.checked = preset === 'full'
                            || (preset === 'read' && action === 'view')
                            || (preset === 'manager' && ['view', 'create', 'edit', 'approve', 'export', 'manage', 'assign', 'switch_branch'].includes(action));
                    });
                    updateCount();
                });
            });

            search.addEventListener('input', () => {
                const term = search.value.toLowerCase().trim();
                form.querySelectorAll('[data-permission-module-card]').forEach((card) => {
                    const matches = card.textContent.toLowerCase().includes(term);
                    card.classList.toggle('d-none', term !== '' && !matches);
                });
            });

            boxes.forEach((box) => box.addEventListener('change', updateCount));
            form.addEventListener('reset', () => setTimeout(updateCount, 0));
            updateCount();
        });
    </script>
@endpush
