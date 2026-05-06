<?php $page = 'users'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content container-fluid">

            @include('automotive.admin.partials.page-header', [
                'title' => __('shared.users'),
                'subtitle' => __('tenant.users_subtitle'),
                'breadcrumbs' => [
                    ['label' => __('shared.dashboard'), 'url' => route('automotive.admin.dashboard')],
                    ['label' => __('shared.users')],
                ],
            ])

            <div class="mb-3">
                <a href="{{ route('automotive.admin.users.create') }}" class="btn btn-primary">
                    <i class="isax isax-add me-1"></i> {{ __('tenant.add_user') }}
                </a>
            </div>

            <div class="card">
                <div class="card-body">
                    @include('automotive.admin.partials.alerts')

                    <div class="alert alert-info d-flex align-items-start gap-2 mb-4">
                        <i class="isax isax-info-circle mt-1"></i>
                        <div>
                            <strong>{{ __('tenant.users_scope_title') }}</strong>
                            <div>{{ __('tenant.users_scope_message') }}</div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-center table-hover datatable">
                            <thead class="thead-light">
                            <tr>
                                <th>{{ __('tenant.name') }}</th>
                                <th>{{ __('tenant.email') }}</th>
                                <th>{{ __('tenant.maintenance_role') }}</th>
                                <th>{{ __('tenant.maintenance_access') }}</th>
                                <th>{{ __('tenant.created_at') }}</th>
                                <th class="text-end">{{ __('tenant.actions') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($users as $user)
                                @php
                                    $permissionSummary = $permissionSummaries[$user->id] ?? null;
                                    $role = $user->maintenance_role;
                                    $roleLabel = $role ? ($roleLabels[$role] ?? $role) : __('tenant.legacy_full_access');
                                    $isCurrentUser = (int) $currentUserId === (int) $user->id;
                                    $isWorkspaceOwner = (int) $user->id === 1;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $user->name }}</div>
                                        <div class="d-flex flex-wrap gap-1 mt-1">
                                            @if($isWorkspaceOwner)
                                                <span class="badge bg-primary-light text-primary">{{ __('tenant.workspace_owner_account') }}</span>
                                            @endif
                                            @if($isCurrentUser)
                                                <span class="badge bg-success-light text-success">{{ __('tenant.current_login_account') }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>{{ $user->email }}</td>
                                    <td>
                                        <span class="badge bg-light text-dark">{{ $roleLabel }}</span>
                                    </td>
                                    <td>
                                        @if($permissionSummary)
                                            <span class="badge bg-light text-dark">
                                                {{ __('tenant.permissions_count', [
                                                    'allowed' => $permissionSummary['allowed_count'],
                                                    'total' => $permissionSummary['total_count'],
                                                ]) }}
                                            </span>
                                            <div class="text-muted small">
                                                {{ __('tenant.access_modes.' . $permissionSummary['mode']) }}
                                            </div>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>{{ optional($user->created_at)->format('Y-m-d H:i') }}</td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <a href="{{ route('automotive.admin.users.edit', $user) }}"
                                               class="btn btn-sm btn-outline-primary">
                                                {{ __('tenant.edit') }}
                                            </a>

                                            <form action="{{ route('automotive.admin.users.destroy', $user) }}"
                                                  method="POST"
                                                  onsubmit="return confirm('{{ __('tenant.delete_user_confirm') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" @disabled($isCurrentUser || $isWorkspaceOwner)>
                                                    {{ __('tenant.delete') }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($users->isEmpty())
                        <div class="mt-3">
                            @include('automotive.admin.partials.empty-state', [
                                'title' => __('tenant.no_users_found'),
                                'message' => __('tenant.no_users_message'),
                            ])
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
