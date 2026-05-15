<?php $page = 'access-control'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex d-block align-items-center justify-content-between flex-wrap gap-3 mb-3">
                <div>
                    <h4 class="mb-1">Access Diagnostics</h4>
                    <p class="mb-0 text-muted">Explain product, branch, route, and permission access decisions.</p>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                    <a href="{{ route('automotive.admin.access.audit.index') }}" class="btn btn-outline-white d-inline-flex align-items-center">
                        <i class="isax isax-document-text me-1"></i>Audit Logs
                    </a>
                    <a href="{{ route('automotive.admin.access.index') }}" class="btn btn-primary d-flex align-items-center">
                        <i class="isax isax-arrow-left me-1"></i>Back to Access
                    </a>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" action="{{ route('automotive.admin.access.diagnostics.index') }}" class="row g-3 align-items-end">
                        <div class="col-xl-3 col-md-6">
                            <label class="form-label">User</label>
                            <select name="user_id" class="form-select" required>
                                <option value="">Select user</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" @selected(($filters['user_id'] ?? '') == $user->id)>{{ $user->name }} - {{ $user->email }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-6">
                            <label class="form-label">Product</label>
                            <select name="product_key" class="form-select">
                                @foreach($products as $product)
                                    <option value="{{ $product }}" @selected(($filters['product_key'] ?? 'automotive_service') === $product)>{{ $product }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-6">
                            <label class="form-label">Branch</label>
                            <select name="branch_id" class="form-select">
                                <option value="">Any / current</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? '') == $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <label class="form-label">Permission Key</label>
                            <input type="text" name="permission_key" value="{{ $filters['permission_key'] ?? '' }}" class="form-control" placeholder="automotive_service.access.roles.manage">
                        </div>
                        <div class="col-xl-2 col-md-6">
                            <label class="form-label">Route Name</label>
                            <input type="text" name="route_name" value="{{ $filters['route_name'] ?? '' }}" class="form-control" placeholder="automotive.admin.access.roles.index">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary d-inline-flex align-items-center">
                                <i class="isax isax-search-status me-1"></i>Run Diagnostics
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            @if($result)
                @include('automotive.admin.access.diagnostics.partials._diagnostic-result', ['result' => $result])
            @else
                <div class="alert alert-info d-flex align-items-center">
                    <i class="isax isax-info-circle me-2"></i>Select a user and run diagnostics.
                </div>
            @endif
        </div>
    </div>
@endsection
