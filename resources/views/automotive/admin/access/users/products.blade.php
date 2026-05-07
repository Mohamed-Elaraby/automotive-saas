<?php $page = 'access-control'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex d-block align-items-center justify-content-between flex-wrap gap-3 mb-3">
                <div>
                    <h4 class="mb-1">{{ __('access.manage_product_access') }}</h4>
                    <p class="mb-0 text-muted">{{ __('access.manage_product_access_subtitle') }}</p>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                    <a href="{{ route('automotive.admin.access.users.index') }}" class="btn btn-outline-white d-inline-flex align-items-center">
                        <i class="isax isax-arrow-left me-1"></i>{{ __('access.back_to_users') }}
                    </a>
                </div>
            </div>

            @include('automotive.admin.partials.alerts')

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
                        <div class="d-flex gap-2 flex-wrap">
                            @if($isPrimaryOwner)
                                <span class="badge bg-primary-light text-primary">{{ __('tenant.workspace_owner_account') }}</span>
                            @endif
                            <span class="badge badge-soft-success d-inline-flex align-items-center">
                                {{ __('tenant.active') }} <i class="isax isax-tick-circle ms-1"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('automotive.admin.access.users.products.update', $user) }}">
                @csrf
                @method('PUT')

                <div class="row">
                    @forelse($productRows as $row)
                        <div class="col-xl-6 d-flex">
                            <div class="card flex-fill {{ $row['has_access'] ? 'border-success' : '' }}">
                                <div class="card-body">
                                    <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                                        <div>
                                            <h5 class="mb-1">{{ $row['product_name'] }}</h5>
                                            <p class="mb-0 text-muted">{{ $row['product_key'] }}</p>
                                        </div>
                                        <span class="badge {{ in_array($row['status'], ['active', 'trialing'], true) ? 'bg-success' : 'bg-secondary' }}">
                                            {{ $row['status'] }}
                                        </span>
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-sm-6">
                                            <div class="border rounded p-3">
                                                <div class="text-muted small">{{ __('access.plan') }}</div>
                                                <div class="fw-semibold">{{ $row['plan_name'] ?: __('access.no_plan') }}</div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="border rounded p-3">
                                                <div class="text-muted small">{{ __('access.seats') }}</div>
                                                <div class="fw-semibold">
                                                    {{ $row['used'] }} / {{ $row['limit'] === null ? __('access.unlimited') : $row['limit'] }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="border rounded p-3">
                                                <div class="text-muted small">{{ __('access.extra_seats') }}</div>
                                                <div class="fw-semibold">{{ $row['extra'] }}</div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="border rounded p-3">
                                                <div class="text-muted small">{{ __('access.available') }}</div>
                                                <div class="fw-semibold">{{ $row['available'] === null ? __('access.unlimited') : $row['available'] }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    @if($row['seat_blocked'])
                                        <div class="alert alert-warning py-2">
                                            {{ __('access.seat_limit_reached') }}
                                        </div>
                                    @endif

                                    <label class="dropdown-item d-flex align-items-center form-switch border rounded p-3">
                                        <i class="isax isax-shield-tick me-3 text-default"></i>
                                        <input
                                            class="form-check-input m-0 me-2"
                                            type="checkbox"
                                            name="products[]"
                                            value="{{ $row['product_key'] }}"
                                            @checked($row['has_access'])
                                            @disabled($row['seat_blocked'])
                                        >
                                        <span>{{ $row['has_access'] ? __('access.product_access_enabled') : __('access.enable_product_access') }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-center text-muted">{{ __('access.no_product_subscriptions') }}</div>
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
