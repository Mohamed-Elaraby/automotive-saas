<?php $page = 'billing-features'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two pb-0">

            <div class="d-flex d-block align-items-center justify-content-between flex-wrap gap-3 mb-3">
                <div>
                    <h6>{{ $feature->name }}</h6>
                    <p class="mb-0">Feature usage across billing plans and current catalog metadata.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.billing-features.index') }}" class="btn btn-outline-white">Back to Features</a>
                    <a href="{{ route('admin.billing-features.edit', $feature) }}" class="btn btn-primary">Edit Feature</a>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted mb-1">Slug</div>
                            <h6 class="mb-0">{{ $feature->slug }}</h6>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted mb-1">Status</div>
                            @if ($feature->is_active)
                                <span class="badge badge-soft-success d-inline-flex align-items-center">Active</span>
                            @else
                                <span class="badge badge-soft-danger d-inline-flex align-items-center">Inactive</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted mb-1">Sort Order</div>
                            <h6 class="mb-0">{{ $feature->sort_order }}</h6>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted mb-1">Plans Using This Feature</div>
                            <h6 class="mb-0">{{ $feature->plans->count() }}</h6>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Feature Details</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-striped align-middle mb-0">
                        <tbody>
                        <tr>
                            <th style="width: 240px;">Name</th>
                            <td>{{ $feature->name }}</td>
                        </tr>
                        <tr>
                            <th>Description</th>
                            <td>{{ $feature->description ?: 'No description provided.' }}</td>
                        </tr>
                        <tr>
                            <th>Created At</th>
                            <td>{{ $feature->created_at ?: '-' }}</td>
                        </tr>
                        <tr>
                            <th>Updated At</th>
                            <td>{{ $feature->updated_at ?: '-' }}</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="mb-0">Plan Usage</h6>
                        <small class="text-muted">All billing plans currently linked to this feature.</small>
                    </div>
                    <a href="{{ route('admin.plans.index') }}" class="btn btn-sm btn-outline-white">Open Plans</a>
                </div>
                <div class="card-body">
                    @if ($feature->plans->isEmpty())
                        <div class="text-center py-4">
                            <p class="mb-0">This feature is not assigned to any billing plan yet.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Plan</th>
                                    <th>Billing</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Order</th>
                                    <th class="text-end">Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($feature->plans as $plan)
                                    <tr>
                                        <td>
                                            <div class="fw-medium text-dark">{{ $plan->name }}</div>
                                            <small class="text-muted">{{ $plan->slug }}</small>
                                        </td>
                                        <td>{{ ucfirst(str_replace('_', ' ', (string) ($plan->billing_period ?: 'monthly'))) }}</td>
                                        <td>{{ number_format((float) $plan->price, 2) }} {{ strtoupper((string) ($plan->currency ?: 'USD')) }}</td>
                                        <td>
                                            @if ($plan->is_active)
                                                <span class="badge badge-soft-success d-inline-flex align-items-center">Active</span>
                                            @else
                                                <span class="badge badge-soft-danger d-inline-flex align-items-center">Inactive</span>
                                            @endif
                                        </td>
                                        <td>{{ $plan->sort_order }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('admin.plans.edit', $plan) }}" class="btn btn-sm btn-outline-primary">Edit Plan</a>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

        </div>

        <div class="footer d-sm-flex align-items-center justify-content-between bg-white py-2 px-4 border-top">
            <p class="text-dark mb-0">&copy; 2025 <a href="javascript:void(0);" class="link-primary">Kanakku</a>, All Rights Reserved</p>
            <p class="text-dark">Version : 1.3.8</p>
        </div>
    </div>
@endsection
