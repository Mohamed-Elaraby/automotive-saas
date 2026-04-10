<?php $page = 'membership-plans'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two pb-0">

            <div class="d-flex d-block align-items-center justify-content-between flex-wrap gap-3 mb-3">
                <div>
                    <h6>{{ $plan->name }}</h6>
                    <p class="mb-0">Plan usage, linked subscriptions, and billing catalog context.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.plans.index') }}" class="btn btn-outline-white">Back to Plans</a>
                    <a href="{{ route('admin.plans.edit', $plan) }}" class="btn btn-primary">Edit Plan</a>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted mb-1">Billing</div>
                            <h6 class="mb-0">{{ ucfirst(str_replace('_', ' ', (string) $plan->billing_period)) }}</h6>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted mb-1">Price</div>
                            <h6 class="mb-0">{{ number_format((float) $plan->price, 2) }} {{ strtoupper((string) $plan->currency) }}</h6>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted mb-1">Plan Status</div>
                            @if ($plan->is_active)
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
                            <div class="text-muted mb-1">Linked Subscriptions</div>
                            <h6 class="mb-0">{{ $subscriptions->count() }}</h6>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Plan Details</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-striped align-middle mb-0">
                        <tbody>
                        <tr>
                            <th style="width: 240px;">Product</th>
                            <td>
                                @if($plan->product)
                                    {{ $plan->product->name }} ({{ $plan->product->code }})
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th style="width: 240px;">Slug</th>
                            <td>{{ $plan->slug }}</td>
                        </tr>
                        <tr>
                            <th>Description</th>
                            <td>{{ $plan->description ?: 'No description provided.' }}</td>
                        </tr>
                        <tr>
                            <th>Stripe Price ID</th>
                            <td>{{ $plan->stripe_price_id ?: '-' }}</td>
                        </tr>
                        <tr>
                            <th>Features</th>
                            <td>
                                @forelse($plan->billingFeatures as $feature)
                                    <span class="badge badge-soft-info mb-1">{{ $feature->name }}</span>
                                @empty
                                    <span class="text-muted">No catalog features linked.</span>
                                @endforelse
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Subscription Status Breakdown</h6>
                </div>
                <div class="card-body">
                    @if ($usageByStatus->isEmpty())
                        <p class="text-muted mb-0">No subscriptions are currently linked to this plan.</p>
                    @else
                        <div class="row g-3">
                            @foreach ($usageByStatus as $usage)
                                <div class="col-xl-3 col-md-4 col-sm-6">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted mb-1">{{ ucfirst(str_replace('_', ' ', (string) $usage['status'])) }}</div>
                                        <h6 class="mb-0">{{ $usage['count'] }}</h6>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="mb-0">Linked Subscriptions</h6>
                        <small class="text-muted">Latest subscriptions that currently reference this plan.</small>
                    </div>
                    <a href="{{ route('admin.subscriptions.index') }}" class="btn btn-sm btn-outline-white">Open Subscriptions</a>
                </div>
                <div class="card-body">
                    @if ($subscriptions->isEmpty())
                        <div class="text-center py-4">
                            <p class="mb-0">No subscriptions are using this plan yet.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tenant</th>
                                    <th>Status</th>
                                    <th>Gateway</th>
                                    <th>Created</th>
                                    <th class="text-end">Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($subscriptions as $subscription)
                                    <tr>
                                        <td>{{ $subscription->id }}</td>
                                        <td>{{ $subscription->tenant_id }}</td>
                                        <td>{{ ucfirst(str_replace('_', ' ', (string) $subscription->status)) }}</td>
                                        <td>{{ strtoupper((string) ($subscription->gateway ?: 'local')) }}</td>
                                        <td>{{ $subscription->created_at ?: '-' }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('admin.subscriptions.show', $subscription->id) }}" class="btn btn-sm btn-outline-primary">Open Subscription</a>
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
