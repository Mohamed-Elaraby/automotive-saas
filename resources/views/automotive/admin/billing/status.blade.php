<?php $page = 'billing-transition'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    @php
        $productBillingLabel = $billingProductName ?: 'Workspace Product';
        $billingStatusLabel = strtoupper(str_replace('_', ' ', (string) ($billingState['status'] ?? 'unknown')));
    @endphp

    <div class="page-wrapper">
        <div class="content container-fluid">
            @include('automotive.admin.partials.page-header', [
                'title' => 'Subscription Access',
                'subtitle' => 'Tenant admin no longer owns account or billing changes. Use it only for runtime modules and operations.',
                'breadcrumbs' => [
                    ['label' => 'Dashboard', 'url' => route('automotive.admin.dashboard', array_filter(['workspace_product' => $focusedWorkspaceProduct['product_code'] ?? null]))],
                    ['label' => 'Subscription Access'],
                ],
                'actions' => null,
            ])

            @include('automotive.admin.partials.alerts')

            <div class="row">
                <div class="col-lg-8">
                    <div class="card border-warning">
                        <div class="card-body">
                            <div class="d-flex align-items-start gap-3">
                                <span class="avatar avatar-md bg-warning text-dark flex-shrink-0">
                                    <i class="isax isax-info-circle fs-20"></i>
                                </span>
                                <div>
                                    <h5 class="mb-2">Billing Moved To Customer Portal</h5>
                                    <p class="mb-3 text-muted">{{ $decommissionMessage }}</p>
                                    <div class="alert alert-light border mb-0">
                                        Use the customer portal for subscription changes, plan upgrades, invoice history, payment methods, and account-level settings.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Current Workspace Access Snapshot</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted small mb-1">Focused Product</div>
                                        <div class="fw-semibold">{{ $productBillingLabel }}</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted small mb-1">Workspace Access Status</div>
                                        <div class="fw-semibold">{{ $billingStatusLabel }}</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted small mb-1">Current Plan</div>
                                        <div class="fw-semibold">{{ $plan->name ?? $plan->slug ?? 'No active plan' }}</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted small mb-1">Tenant</div>
                                        <div class="fw-semibold">{{ $tenant->id ?? '-' }}</div>
                                    </div>
                                </div>
                            </div>

                            @if(!empty($billingState['message']))
                                <div class="alert alert-info mt-4 mb-0">
                                    {{ $billingState['message'] }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Next Step</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">Open the customer portal to manage subscription and account ownership tasks.</p>

                            <div class="d-grid gap-2">
                                <a href="{{ $portalBillingUrl }}" class="btn btn-primary">
                                    Open Customer Portal Billing
                                </a>
                                <a href="{{ $portalOverviewUrl }}" class="btn btn-outline-light">
                                    Open Customer Portal Home
                                </a>
                                <a href="{{ route('automotive.admin.dashboard', array_filter(['workspace_product' => $focusedWorkspaceProduct['product_code'] ?? null])) }}" class="btn btn-outline-secondary">
                                    Back To Runtime Dashboard
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Workspace Products</h5>
                        </div>
                        <div class="card-body">
                            @forelse($workspaceProducts as $workspaceProduct)
                                @php($isFocusedProduct = (string) ($focusedWorkspaceProduct['product_code'] ?? '') === (string) ($workspaceProduct['product_code'] ?? ''))
                                <a
                                    href="{{ route('automotive.admin.billing.status', ['workspace_product' => $workspaceProduct['product_code']]) }}"
                                    class="d-block border rounded p-3 mb-2 text-decoration-none {{ $isFocusedProduct ? 'border-primary bg-light' : '' }}"
                                >
                                    <div class="fw-semibold text-dark">{{ $workspaceProduct['product_name'] }}</div>
                                    <div class="small text-muted">{{ $workspaceProduct['is_accessible'] ? 'Runtime connected' : ($workspaceProduct['status_label'] ?? 'Unavailable') }}</div>
                                </a>
                            @empty
                                <p class="text-muted mb-0">No connected runtime products were found for this tenant.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
