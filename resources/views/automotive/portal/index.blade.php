<?php $page = 'profile'; ?>
@extends('automotive.portal.layouts.portalLayout.mainlayout')

@section('content')
    @php
        $hasExplicitProductSelection = $hasExplicitProductSelection ?? (!empty($selectedProductWasExplicit) && !empty($selectedProduct));
    @endphp
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="mb-3 border-bottom pb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <h6 class="mb-1">{{ __('shared.profile') }}</h6>
                            <p class="text-muted mb-0">{{ __('shared.portal_intro') }}</p>
                        </div>

                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            @if($allowSystemAccess && !empty($systemUrl))
                                <a href="{{ $systemUrl }}" target="_blank" class="btn btn-primary">
                                    {{ __('shared.open_my_workspace') }}
                                </a>
                            @else
                                <form method="POST" action="{{ route('automotive.logout') }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-white">
                                        {{ __('shared.sign_out') }}
                                    </button>
                                </form>

                                @if(!empty($selectedPortalBillingUrl) && $hasExplicitProductSelection)
                                    <a href="{{ $selectedPortalBillingUrl }}" class="btn btn-outline-white">
                                        {{ __('shared.manage_workspace_billing') }}
                                    </a>
                                @else
                                    <a href="#products-catalog" class="btn btn-outline-white">
                                        {{ __('shared.choose_product') }}
                                    </a>
                                @endif

                                <a href="#products-catalog" class="btn btn-outline-white">
                                    {{ __('shared.view_products') }}
                                </a>

                                <a href="{{ route('automotive.portal.settings') }}" class="btn btn-outline-white">
                                    {{ __('shared.account_settings') }}
                                </a>
                            @endif
                        </div>
                    </div>

                    @if(session('success'))
                        <div class="alert alert-success mb-3">
                            {{ session('success') }}
                            @if(session('checkout_completed'))
                                <div class="mt-3 d-flex align-items-center gap-2 flex-wrap">
                                    @if($allowSystemAccess && !empty($systemUrl))
                                        <span class="small text-muted">
                                            {{ __('portal.use_open_workspace_button') }}
                                        </span>
                                    @else
                                        <a href="{{ route('automotive.portal', array_filter(['product' => session('checkout_completed_product')])) }}" class="btn btn-sm btn-outline-success">
                                            {{ __('portal.refresh_portal_status') }}
                                        </a>
                                        <span class="small text-muted">
                                            {{ __('portal.checkout_finished_pending_sync') }}
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger mb-3">
                            <ul class="mb-0 ps-3">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($hasPendingPaidCheckout)
                        <div class="alert alert-warning mb-3">
                            {{ __('portal.last_checkout_not_completed') }}
                        </div>
                    @elseif($status === 'trialing')
                        <div class="alert alert-info mb-3">
                            {{ __('portal.account_on_free_trial') }}
                            @if(!is_null($trialDaysRemaining))
                                {!! __('portal.trial_days_remaining', ['days' => '<strong>' . max((int) $trialDaysRemaining, 0) . '</strong>']) !!}
                            @endif
                        </div>
                    @elseif($status === 'past_due' || $status === 'suspended' || $status === 'expired')
                        <div class="alert alert-warning mb-3">
                            @if($status === 'expired' && $canStartPaidCheckout)
                                {!! __('portal.previous_subscription_expired') !!}
                            @else
                                {!! __('portal.current_subscription_status_message', ['status' => '<strong>' . e(strtoupper(str_replace('_', ' ', $status))) . '</strong>']) !!}
                            @endif
                        </div>
                    @elseif(!$hasAnyWorkspace)
                        <div class="alert alert-primary mb-3">
                            @if($freeTrialEnabled)
                                {{ __('portal.account_ready_trial_or_paid') }}
                            @else
                                {{ __('portal.account_ready_paid') }}
                            @endif
                        </div>
                    @endif

                    <div class="card mb-0">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <span class="bg-dark avatar avatar-sm me-2 flex-shrink-0">
                                    <i class="isax isax-info-circle fs-14"></i>
                                </span>
                                <h6 class="fs-16 fw-semibold mb-0">{{ __('shared.general_information') }}</h6>
                            </div>

                            <div class="mb-3">
                                <span class="text-gray-9 fw-bold mb-2 d-flex">{{ __('shared.profile_summary') }}</span>
                                <div class="d-flex align-items-center">
                                    <div class="avatar avatar-xxl border border-dashed bg-light me-3 flex-shrink-0">
                                        <div class="position-relative d-flex align-items-center justify-content-center h-100">
                                            <span class="avatar avatar-xl bg-primary text-white fs-24">
                                                {{ strtoupper(substr((string) ($user->name ?? 'U'), 0, 1)) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="d-inline-flex flex-column align-items-start">
                                        <h6 class="mb-1">{{ $user->name ?? '-' }}</h6>
                                        <span class="text-gray-9 fs-13 mb-1">{{ $user->email ?? '-' }}</span>
                                        <span class="badge
                                            @if($status === 'active') bg-success
                                            @elseif($status === 'trialing') bg-info
                                            @elseif($status === 'past_due') bg-warning
                                            @elseif(in_array($status, ['suspended', 'expired', 'cancelled'])) bg-danger
                                            @else bg-secondary
                                            @endif
                                            ">
                                            {{ $status ? strtoupper(str_replace('_', ' ', $status)) : 'NOT STARTED' }}
                                        </span>
                                        @if(!empty($visibleCouponCode))
                                            <span class="badge bg-soft-success text-success mt-2">
                                                {{ __('portal.reserved_coupon') }}: {{ $visibleCouponCode }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="border-bottom mb-3 pb-2">
                                <div class="row gx-3">
                                    <div class="col-lg-4 col-md-6">
                                        <div class="mb-3">
                                                    <label class="form-label">{{ __('portal.full_name') }}</label>
                                            <input type="text" class="form-control" value="{{ $user->name ?? '-' }}" readonly>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-md-6">
                                        <div class="mb-3">
                                                    <label class="form-label">{{ __('portal.email') }}</label>
                                            <input type="text" class="form-control" value="{{ $user->email ?? '-' }}" readonly>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-md-6">
                                        <div class="mb-3">
                                                    <label class="form-label">{{ __('portal.company_name') }}</label>
                                            <input type="text" class="form-control" value="{{ $profile->company_name ?? '-' }}" readonly>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-md-6">
                                        <div class="mb-3">
                                                    <label class="form-label">{{ __('portal.reserved_subdomain') }}</label>
                                            <input type="text" class="form-control" value="{{ $profile->subdomain ?? '-' }}" readonly>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-md-6">
                                        <div class="mb-3">
                                                    <label class="form-label">{{ __('portal.primary_domain') }}</label>
                                            <input type="text" class="form-control" value="{{ $primaryDomainValue ?: '-' }}" readonly>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-md-6">
                                        <div class="mb-3">
                                                    <label class="form-label">{{ __('portal.system_access') }}</label>
                                                    <input type="text" class="form-control" value="{{ $allowSystemAccess ? __('portal.available') : __('portal.not_available_yet') }}" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="border-bottom mb-3 pb-2">
                                <div class="d-flex align-items-center mb-3">
                                    <span class="bg-dark avatar avatar-sm me-2 flex-shrink-0">
                                        <i class="isax isax-info-circle fs-14"></i>
                                    </span>
                                    <h6 class="fs-16 fw-semibold mb-0">{{ __('portal.subscription_information') }}</h6>
                                </div>

                                <div class="row gx-3">
                                    <div class="col-lg-4 col-md-6">
                                        <div class="mb-3">
                                                    <label class="form-label">{{ __('shared.current_plan') }}</label>
                                                    <input type="text" class="form-control" value="{{ $plan->name ?? $plan->slug ?? __('portal.no_plan_yet') }}" readonly>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-md-6">
                                        <div class="mb-3">
                                                    <label class="form-label">{{ __('portal.current_status') }}</label>
                                            <input type="text" class="form-control" value="{{ $status ? strtoupper(str_replace('_', ' ', $status)) : 'NOT STARTED' }}" readonly>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-md-6">
                                        <div class="mb-3">
                                                    <label class="form-label">{{ __('portal.billing_period') }}</label>
                                            <input type="text" class="form-control" value="{{ !empty($subscription->billing_period) ? strtoupper((string) $subscription->billing_period) : '-' }}" readonly>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-md-6">
                                        <div class="mb-3">
                                                    <label class="form-label">{{ __('portal.trial_ends_at') }}</label>
                                            <input type="text" class="form-control" value="{{ $trialEndsAt ? $trialEndsAt->format('Y-m-d H:i:s') : '-' }}" readonly>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-md-6">
                                        <div class="mb-3">
                                                    <label class="form-label">{{ __('portal.days_remaining') }}</label>
                                            <input type="text" class="form-control" value="{{ !is_null($trialDaysRemaining) ? max((int) $trialDaysRemaining, 0) : '-' }}" readonly>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-md-6">
                                        <div class="mb-3">
                                                    <label class="form-label">{{ __('portal.gateway') }}</label>
                                            <input type="text" class="form-control" value="{{ $subscription->gateway ?? '-' }}" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="border-bottom mb-3">
                                <div class="d-flex align-items-center mb-3">
                                    <span class="bg-dark avatar avatar-sm me-2 flex-shrink-0">
                                        <i class="isax isax-info-circle fs-14"></i>
                                    </span>
                                    <h6 class="fs-16 fw-semibold mb-0">{{ __('portal.domain_information') }}</h6>
                                </div>

                                @if($domains->count() > 0)
                                    <div class="row gx-3">
                                        @foreach($domains as $domain)
                                            <div class="col-lg-6">
                                                <div class="border rounded p-3 mb-3">
                                                    <div class="fw-semibold mb-2">{{ $domain['domain'] }}</div>
                                                    @if($allowSystemAccess && !empty($systemUrl))
                                                        <p class="text-muted fs-13 mb-0">
                                                            {{ __('portal.use_open_workspace_button') }}
                                                        </p>
                                                    @else
                                                        <div class="d-flex flex-wrap gap-2">
                                                            <a href="{{ $domain['url'] }}" target="_blank" class="btn btn-sm btn-outline-white">
                                                                {{ __('portal.open_domain') }}
                                                            </a>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="alert alert-light border">
                                        {{ __('portal.domain_after_activation') }}
                                    </div>
                                @endif
                            </div>

                            @unless($allowSystemAccess && !empty($systemUrl))
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div class="d-flex flex-wrap gap-2">
                                        <a href="{{ $hasExplicitProductSelection ? '#paid-plans' : '#products-catalog' }}" class="btn btn-outline-white">
                                            {{ $hasExplicitProductSelection ? __('portal.view_paid_plans') : __('portal.choose_product_first') }}
                                        </a>

                                        @if(!empty($selectedPortalBillingUrl) && $hasExplicitProductSelection)
                                            <a href="{{ $selectedPortalBillingUrl }}" class="btn btn-outline-white">
                                                {{ __('portal.open_billing_control') }}
                                            </a>
                                        @endif
                                    </div>

                                    <button type="button" class="btn btn-outline-white" disabled>
                                        {{ __('portal.profile_overview_only') }}
                                    </button>
                                </div>
                            @endunless
                        </div>
                    </div>

                    <div class="card mt-4" id="products-catalog">
                        <div class="card-body">
                            <div class="mb-4">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                    <div>
                                        <h6 class="mb-1">{{ __('portal.products_catalog') }}</h6>
                                        <p class="text-muted mb-0">{{ __('portal.products_catalog_intro') }}</p>
                                    </div>
                                    <span class="badge bg-soft-info text-info">
                                        {{ __('portal.one_workspace_many_products') }}
                                    </span>
                                </div>
                            </div>

                            <div class="row">
                                @foreach($productCatalog as $productRow)
                                    @php
                                        $statusBadgeClass = match ((string) ($productRow['subscription_status'] ?? '')) {
                                            'active' => 'bg-success',
                                            'trialing' => 'bg-info',
                                            'past_due', 'suspended', 'expired', 'canceled' => 'bg-warning',
                                            default => ((bool) ($productRow['is_active'] ?? false) ? 'bg-dark' : 'bg-secondary'),
                                        };
                                    @endphp
                                    <div class="col-lg-4 col-md-6 d-flex">
                                        <div class="card border flex-fill w-100">
                                            <div class="card-body d-flex flex-column">
                                                <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                                                    <div>
                                                        <h5 class="mb-1">{{ $productRow['name'] }}</h5>
                                                        <p class="text-muted mb-0">{{ $productRow['description'] ?: __('portal.product_catalog_item') }}</p>
                                                    </div>
                                                    <span class="badge {{ $statusBadgeClass }}">
                                                        {{ $productRow['status_label'] }}
                                                    </span>
                                                </div>

                                                <div class="mb-3">
                                                    <div class="text-muted fs-13 mb-1">{{ __('portal.product_code') }}</div>
                                                    <div class="fw-semibold">{{ strtoupper((string) $productRow['code']) }}</div>
                                                </div>

                                                <div class="mb-3">
                                                    @if($productRow['is_subscribed'])
                                                        <p class="mb-1 text-success">{{ __('portal.product_attached') }}</p>
                                                        @if(!empty($productRow['activation_portal_status']['message']))
                                                            <p class="mb-0 text-muted fs-13">{{ $productRow['activation_portal_status']['message'] }}</p>
                                                        @endif
                                                    @elseif($productRow['is_automotive'])
                                                        <p class="mb-1 text-muted">{{ __('portal.product_active_catalog') }}</p>
                                                    @else
                                                        <p class="mb-1 text-muted">{{ __('portal.product_connects_workspace') }}</p>
                                                    @endif
                                                </div>

                                                <div class="mt-auto d-flex flex-wrap gap-2">
                                                    @if($productRow['is_subscribed'] && $allowSystemAccess && !empty($systemUrl))
                                                        <span class="badge bg-soft-success text-success align-self-start">
                                                            {{ __('portal.workspace_ready') }}
                                                        </span>
                                                    @else
                                                        <a href="{{ $productRow['action_url'] }}" class="btn btn-outline-white">
                                                            {{ $productRow['action_label'] }}
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    @if($hasExplicitProductSelection)
                        <div class="card mt-4" id="paid-plans">
                            <div class="card-body">
                            @php
                                $plansByPeriod = collect($paidPlans ?? [])->groupBy(fn ($plan) => (string) ($plan->billing_period ?? 'monthly'));
                                $periodTabs = collect([
                                    'monthly' => __('portal.monthly'),
                                    'yearly' => __('portal.yearly'),
                                    'one_time' => __('portal.one_time'),
                                ])->filter(fn ($label, $period) => $plansByPeriod->has($period));
                                $activePeriod = (string) ($periodTabs->keys()->first() ?? 'monthly');
                                $selectedProductName = (string) ($selectedProduct['name'] ?? __('portal.selected_product'));
                                $selectedProductDescription = (string) ($selectedProduct['description'] ?? '');
                            @endphp

                            <div class="mb-4">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                    <div>
                                        <h6 class="mb-1">{{ __('portal.product_subscription_options') }}</h6>
                                        <p class="text-muted mb-0">
                                            @if(empty($selectedProduct))
                                                {{ __('portal.choose_product_options_load') }}
                                            @elseif($selectedProductSupportsCheckout)
                                                @if(!$hasAnyWorkspace && empty($selectedProductWasExplicit))
                                                    {{ __('portal.choose_product_review_options') }}
                                                @else
                                                    {!! __('portal.review_trial_paid_for', ['product' => '<strong>' . e($selectedProductName) . '</strong>']) !!}
                                                @endif
                                            @else
                                                {!! __('portal.review_enablement_for', ['product' => '<strong>' . e($selectedProductName) . '</strong>']) !!}
                                            @endif
                                        </p>
                                    </div>

                                    @if(!empty($visibleCouponCode))
                                        <span class="badge bg-soft-success text-success">
                                            {{ __('portal.reserved_coupon') }}: {{ $visibleCouponCode }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            @if(($selectedProductCapabilities ?? collect())->isNotEmpty())
                                <div class="alert alert-light border mb-4">
                                    <div class="fw-semibold mb-2">{{ __('portal.included_product_capabilities') }}</div>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach($selectedProductCapabilities as $capabilityName)
                                            <span class="badge bg-white text-dark border">{{ $capabilityName }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if(!empty($selectedProductSubscription) && !empty($selectedProductProvisioningStatus))
                                <div class="alert alert-{{ $selectedProductProvisioningStatus['severity'] ?? 'secondary' }} mb-4">
                                    <div class="fw-semibold mb-1">{{ $selectedProductProvisioningStatus['label'] ?? __('portal.provisioning_status') }}</div>
                                    <div>{{ $selectedProductProvisioningStatus['message'] ?? __('portal.provisioning_resolving') }}</div>
                                    @if(($selectedProductProvisioningStatus['state'] ?? '') === 'provisioning_failed' && !empty($selectedProductProvisioningStatus['error']))
                                        <div class="small mt-2 text-muted">{{ __('portal.diagnostic') }}: {{ $selectedProductProvisioningStatus['error'] }}</div>
                                    @endif
                                </div>
                            @endif

                            @if(!empty($selectedProduct) && !$hasAnyWorkspace && $freeTrialEnabled && $selectedProductHasTrialPlan && !empty($selectedProductWasExplicit))
                                <div class="alert alert-primary border mb-4">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                        <div>
                                            <div class="fw-semibold mb-1">{{ $selectedProductName }} {{ __('portal.free_trial') }}</div>
                                            <div class="text-muted">
                                                {!! __('portal.free_trial_intro', ['days' => '<strong>' . (int) ($selectedProductTrialDays ?? 14) . '</strong>']) !!}
                                            </div>
                                        </div>
                                        <form method="POST" action="{{ route('automotive.portal.start-trial') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="product_id" value="{{ $selectedProduct['id'] }}">
                                            <button type="submit" class="btn btn-primary">
                                                {{ __('portal.start_product_free_trial', ['product' => $selectedProductName]) }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endif

                            @if(empty($selectedProduct))
                            @elseif(!$selectedProductSupportsCheckout)
                                @php
                                    $selectedProductEnablementStatus = (string) ($selectedProductEnablementRequest->status ?? '');
                                @endphp
                                <div class="alert alert-info">
                                    {{ __('portal.product_visible_catalog', ['product' => $selectedProductName]) }}
                                    @if(!$hasAnyWorkspace)
                                        {{ __('portal.no_direct_checkout_yet') }}
                                    @else
                                        {{ __('portal.product_visible_catalog', ['product' => $selectedProductName]) }}
                                        {{ __('portal.submit_enablement_first') }}
                                    @endif
                                </div>

                                @if($selectedProductEnablementStatus === 'rejected')
                                    <div class="alert alert-warning">
                                        {{ __('portal.enablement_rejected', ['product' => $selectedProductName]) }}
                                    </div>
                                @elseif($selectedProductEnablementStatus === 'approved')
                                    <div class="alert alert-success">
                                        {{ __('portal.enablement_approved_message', ['product' => $selectedProductName]) }}
                                    </div>
                                @endif

                                <div class="mb-4 d-flex flex-wrap gap-2">
                                    @if(empty($selectedProduct['is_active']))
                                        <button type="button" class="btn btn-outline-white" disabled>
                                            {{ __('portal.product_coming_soon') }}
                                        </button>
                                    @elseif(!empty($selectedProduct['is_subscribed']))
                                        <button type="button" class="btn btn-success" disabled>
                                            {{ __('portal.product_already_attached') }}
                                        </button>
                                    @elseif($selectedProductEnablementStatus === 'pending')
                                        <button type="button" class="btn btn-outline-white" disabled>
                                            {{ __('portal.enablement_request_pending') }}
                                        </button>
                                    @elseif($selectedProductEnablementStatus === 'approved')
                                        <button type="button" class="btn btn-success" disabled>
                                            {{ __('portal.enablement_approved') }}
                                        </button>
                                    @else
                                        <form method="POST" action="{{ route('automotive.portal.products.request-enable') }}">
                                            @csrf
                                            <input type="hidden" name="product_id" value="{{ $selectedProduct['id'] }}">
                                            <button type="submit" class="btn btn-primary">
                                                {{ $selectedProductEnablementStatus === 'rejected' ? __('portal.request_product_enablement_again') : __('portal.request_product_enablement') }}
                                            </button>
                                        </form>
                                    @endif
                                </div>

                                @if($paidPlans->count() === 0)
                                    <div class="alert alert-light border mb-0">
                                        {{ __('portal.no_active_paid_plans_for_product', ['product' => $selectedProductName]) }}
                                    </div>
                                @else
                                    <div class="row">
                                        @foreach($paidPlans as $paidPlan)
                                            @php
                                                $featureList = collect($paidPlan->features_array ?? [])->take(6);
                                                $limitLines = collect($paidPlan->limits_array ?? [])->map(function ($limit) {
                                                    return $limit['label'] . ' ' . $limit['value'];
                                                });
                                            @endphp
                                            <div class="col-lg-4 col-md-6 col-sm-12 d-flex">
                                                <div class="card pricing-starter flex-fill w-100">
                                                    <div class="card-body d-flex flex-column">
                                                        <div class="border-bottom">
                                                            <div class="mb-3">
                                                                <h5 class="mb-1">{{ $paidPlan->name }}</h5>
                                                                <p class="mb-0">{{ $paidPlan->description ?: ($selectedProductDescription ?: __('portal.configured_product_plan')) }}</p>
                                                            </div>
                                                            <div class="mb-3">
                                                                <h3 class="d-flex align-items-center mb-1">
                                                                    {{ str_replace(' ' . $paidPlan->currency_code, '', $paidPlan->display_price) }}
                                                                </h3>
                                                                <p class="mb-0">
                                                                    {{ strtoupper((string) ($paidPlan->slug ?? 'PLAN')) }} · {{ $paidPlan->currency_code }} {{ __('portal.billing') }}
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <div class="mt-3 mb-3">
                                                            @foreach($limitLines as $limitLine)
                                                                <p class="text-dark d-flex align-items-center mb-2 text-truncate">
                                                                    <i class="isax isax-tick-circle me-2"></i>{{ $limitLine }}
                                                                </p>
                                                            @endforeach
                                                            @foreach($featureList as $feature)
                                                                <p class="text-dark d-flex align-items-center mb-2 text-truncate">
                                                                    <i class="isax isax-tick-circle me-2"></i>{{ $feature }}
                                                                </p>
                                                            @endforeach
                                                        </div>
                                                        <div class="mt-auto">
                                                            <button type="button" class="d-flex align-items-center justify-content-center btn border w-100" disabled>
                                                                <i class="isax isax-lock me-1"></i> {{ __('portal.approval_required_before_checkout') }}
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            @elseif($selectedProductHasLiveBilling)
                                <div class="alert alert-info mb-0">
                                    {{ __('portal.already_live_subscription') }}
                                </div>
                            @elseif($paidPlans->count() === 0)
                                <div class="alert alert-light border mb-0">
                                    {{ __('portal.no_active_paid_plans') }}
                                </div>
                            @else
                                @if(!($selectedProduct['is_automotive'] ?? false) && $selectedProductHasPendingCheckout)
                                    <div class="alert alert-warning mb-3">
                                        {{ __('portal.pending_checkout_for_product', ['product' => $selectedProductName]) }}
                                    </div>
                                @elseif(!($selectedProduct['is_automotive'] ?? false) && $selectedProductHasLiveBilling)
                                    <div class="alert alert-info mb-3">
                                        {{ __('portal.live_billed_subscription_for_product', ['product' => $selectedProductName]) }}
                                    </div>
                                @elseif(!($selectedProduct['is_automotive'] ?? false) && in_array($selectedProductStatus ?? '', ['past_due', 'suspended'], true))
                                    <div class="alert alert-warning mb-3">
                                        {!! __('portal.product_currently_status', ['product' => e($selectedProductName), 'status' => '<strong>' . e(strtoupper(str_replace('_', ' ', (string) $selectedProductStatus))) . '</strong>']) !!}
                                    </div>
                                @elseif(!($selectedProduct['is_automotive'] ?? false) && in_array($selectedProductStatus ?? '', ['expired', 'canceled', 'cancelled'], true))
                                    <div class="alert alert-warning mb-3">
                                        {!! __('portal.previous_subscription_status', ['product' => e($selectedProductName), 'status' => '<strong>' . e(strtoupper(str_replace('_', ' ', (string) $selectedProductStatus))) . '</strong>']) !!}
                                    </div>
                                @endif

                                @if($periodTabs->count() > 1)
                                    <div class="d-flex align-items-center justify-content-center mb-4">
                                        <ul class="nav nav-tabs nav-solid-success nav-tabs-rounded p-1 rounded-pill bg-light" role="tablist">
                                            @foreach($periodTabs as $period => $label)
                                                <li class="nav-item">
                                                    <a class="nav-link @if($activePeriod === $period) active @endif" href="#portal-plan-tab-{{ $period }}" data-bs-toggle="tab">
                                                        {{ $label }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <div class="tab-content">
                                    @foreach($periodTabs as $period => $label)
                                        <div class="tab-pane @if($activePeriod === $period) show active @endif" id="portal-plan-tab-{{ $period }}">
                                            <div class="row">
                                                @foreach(($plansByPeriod[$period] ?? collect()) as $paidPlan)
                                                    @php
                                                        $isCurrentPaidPlan = !empty($plan)
                                                            && (int) ($plan->id ?? 0) === (int) $paidPlan->id
                                                            && !in_array((string) $status, ['trialing', ''], true);

                                                        $priceSuffix = match ((string) $paidPlan->billing_period) {
                                                            'yearly' => __('portal.year_suffix'),
                                                            'one_time' => __('portal.one_time_suffix'),
                                                            default => __('portal.month_suffix'),
                                                        };
                                                        $featureList = collect($paidPlan->features_array ?? [])->take(6);
                                                        $limitLines = collect($paidPlan->limits_array ?? [])->map(function ($limit) {
                                                            return $limit['label'] . ' ' . $limit['value'];
                                                        });
                                                    @endphp
                                                    <div class="col-lg-4 col-md-6 col-sm-12 d-flex">
                                                        <div class="card pricing-starter flex-fill w-100">
                                                            <div class="card-body d-flex flex-column">
                                                                <div class="border-bottom">
                                                                    <div class="mb-3">
                                                                        <div class="d-flex align-items-center justify-content-between position-relative gap-2">
                                                                            <div>
                                                                                <h5 class="mb-1">{{ $paidPlan->name }}</h5>
                                                                                <p class="mb-0">{{ $paidPlan->description ?: __('portal.configured_paid_plan') }}</p>
                                                                            </div>
                                                                            @if($isCurrentPaidPlan && $status === 'active')
                                                                                <span class="badge bg-success position-absolute top-0 end-0">{{ __('portal.current') }}</span>
                                                                            @elseif($loop->first)
                                                                                <span class="badge bg-soft-info text-info position-absolute top-0 end-0">{{ __('portal.popular') }}</span>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <h3 class="d-flex align-items-center mb-1">
                                                                            {{ str_replace(' ' . $paidPlan->currency_code, '', $paidPlan->display_price) }}
                                                                            <span class="fs-14 fw-normal text-gray-9 ms-1">
                                                                                {{ $priceSuffix }}
                                                                            </span>
                                                                        </h3>
                                                                        <p class="mb-0">
                                                                            {{ strtoupper((string) ($paidPlan->slug ?? 'PLAN')) }} · {{ $paidPlan->currency_code }} {{ __('portal.billing') }}
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                                <div class="mt-3 mb-3">
                                                                    <div class="mb-1">
                                                                        <h6 class="fs-16 mb-2">{{ __('portal.what_you_get') }}</h6>
                                                                    </div>
                                                                    <div>
                                                                        @foreach($limitLines as $limitLine)
                                                                            <p class="text-dark d-flex align-items-center mb-2 text-truncate">
                                                                                <i class="isax isax-tick-circle me-2"></i>{{ $limitLine }}
                                                                            </p>
                                                                        @endforeach
                                                                        @forelse($featureList as $feature)
                                                                            <p class="text-dark d-flex align-items-center mb-2 text-truncate">
                                                                                <i class="isax isax-tick-circle me-2"></i>{{ $feature }}
                                                                            </p>
                                                                        @empty
                                                                            @if($limitLines->isEmpty())
                                                                                <p class="text-muted mb-0">{{ __('portal.no_extra_features') }}</p>
                                                                            @endif
                                                                        @endforelse
                                                                    </div>
                                                                </div>
                                                                <div class="mt-auto">
                                                                    @if($isCurrentPaidPlan && $status === 'active')
                                                                        <button type="button" class="d-flex align-items-center justify-content-center btn border w-100" disabled>
                                                                            <i class="isax isax-bill me-1"></i> {{ __('portal.current_plan_button') }}
                                                                        </button>
                                                                    @elseif(($selectedProduct['is_automotive'] ?? false) ? $canStartPaidCheckout : !$selectedProductHasLiveBilling)
                                                                        <form method="POST" action="{{ route('automotive.portal.subscribe') }}">
                                                                            @csrf
                                                                            <input type="hidden" name="plan_id" value="{{ $paidPlan->id }}">
                                                                            @if(!($selectedProduct['is_automotive'] ?? false))
                                                                                <input type="hidden" name="product_id" value="{{ $selectedProduct['id'] }}">
                                                                            @endif
                                                                            <button type="submit" class="d-flex align-items-center justify-content-center btn border w-100">
                                                                                <i class="isax isax-shopping-cart me-1"></i>
                                                                                @if(($selectedProduct['is_automotive'] ?? false) && $status === 'trialing')
                                                                                    {{ __('portal.upgrade_to_plan', ['plan' => $paidPlan->name]) }}
                                                                                @elseif(($selectedProduct['is_automotive'] ?? false) && $status === 'past_due')
                                                                                    {{ __('portal.continue_checkout') }}
                                                                                @elseif(!($selectedProduct['is_automotive'] ?? false) && !empty($selectedProductSubscription?->gateway_checkout_session_id) && empty($selectedProductSubscription?->gateway_subscription_id))
                                                                                    {{ __('portal.continue_product_checkout') }}
                                                                                @else
                                                                                    {{ __('portal.select_continue') }}
                                                                                @endif
                                                                            </button>
                                                                        </form>
                                                                    @else
                                                                        <button type="button" class="d-flex align-items-center justify-content-center btn border w-100" disabled>
                                                                            <i class="isax isax-bill me-1"></i> {{ __('portal.billing_managed_in_system') }}
                                                                        </button>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            @component('automotive.portal.layouts.portalLayout.components.footer')
            @endcomponent
        </div>
    </div>
@endsection
