<?php $page = 'billing'; ?>
@extends('automotive.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content container-fluid">

            @include('automotive.admin.partials.page-header', [
                'title' => 'Plans & Billing',
                'subtitle' => 'Trial, subscription, access state, and billing lifecycle overview.',
                'breadcrumbs' => [
                    ['label' => 'Dashboard', 'url' => route('automotive.admin.dashboard')],
                    ['label' => 'Plans & Billing'],
                ],
            ])

            @include('automotive.admin.partials.alerts')

            <div class="row">
                <div class="col-lg-8">
                    @include('automotive.admin.billing.partials.status-card', [
                        'billingState' => $billingState,
                        'plan' => $plan,
                    ])
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="mb-3">Next Billing Actions</h6>

                            <div class="d-grid gap-2">
                                <a href="javascript:void(0);" class="btn btn-primary">
                                    Renew Subscription
                                </a>
                                <a href="javascript:void(0);" class="btn btn-outline-primary">
                                    Update Payment Method
                                </a>
                                <a href="javascript:void(0);" class="btn btn-light">
                                    View Plan Details
                                </a>
                            </div>

                            <hr>

                            <p class="mb-2"><strong>Tenant:</strong> {{ $tenant->id ?? '-' }}</p>
                            <p class="mb-2"><strong>Plan:</strong> {{ $plan->name ?? 'N/A' }}</p>
                            <p class="mb-0"><strong>Subscription Status:</strong> {{ ucfirst(str_replace('_', ' ', $billingState['status'] ?? 'unknown')) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
