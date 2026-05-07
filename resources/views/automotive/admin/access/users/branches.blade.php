<?php $page = 'access-control'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex d-block align-items-center justify-content-between flex-wrap gap-3 mb-3">
                <div>
                    <h4 class="mb-1">{{ __('access.manage_branch_access') }}</h4>
                    <p class="mb-0 text-muted">{{ __('access.manage_branch_access_subtitle') }}</p>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                    <a href="{{ route('automotive.admin.access.users.index') }}" class="btn btn-outline-white d-inline-flex align-items-center">
                        <i class="isax isax-arrow-left me-1"></i>{{ __('access.back_to_users') }}
                    </a>
                </div>
            </div>

            @include('automotive.admin.partials.alerts')

            @if($isPrimaryOwner)
                <div class="alert alert-primary d-flex align-items-start gap-2">
                    <i class="isax isax-buildings mt-1"></i>
                    <div>
                        <div class="fw-semibold">{{ __('access.workspace_owner') }} · {{ __('access.owner_implicit_branch_access') }}</div>
                        <div>{{ __('access.owner_branch_access_hint') }}</div>
                    </div>
                </div>
            @endif

            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="d-flex align-items-center">
                            <span class="avatar avatar-lg rounded-circle bg-primary-transparent me-3">
                                <i class="isax isax-user text-primary"></i>
                            </span>
                            <div>
                                <h5 class="mb-1">{{ $user->name }}</h5>
                                <p class="mb-0 text-muted">{{ $user->email }}</p>
                            </div>
                        </div>
                        <span class="badge badge-soft-success d-inline-flex align-items-center">
                            {{ __('tenant.active') }} <i class="isax isax-tick-circle ms-1"></i>
                        </span>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('automotive.admin.access.users.branches.update', $user) }}">
                @csrf
                @method('PUT')

                <div class="row">
                    @forelse($productRows as $row)
                        <div class="col-xl-6 d-flex">
                            <div class="card flex-fill">
                                <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div>
                                        <h5 class="card-title mb-0">{{ $row['product_key'] }}</h5>
                                        <p class="mb-0 text-muted small">{{ __('access.user_branches_hint') }}</p>
                                    </div>
                                    <span class="badge bg-success-transparent text-success border">
                                        {{ __('access.product_access_enabled') }}
                                    </span>
                                </div>
                                <div class="card-body">
                                    @forelse($row['enabled_branches'] as $branch)
                                        <label class="dropdown-item d-flex align-items-center justify-content-between border rounded p-3 mb-2">
                                            <span class="d-flex align-items-center">
                                                <span class="avatar avatar-sm rounded-circle bg-primary-transparent me-2">
                                                    <i class="isax isax-buildings text-primary"></i>
                                                </span>
                                                <span>
                                                    <span class="fw-semibold d-block">{{ $branch->name }}</span>
                                                    <span class="text-muted small">{{ $branch->code ?: __('access.no_code') }}</span>
                                                </span>
                                            </span>
                                            <span class="d-flex align-items-center gap-2">
                                                <span class="badge bg-light text-muted border">{{ __('access.access_level_member') }}</span>
                                                @if($row['owner_implicit'] ?? false)
                                                    <span class="badge bg-primary-transparent text-primary border">{{ __('access.implicit_full_access') }}</span>
                                                @else
                                                    <input
                                                        class="form-check-input m-0"
                                                        type="checkbox"
                                                        name="branches[{{ $row['product_key'] }}][]"
                                                        value="{{ $branch->id }}"
                                                        @checked($row['assigned_branch_ids']->contains((int) $branch->id))
                                                    >
                                                @endif
                                            </span>
                                        </label>
                                    @empty
                                        <div class="alert alert-warning mb-0">
                                            {{ __('access.no_enabled_product_branches') }}
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-center">
                                    <span class="avatar avatar-lg bg-light rounded-circle mb-2">
                                        <i class="isax isax-layer text-muted"></i>
                                    </span>
                                    <h5>{{ __('access.no_product_access') }}</h5>
                                    <p class="text-muted mb-0">{{ __('access.user_needs_product_access_first') }}</p>
                                </div>
                            </div>
                        </div>
                    @endforelse
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('automotive.admin.access.users.index') }}" class="btn btn-outline-white">{{ __('tenant.cancel') }}</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="isax isax-tick-circle me-1"></i>{{ __('tenant.save_changes') }}
                    </button>
                </div>
            </form>
        </div>

        @include('automotive.admin.components.page-footer')
    </div>
@endsection
