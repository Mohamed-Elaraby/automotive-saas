<?php $page = 'access-control'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex d-block align-items-center justify-content-between flex-wrap gap-3 mb-3">
                <div>
                    <h4 class="mb-1">{{ __('access.edit_role') }}</h4>
                    <p class="mb-0 text-muted">{{ $role->name }}</p>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                    <a href="{{ route('automotive.admin.access.roles.permissions.edit', $role) }}" class="btn btn-outline-white d-inline-flex align-items-center">
                        <i class="isax isax-shield-tick me-1"></i>{{ __('access.permissions') }}
                    </a>
                    <a href="{{ route('automotive.admin.access.roles.index') }}" class="btn btn-outline-white d-inline-flex align-items-center">
                        <i class="isax isax-arrow-left me-1"></i>{{ __('access.back_to_roles') }}
                    </a>
                </div>
            </div>

            @include('automotive.admin.access.roles.partials._alerts')

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('automotive.admin.access.roles.update', $role) }}">
                        @csrf
                        @method('PUT')
                        @include('automotive.admin.access.roles.partials._form')
                    </form>
                </div>
            </div>
        </div>

        @include('automotive.admin.components.page-footer')
    </div>
@endsection
