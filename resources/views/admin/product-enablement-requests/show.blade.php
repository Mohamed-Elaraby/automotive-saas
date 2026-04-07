<?php $page = 'product-enablement-requests-show'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                <div>
                    <div class="text-muted small mb-1">
                        <a href="{{ route('admin.product-enablement-requests.index') }}" class="text-decoration-none">Product Enablement Requests</a>
                        <span class="mx-1">/</span>
                        Request #{{ $requestRow->id }}
                    </div>
                    <h4 class="mb-1">Enablement Request Details</h4>
                    <p class="text-muted mb-0">Review the request context before approving or rejecting the product attachment.</p>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('admin.product-enablement-requests.index') }}" class="btn btn-outline-white">Back To Requests</a>
                    @if($requestRow->status === 'pending')
                        <form method="POST" action="{{ route('admin.product-enablement-requests.approve', $requestRow->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-success">Approve</button>
                        </form>
                        <form method="POST" action="{{ route('admin.product-enablement-requests.reject', $requestRow->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger">Reject</button>
                        </form>
                    @endif
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="mb-3">Request Summary</h5>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted small mb-1">Product</div>
                                        <div class="fw-semibold">{{ $requestRow->product?->name ?? '-' }}</div>
                                        <div class="text-muted small">{{ strtoupper((string) ($requestRow->product?->code ?? '')) }}</div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted small mb-1">Status</div>
                                        <span class="badge
                                            @if($requestRow->status === 'approved') bg-success
                                            @elseif($requestRow->status === 'rejected') bg-danger
                                            @else bg-warning
                                            @endif">
                                            {{ strtoupper((string) $requestRow->status) }}
                                        </span>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted small mb-1">Tenant</div>
                                        <div class="fw-semibold">{{ $requestRow->tenant_id }}</div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted small mb-1">Requested At</div>
                                        <div class="fw-semibold">{{ optional($requestRow->requested_at)->format('Y-m-d H:i') ?: '-' }}</div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted small mb-1">Approved At</div>
                                        <div class="fw-semibold">{{ optional($requestRow->approved_at)->format('Y-m-d H:i') ?: '-' }}</div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted small mb-1">Rejected At</div>
                                        <div class="fw-semibold">{{ optional($requestRow->rejected_at)->format('Y-m-d H:i') ?: '-' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="mb-3">Customer Context</h5>
                            <div class="border rounded p-3">
                                <div class="text-muted small mb-1">Portal User</div>
                                <div class="fw-semibold">{{ $requestRow->user?->name ?? '-' }}</div>
                                <div class="text-muted small">{{ $requestRow->user?->email ?? '-' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <h5 class="mb-3">Current Product Attachment</h5>

                            @if($latestProductSubscription)
                                <div class="border rounded p-3">
                                    <div class="text-muted small mb-1">Latest Tenant Product Subscription</div>
                                    <div class="fw-semibold mb-2">Status: {{ strtoupper((string) $latestProductSubscription->status) }}</div>
                                    <div class="small text-muted">Subscription ID: {{ $latestProductSubscription->id }}</div>
                                    <div class="small text-muted">Legacy Link: {{ $latestProductSubscription->legacy_subscription_id ?: '-' }}</div>
                                    <div class="small text-muted">Plan ID: {{ $latestProductSubscription->plan_id ?: '-' }}</div>
                                    <div class="small text-muted">Gateway: {{ $latestProductSubscription->gateway ?: '-' }}</div>
                                </div>
                            @else
                                <div class="alert alert-light border mb-0">
                                    No tenant product subscription is attached yet for this product.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
