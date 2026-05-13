<?php $page = 'access-control'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex d-block align-items-center justify-content-between flex-wrap gap-3 mb-3">
                <div>
                    <h4 class="mb-1">{{ __('access.assign_roles') }}</h4>
                    <p class="mb-0 text-muted">{{ $user->name }} · {{ $user->email }}</p>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                    <a href="{{ route('automotive.admin.access.users.show', $user) }}" class="btn btn-outline-white d-inline-flex align-items-center">
                        <i class="isax isax-arrow-left me-1"></i>{{ __('access.back_to_access_profile') }}
                    </a>
                </div>
            </div>

            @include('automotive.admin.access.users.partials._alerts')

            <form method="POST" action="{{ route('automotive.admin.access.users.roles.update', $user) }}">
                @csrf
                @method('PUT')

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-nowrap mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>{{ __('access.product') }}</th>
                                        <th>{{ __('access.access_state') }}</th>
                                        <th>{{ __('access.current_role') }}</th>
                                        <th>{{ __('access.available_roles') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($roleRows as $row)
                                        @php($product = $row['product'])
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $product['product_name'] }}</div>
                                                <span class="badge bg-light text-dark border">{{ $product['product_key'] }}</span>
                                            </td>
                                            <td>
                                                <span class="badge {{ $product['has_access'] ? 'bg-success-transparent text-success' : 'bg-light text-muted' }} border">{{ __('access.' . $product['access_state']) }}</span>
                                            </td>
                                            <td>
                                                @forelse($row['assigned_role_ids'] as $roleId)
                                                    @php($assignedRole = $row['available_roles']->firstWhere('id', $roleId))
                                                    @if($assignedRole)
                                                        <span class="badge bg-primary-transparent text-primary border">{{ $assignedRole->name }}</span>
                                                    @endif
                                                @empty
                                                    <span class="text-muted">{{ __('access.no_roles_assigned') }}</span>
                                                @endforelse
                                            </td>
                                            <td style="min-width: 280px;">
                                                <select name="roles[{{ $product['product_key'] }}]" class="form-select" @disabled(! $product['has_access'])>
                                                    <option value="">{{ __('access.no_role') }}</option>
                                                    @foreach($row['available_roles'] as $role)
                                                        <option value="{{ $role->id }}" @selected(in_array((int) $role->id, old('roles.' . $product['product_key'], $row['assigned_role_ids']), true))>
                                                            {{ $role->name }} · {{ $role->permissions_count }} {{ __('access.permissions') }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @if(! $product['has_access'])
                                                    <span class="text-muted small">{{ __('access.cannot_assign_role_without_product_access') }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                @if($isOwner)
                    <div class="alert alert-warning">
                        {{ __('access.owner_role_change_warning') }}
                    </div>
                @endif

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('automotive.admin.access.users.show', $user) }}" class="btn btn-outline-white">{{ __('tenant.cancel') }}</a>
                    <button type="submit" class="btn btn-primary d-inline-flex align-items-center">
                        <i class="isax isax-save-2 me-1"></i>{{ __('tenant.save') }}
                    </button>
                </div>
            </form>
        </div>

        @include('automotive.admin.components.page-footer')
    </div>
@endsection
