<?php $page = 'billing'; ?>
@extends('automotive.layouts.adminLayout.mainlayout')

@section('content')
    @php
        $canChangeCurrentSubscriptionPlan = $canChangeCurrentSubscriptionPlan ?? false;
        $billingFormAction = $canChangeCurrentSubscriptionPlan
            ? route('automotive.admin.billing.change-plan')
            : route('automotive.admin.billing.renew');

        $billingSubmitLabel = $canChangeCurrentSubscriptionPlan
            ? 'Confirm Plan Change'
            : ($billingActions['primary_label'] ?? 'Renew Subscription');

        $previewData = ($planChangePreview['ok'] ?? false) ? ($planChangePreview['preview'] ?? null) : null;
    @endphp

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

                    <form id="billing-plan-preview-form" method="GET" action="{{ route('automotive.admin.billing.status') }}" class="mt-4">
                        @include('automotive.admin.billing.partials.plan-selector', [
                            'availablePlans' => $availablePlans,
                            'selectedPlanId' => $selectedPlanId,
                        ])
                    </form>

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
                                        You are already on this plan. Choose another plan to change it, or use Manage Billing for payment method and cancellation controls.
                                    </div>
                                @elseif(empty($selectedPlanAudit['checks']['is_aligned']))
                                    <div class="alert alert-danger mt-3 mb-0">
                                        {{ $selectedPlanAudit['message'] ?? 'Selected plan pricing does not match Stripe.' }}
                                        Billing action is blocked until this mapping is corrected.
                                    </div>
                                @else
                                    <div class="alert alert-success mt-3 mb-0">
                                        Local plan pricing is aligned with Stripe for this selected plan.
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if($canChangeCurrentSubscriptionPlan)
                        <div class="alert alert-info mt-4">
                            This tenant already has a live Stripe subscription. Select a plan first to refresh the Stripe preview, then confirm the plan change.
                        </div>
                    @endif

                    @if($canChangeCurrentSubscriptionPlan && !empty($planChangePreview) && !($planChangePreview['ok'] ?? false))
                        <div class="alert alert-warning mt-4">
                            {{ $planChangePreview['message'] ?? 'Unable to preview the Stripe proration right now.' }}
                        </div>
                    @endif

                    @if($canChangeCurrentSubscriptionPlan && !empty($previewData))
                        <div class="card mt-4 border-primary">
                            <div class="card-body">
                                <h5 class="mb-3">Stripe Change Preview</h5>
                                <p class="text-muted mb-3">
                                    This preview is generated from Stripe before the actual change is submitted.
                                </p>

                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-2">
                                            <strong>Proration Total:</strong>
                                            {{ number_format((float) ($previewData['proration_total_decimal'] ?? 0), 2) }}
                                            {{ $previewData['currency'] ?? 'USD' }}
                                        </p>
                                        <p class="mb-2">
                                            <strong>Preview Invoice Total:</strong>
                                            {{ number_format((float) ($previewData['total_decimal'] ?? 0), 2) }}
                                            {{ $previewData['currency'] ?? 'USD' }}
                                        </p>
                                        <p class="mb-2">
                                            <strong>Amount Due:</strong>
                                            {{ number_format((float) ($previewData['amount_due_decimal'] ?? 0), 2) }}
                                            {{ $previewData['currency'] ?? 'USD' }}
                                        </p>
                                    </div>

                                    <div class="col-md-6">
                                        <p class="mb-2">
                                            <strong>Proration Date:</strong>
                                            {{ !empty($previewData['proration_date']) ? \Carbon\Carbon::createFromTimestamp($previewData['proration_date'])->format('Y-m-d H:i:s') : '-' }}
                                        </p>
                                        <p class="mb-2">
                                            <strong>Current Plan:</strong> {{ $plan->name ?? '-' }}
                                        </p>
                                        <p class="mb-2">
                                            <strong>Target Plan:</strong> {{ $selectedPlan->name ?? '-' }}
                                        </p>
                                    </div>
                                </div>

                                @if(!empty($previewData['proration_lines']))
                                    <hr>
                                    <h6 class="mb-3">Proration Lines</h6>

                                    @foreach($previewData['proration_lines'] as $line)
                                        <div class="border rounded p-3 mb-2">
                                            <div class="d-flex justify-content-between gap-3">
                                                <div>
                                                    <div class="fw-semibold">{{ $line['description'] ?? 'Stripe proration line' }}</div>
                                                    <div class="small text-muted">
                                                        @if(!empty($line['period_start']) && !empty($line['period_end']))
                                                            {{ \Carbon\Carbon::createFromTimestamp($line['period_start'])->format('Y-m-d H:i') }}
                                                            →
                                                            {{ \Carbon\Carbon::createFromTimestamp($line['period_end'])->format('Y-m-d H:i') }}
                                                        @else
                                                            Stripe preview line
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="fw-semibold">
                                                    {{ number_format((float) ($line['amount_decimal'] ?? 0), 2) }}
                                                    {{ $line['currency'] ?? ($previewData['currency'] ?? 'USD') }}
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="alert alert-light mt-3 mb-0">
                                        Stripe did not return isolated proration line items for this preview. The totals above still reflect the invoice preview returned by Stripe.
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <form method="POST" action="{{ $billingFormAction }}" class="mt-4">
                        @csrf

                        @if(!empty($selectedPlanId))
                            <input type="hidden" name="target_plan_id" value="{{ $selectedPlanId }}">
                        @endif

                        @if($canChangeCurrentSubscriptionPlan && !empty($previewData['proration_date']))
                            <input type="hidden" name="preview_proration_date" value="{{ $previewData['proration_date'] }}">
                        @endif

                        <div class="card mt-4">
                            <div class="card-body d-flex flex-wrap gap-2 justify-content-end">
                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                    @disabled(empty($selectedPlanId) || ($canChangeCurrentSubscriptionPlan && ($isSameCurrentPaidPlan ?? false)))
                                >
                                    {{ $billingSubmitLabel }}
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

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const previewForm = document.getElementById('billing-plan-preview-form');

            if (!previewForm) {
                return;
            }

            const radios = previewForm.querySelectorAll('input[name="target_plan_id"]');

            radios.forEach(function (radio) {
                radio.addEventListener('change', function () {
                    previewForm.submit();
                });
            });
        });
    </script>
@endsection
