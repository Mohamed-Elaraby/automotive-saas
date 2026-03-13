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
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="mb-3">Available Actions</h6>

                            <div class="d-grid gap-2">
                                <form method="POST" action="{{ route('automotive.admin.billing.renew') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary w-100">
                                        {{ $billingActions['primary_label'] ?? 'Renew Subscription' }}
                                    </button>
                                </form>
                            </div>

                            <hr>

                            <p class="mb-2"><strong>Tenant:</strong> {{ $tenant->id ?? '-' }}</p>
                            <p class="mb-2"><strong>Plan:</strong> {{ $plan->name ?? 'N/A' }}</p>
                            <p class="mb-2"><strong>Subscription ID:</strong> {{ $subscription->id ?? '-' }}</p>
                            <p class="mb-0"><strong>Status:</strong> {{ ucfirst(str_replace('_', ' ', $billingState['status'] ?? 'unknown')) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
