<?php $page = 'access-control'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex d-block align-items-center justify-content-between flex-wrap gap-3 mb-3">
                <div>
                    <h4 class="mb-1">{{ __('access.select_branch') }}</h4>
                    <p class="mb-0 text-muted">{{ __('access.select_branch_subtitle') }}</p>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                    <a href="{{ route('automotive.admin.dashboard') }}" class="btn btn-outline-white d-inline-flex align-items-center">
                        <i class="isax isax-arrow-left me-1"></i>{{ __('shared.dashboard') }}
                    </a>
                </div>
            </div>

            @include('automotive.admin.partials.alerts')

            @if($productRows->every(fn ($row) => $row['branches']->isEmpty()))
                <div class="card">
                    <div class="card-body text-center py-5">
                        <span class="avatar avatar-xl bg-warning-transparent rounded-circle mb-3">
                            <i class="isax isax-location-cross text-warning fs-3"></i>
                        </span>
                        <h5>{{ __('access.no_branch_access_assigned') }}</h5>
                        <p class="text-muted mb-0">{{ __('access.no_branch_access_contact_admin') }}</p>
                    </div>
                </div>
            @else
                <div class="row">
                    @foreach($productRows as $row)
                        <div class="col-xl-6 d-flex">
                            <div class="card flex-fill">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">{{ $row['product_key'] }}</h5>
                                </div>
                                <div class="card-body">
                                    @forelse($row['branches'] as $branch)
                                        <form method="POST" action="{{ route('automotive.admin.access.branch-context.store') }}" class="mb-2">
                                            @csrf
                                            <input type="hidden" name="product_key" value="{{ $row['product_key'] }}">
                                            <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                                            <button type="submit" class="btn btn-outline-white w-100 d-flex align-items-center justify-content-between p-3">
                                                <span class="d-flex align-items-center text-start">
                                                    <span class="avatar avatar-sm rounded-circle bg-primary-transparent me-2">
                                                        <i class="isax isax-buildings text-primary"></i>
                                                    </span>
                                                    <span>
                                                        <span class="fw-semibold d-block">{{ $branch->name }}</span>
                                                        <span class="text-muted small">{{ $branch->code ?: __('access.no_code') }}</span>
                                                    </span>
                                                </span>
                                                <i class="isax isax-arrow-right-3"></i>
                                            </button>
                                        </form>
                                    @empty
                                        <div class="alert alert-warning mb-0">
                                            {{ __('access.no_branch_access_assigned') }}
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        @include('automotive.admin.components.page-footer')
    </div>
@endsection
