<?php $page = 'billing'; ?>
@extends('automotive.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content container-fluid">

            @include('automotive.admin.partials.page-header', [
                'title' => 'Plans & Billing',
                'subtitle' => 'Trial, subscription, billing access state, and renewal actions.',
                'breadcrumbs' => [
                    [
                        'label' => 'Dashboard',
                        'url' => \Illuminate\Support\Facades\Route::has('automotive.admin.dashboard')
                            ? route('automotive.admin.dashboard')
                            : url('/automotive/admin/dashboard'),
                    ],
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

                    @if(!empty($selectedPlan))
                        <div class="card mt-4">
                            <div class="card-body">
                                <h6 class="mb-3">Selected Plan Pricing Verification</h6>

                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-2"><strong>Selected Plan:</strong> {{ $selectedPlan->name ?? '-' }}</p>
                                        <p class="mb-2"><strong>Local Price:</strong> {{ $selectedPlan->display_price ?? '-' }}</p>
                                        <p class="mb-2"><strong>Local Billing Period:</strong> {{ $selectedPlan->billing_period_label ?? '-' }}</p>
                                        <p class="mb-2"><strong>Stripe Price ID:</strong> {{ $selectedPlan->stripe_price_id ?? '-' }}</p>
                                    </div>

                                    <div class="col-md-6">
                                        <p class="mb-2"><strong>Stripe Amount:</strong>
                                            {{ isset($selectedPlanAudit['stripe']['unit_amount_decimal']) && $selectedPlanAudit['stripe']['unit_amount_decimal'] !== null
                                                ? number_format((float) $selectedPlanAudit['stripe']['unit_amount_decimal'], 2)
                                                : '-' }}
                                            {{ $selectedPlanAudit['stripe']['currency'] ?? '' }}
                                        </p>
                                        <p class="mb-2"><strong>Stripe Interval:</strong> {{ $selectedPlanAudit['stripe']['interval'] ?? '-' }}</p>
                                        <p class="mb-2"><strong>Stripe Product:</strong> {{ $selectedPlanAudit['stripe']['product_name'] ?? '-' }}</p>
                                        <p class="mb-2"><strong>Verification:</strong>
                                            @if(!empty($selectedPlanAudit['checks']['is_aligned']))
                                                <span class="badge bg-success">Aligned</span>
                                            @else
                                                <span class="badge bg-danger">Mismatch</span>
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                @if($isSameCurrentPaidPlan ?? false)
                                    <div class="alert alert-info mt-3 mb-0">
                                        You are already on this active plan. Use Manage Billing or choose another plan to upgrade or downgrade.
                                    </div>
                                @elseif(empty($selectedPlanAudit['checks']['is_aligned']))
                                    <div class="alert alert-danger mt-3 mb-0">
                                        {{ $selectedPlanAudit['message'] ?? 'Selected plan pricing does not match Stripe.' }}
                                        Renew / checkout is blocked until this mapping is corrected.
                                    </div>
                                @else
                                    <div class="alert alert-success mt-3 mb-0">
                                        Local plan pricing is aligned with Stripe for this selected plan.
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('automotive.admin.billing.renew') }}" class="mt-4">
                        @csrf

                        @include('automotive.admin.billing.partials.plan-selector', [
                            'availablePlans' => $availablePlans,
                            'selectedPlanId' => $selectedPlanId,
                        ])

                        <div class="card mt-4">
                            <div class="card-body d-flex flex-wrap gap-2 justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    {{ $billingActions['primary_label'] ?? 'Renew Subscription' }}
                                </button>
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
                            <p class="mb-2"><strong>Gateway Customer ID:</strong> {{ $subscription->gateway_customer_id ?? '-' }}</p>
                            <p class="mb-2"><strong>Gateway Subscription ID:</strong> {{ $subscription->gateway_subscription_id ?? '-' }}</p>
                            <p class="mb-4"><strong>Gateway Price ID:</strong> {{ $subscription->gateway_price_id ?? '-' }}</p>

                            @if(!empty($subscription->gateway_customer_id))
                                <div class="d-grid gap-2">
                                    <form method="POST" action="{{ route('automotive.admin.billing.portal') }}">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-primary w-100">
                                            Manage Billing
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('automotive.admin.billing.portal') }}">
                                        @csrf
                                        <button type="submit" class="btn btn-light w-100">
                                            Update Payment Method
                                        </button>
                                    </form>

                                    @if(($billingState['status'] ?? '') === 'active')
                                        <form method="POST" action="{{ route('automotive.admin.billing.cancel-subscription') }}">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-danger w-100">
                                                Cancel at Period End
                                            </button>
                                        </form>
                                    @endif

                                    @if(($billingState['status'] ?? '') === 'canceled')
                                        <form method="POST" action="{{ route('automotive.admin.billing.resume-subscription') }}">
                                            @csrf
                                            <button type="submit" class="btn btn-success w-100">
                                                Resume Subscription
                                            </button>
                                        </form>
                                    @endif

                                    @if(in_array($billingState['status'] ?? '', ['past_due', 'grace_period', 'suspended', 'expired'], true))
                                        <form method="POST" action="{{ route('automotive.admin.billing.portal') }}">
                                            @csrf
                                            <button type="submit" class="btn btn-warning w-100">
                                                Retry / Reactivate
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @else
                                <div class="alert alert-info mb-0">
                                    Billing portal will become available after the first Stripe subscription is linked to this tenant.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
