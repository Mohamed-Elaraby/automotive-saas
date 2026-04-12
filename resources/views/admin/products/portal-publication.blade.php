<?php $page = 'products-portal-publication'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>Portal Publication Checklist</h5>
                    <p class="text-muted mb-0">Control when <strong>{{ $product->name }}</strong> becomes visible in the shared customer portal catalog.</p>
                </div>

                <a href="{{ route('admin.products.show', $product) }}" class="btn btn-outline-white">Back to Product Builder</a>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="row g-4">
                <div class="col-xl-7">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                <div>
                                    <h6 class="mb-1">Readiness</h6>
                                    <p class="text-muted mb-0">Publishing is allowed only when the minimum portal-facing setup exists.</p>
                                </div>
                                <span class="badge {{ $readyForPublication ? 'bg-success' : 'bg-warning text-dark' }}">
                                    {{ $readyForPublication ? 'Ready To Publish' : 'Blocked' }}
                                </span>
                            </div>

                            @if($blockers === [])
                                <div class="alert alert-success">
                                    No blockers remain. This product can be published to the customer portal.
                                </div>
                            @else
                                <div class="alert alert-warning">
                                    <ul class="mb-0 ps-3">
                                        @foreach($blockers as $blocker)
                                            <li>{{ $blocker }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="list-group">
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Product is active</span>
                                    <span class="badge {{ $product->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $product->is_active ? 'Yes' : 'No' }}</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Active plans</span>
                                    <span class="badge {{ $product->active_plans_count > 0 ? 'bg-success' : 'bg-warning text-dark' }}">{{ $product->active_plans_count }}</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Portal capabilities</span>
                                    <span class="badge {{ $product->capabilities_count > 0 ? 'bg-success' : 'bg-warning text-dark' }}">{{ $product->capabilities_count }}</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Experience draft</span>
                                    <span class="badge {{ $experienceDraft !== [] ? 'bg-success' : 'bg-warning text-dark' }}">{{ $experienceDraft !== [] ? 'Saved' : 'Missing' }}</span>
                                </div>
                            </div>

                            <div class="mt-4 d-flex gap-2 flex-wrap">
                                <form method="POST" action="{{ route('admin.products.portal-publication.publish', $product) }}">
                                    @csrf
                                    @method('PUT')
                                    <button type="submit" class="btn btn-primary" {{ $readyForPublication ? '' : 'disabled' }}>
                                        Publish To Portal
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('admin.products.portal-publication.hide', $product) }}">
                                    @csrf
                                    @method('PUT')
                                    <button type="submit" class="btn btn-outline-danger">
                                        Hide From Portal
                                    </button>
                                </form>

                                <a href="{{ route('admin.products.experience.edit', $product) }}" class="btn btn-outline-white">
                                    Edit Experience Draft
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-5">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="mb-3">Portal Card Preview</h6>
                            <div class="border rounded p-3">
                                <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                                    <div>
                                        <div class="text-muted small mb-1">{{ data_get($experienceDraft, 'portal.eyebrow', 'Portal Catalog') }}</div>
                                        <h5 class="mb-1">{{ $product->name }}</h5>
                                        <p class="text-muted mb-0">{{ $product->description ?: data_get($experienceDraft, 'portal.description', 'No product description yet.') }}</p>
                                    </div>
                                    <span class="badge {{ $product->is_active ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $product->is_active ? 'ACTIVE' : 'INACTIVE' }}
                                    </span>
                                </div>

                                <div class="mb-3">
                                    <div class="text-muted small mb-1">Product Code</div>
                                    <div class="fw-semibold">{{ strtoupper($product->code) }}</div>
                                </div>

                                <div class="mb-3">
                                    @forelse($previewCapabilities as $capability)
                                        <div class="small text-muted mb-1">- {{ $capability->name }}</div>
                                    @empty
                                        <div class="small text-muted">No portal capabilities yet.</div>
                                    @endforelse
                                </div>

                                <div class="d-flex flex-wrap gap-2">
                                    <span class="btn btn-sm btn-outline-white disabled">
                                        {{ $product->active_plans_count > 0 ? 'Browse Product Plans' : 'Explore Enablement' }}
                                    </span>
                                </div>
                            </div>

                            <div class="mt-4">
                                <h6 class="mb-2">Preview Plans</h6>
                                @forelse($previewPlans as $plan)
                                    <div class="border rounded p-2 mb-2">
                                        <div class="fw-semibold">{{ $plan->name }}</div>
                                        <div class="small text-muted">{{ ucfirst(str_replace('_', ' ', (string) $plan->billing_period)) }} · {{ number_format((float) $plan->price, 2) }} {{ strtoupper((string) $plan->currency) }}</div>
                                    </div>
                                @empty
                                    <div class="small text-muted">No active plans available for preview.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
