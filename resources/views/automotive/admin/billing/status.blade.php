<?php $page = 'billing'; ?>
@extends('automotive.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content container-fluid">

            @include('automotive.admin.partials.page-header', [
                'title' => 'Plans & Billing',
                'subtitle' => 'Trial, subscription, billing access state, and renewal actions.',
                'breadcrumbs' => [
                    ['label' => 'Dashboard', 'url' => route('automotive.admin.dashboard')],
                    ['label' => 'Plans & Billing'],
                ],
                'actions' => null,
            ])

            @include('automotive.admin.partials.alerts')

            <div class="row">
                <div class="col-lg-8">
                    @include('automotive.admin.billing.partials.status-card', [
                        'billingState' => $billingState,
                        'plan' => $plan,
                    ])

                    <form method="POST" action="{{ route('automotive.admin.billing.renew') }}" class="mt-4">
                        @csrf

                        @include('automotive.admin.billing.partials.plan-selector', [
                            'availablePlans' => $availablePlans,
                            'selectedPlanId' => $selectedPlanId,
                        ])

                        <div class="card mt-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary">
                                        {{ $billingActions['primary_label'] ?? 'Renew Subscription' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="mb-3">Billing Summary</h6>

                            <p class="mb-2"><strong>Tenant:</strong> {{ $tenant->id ?? '-' }}</p>
                            <p class="mb-2"><strong>Current Plan:</strong> {{ $plan->name ?? 'N/A' }}</p>
                            <p class="mb-2"><strong>Current Status:</strong> {{ ucfirst(str_replace('_', ' ', $billingState['status'] ?? 'unknown')) }}</p>
                            <p class="mb-2"><strong>Subscription ID:</strong> {{ $subscription->id ?? '-' }}</p>
                            <p class="mb-0"><strong>Selected Plan ID:</strong> {{ $selectedPlanId ?? '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
