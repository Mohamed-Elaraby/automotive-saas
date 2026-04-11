<?php $page = 'profile'; ?>
@extends('automotive.portal.layouts.portalLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="mb-3 border-bottom pb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <h6 class="mb-1">Profile</h6>
                            <p class="text-muted mb-0">Your shared customer portal for onboarding, subscription status, and workspace access.</p>
                        </div>

                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <form method="POST" action="{{ route('automotive.logout') }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-white">
                                    Sign Out
                                </button>
                            </form>

                            @if(!empty($selectedPortalBillingUrl))
                                <a href="{{ $selectedPortalBillingUrl }}" class="btn btn-outline-white">
                                    Manage Workspace Billing
                                </a>
                            @else
                                <a href="#paid-plans" class="btn btn-outline-white">
                                    View Plans &amp; Subscribe
                                </a>
                            @endif

                            <a href="#products-catalog" class="btn btn-outline-white">
                                View Products
                            </a>

                            @if($allowSystemAccess && !empty($systemUrl))
                                <a href="{{ $systemUrl }}" target="_blank" class="btn btn-primary">
                                    Open My Workspace
                                </a>
                            @endif
                        </div>
                    </div>

                    @if(session('success'))
                        <div class="alert alert-success mb-3">
                            {{ session('success') }}
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
                            Your last checkout was not completed. Your current subscription state has not changed yet, and you can continue checkout when you are ready.
                        </div>
                    @elseif($status === 'trialing')
                        <div class="alert alert-info mb-3">
                            Your account is currently on a free trial.
                            @if(!is_null($trialDaysRemaining))
                                You have <strong>{{ max((int) $trialDaysRemaining, 0) }}</strong> day(s) remaining before the trial ends.
                            @endif
                        </div>
                    @elseif($status === 'past_due' || $status === 'suspended' || $status === 'expired')
                        <div class="alert alert-warning mb-3">
                            @if($status === 'expired' && $canStartPaidCheckout)
                                Your previous subscription is
                                <strong>EXPIRED</strong>.
                                You can choose a paid plan below to start a new Stripe checkout.
                            @else
                                Your current subscription status is
                                <strong>{{ strtoupper(str_replace('_', ' ', $status)) }}</strong>.
                                Please review your plan and billing before opening the system workspace.
                            @endif
                        </div>
                    @elseif(empty($subscription))
                        <div class="alert alert-primary mb-3">
                            @if($freeTrialEnabled)
                                Your account is ready. Choose how you want to continue: start a free trial or subscribe to a paid plan.
                            @else
                                Your account is ready. Choose a paid plan and continue to checkout.
                            @endif
                        </div>
                    @endif

                    <div class="card mb-0">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <span class="bg-dark avatar avatar-sm me-2 flex-shrink-0">
                                    <i class="isax isax-info-circle fs-14"></i>
                                </span>
                                <h6 class="fs-16 fw-semibold mb-0">General Information</h6>
                            </div>

                            <div class="mb-3">
                                <span class="text-gray-9 fw-bold mb-2 d-flex">Profile Summary</span>
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
                                                Reserved Coupon: {{ $visibleCouponCode }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="border-bottom mb-3 pb-2">
                                <div class="row gx-3">
                                    <div class="col-lg-4 col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Full Name</label>
                                            <input type="text" class="form-control" value="{{ $user->name ?? '-' }}" readonly>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="text" class="form-control" value="{{ $user->email ?? '-' }}" readonly>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Company Name</label>
                                            <input type="text" class="form-control" value="{{ $profile->company_name ?? '-' }}" readonly>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Reserved Subdomain</label>
                                            <input type="text" class="form-control" value="{{ $profile->subdomain ?? '-' }}" readonly>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Primary Domain</label>
                                            <input type="text" class="form-control" value="{{ $primaryDomainValue ?: '-' }}" readonly>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">System Access</label>
                                            <input type="text" class="form-control" value="{{ $allowSystemAccess ? 'Available' : 'Not Available Yet' }}" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="border-bottom mb-3 pb-2">
                                <div class="d-flex align-items-center mb-3">
                                    <span class="bg-dark avatar avatar-sm me-2 flex-shrink-0">
                                        <i class="isax isax-info-circle fs-14"></i>
                                    </span>
                                    <h6 class="fs-16 fw-semibold mb-0">Subscription Information</h6>
                                </div>

                                <div class="row gx-3">
                                    <div class="col-lg-4 col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Current Plan</label>
                                            <input type="text" class="form-control" value="{{ $plan->name ?? $plan->slug ?? 'No Plan Yet' }}" readonly>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Current Status</label>
                                            <input type="text" class="form-control" value="{{ $status ? strtoupper(str_replace('_', ' ', $status)) : 'NOT STARTED' }}" readonly>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Billing Period</label>
                                            <input type="text" class="form-control" value="{{ !empty($subscription->billing_period) ? strtoupper((string) $subscription->billing_period) : '-' }}" readonly>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Trial Ends At</label>
                                            <input type="text" class="form-control" value="{{ $trialEndsAt ? $trialEndsAt->format('Y-m-d H:i:s') : '-' }}" readonly>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Days Remaining</label>
                                            <input type="text" class="form-control" value="{{ !is_null($trialDaysRemaining) ? max((int) $trialDaysRemaining, 0) : '-' }}" readonly>
                                        </div>
                                    </div>

                                    <div class="col-lg-4 col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Gateway</label>
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
                                    <h6 class="fs-16 fw-semibold mb-0">Domain Information</h6>
                                </div>

                                @if($domains->count() > 0)
                                    <div class="row gx-3">
                                        @foreach($domains as $domain)
                                            <div class="col-lg-6">
                                                <div class="border rounded p-3 mb-3">
                                                    <div class="fw-semibold mb-2">{{ $domain['domain'] }}</div>
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <a href="{{ $domain['url'] }}" target="_blank" class="btn btn-sm btn-outline-white">
                                                            Open Domain
                                                        </a>

                                                        @if($allowSystemAccess)
                                                            <a href="{{ $domain['admin_login_url'] }}" target="_blank" class="btn btn-sm btn-primary">
                                                            Open Workspace Login
                                                            </a>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="alert alert-light border">
                                        Your domain will appear here after trial or paid activation.
                                    </div>
                                @endif
                            </div>

                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="#paid-plans" class="btn btn-outline-white">
                                        View Paid Plans
                                    </a>

                                    @if(!empty($selectedPortalBillingUrl))
                                        <a href="{{ $selectedPortalBillingUrl }}" class="btn btn-outline-white">
                                            Open Billing Control
                                        </a>
                                    @endif

                                    @if($allowSystemAccess && !empty($systemUrl))
                                        <a href="{{ $systemUrl }}" target="_blank" class="btn btn-primary">
                                            Go to My Workspace
                                        </a>

                                        <a href="#paid-plans" class="btn btn-outline-white">
                                            Upgrade to Paid Plan
                                        </a>
                                    @endif
                                </div>

                                <button type="button" class="btn btn-outline-white" disabled>
                                    Profile Overview Only
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4" id="products-catalog">
                        <div class="card-body">
                            <div class="mb-4">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                    <div>
                                        <h6 class="mb-1">Products Catalog</h6>
                                        <p class="text-muted mb-0">Start with one product today, then attach more modules to the same workspace later.</p>
                                    </div>
                                    <span class="badge bg-soft-info text-info">
                                        One workspace, many connected products
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
                                                        <p class="text-muted mb-0">{{ $productRow['description'] ?: 'Product catalog item.' }}</p>
                                                    </div>
                                                    <span class="badge {{ $statusBadgeClass }}">
                                                        {{ $productRow['status_label'] }}
                                                    </span>
                                                </div>

                                                <div class="mb-3">
                                                    <div class="text-muted fs-13 mb-1">Product Code</div>
                                                    <div class="fw-semibold">{{ strtoupper((string) $productRow['code']) }}</div>
                                                </div>

                                                <div class="mb-3">
                                                    @if($productRow['is_subscribed'])
                                                        <p class="mb-1 text-success">This product is already attached to your workspace.</p>
                                                    @elseif($productRow['is_automotive'])
                                                        <p class="mb-1 text-muted">This product is currently active in your workspace catalog.</p>
                                                    @else
                                                        <p class="mb-1 text-muted">This product will connect to the same tenant workspace when enabled.</p>
                                                    @endif
                                                </div>

                                                <div class="mt-auto d-flex flex-wrap gap-2">
                                                    <a href="{{ $productRow['action_url'] }}" class="btn btn-outline-white">
                                                        {{ $productRow['action_label'] }}
                                                    </a>

                                                    @if($productRow['is_subscribed'] && $productRow['is_automotive'] && $allowSystemAccess && !empty($systemUrl))
                                                        <a href="{{ $systemUrl }}" target="_blank" class="btn btn-primary">
                                                            Open Product Workspace
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

                    <div class="card mt-4" id="paid-plans">
                        <div class="card-body">
                            @php
                                $plansByPeriod = collect($paidPlans ?? [])->groupBy(fn ($plan) => (string) ($plan->billing_period ?? 'monthly'));
                                $periodTabs = collect([
                                    'monthly' => 'Monthly',
                                    'yearly' => 'Yearly',
                                    'one_time' => 'One Time',
                                ])->filter(fn ($label, $period) => $plansByPeriod->has($period));
                                $activePeriod = (string) ($periodTabs->keys()->first() ?? 'monthly');
                                $selectedProductName = (string) ($selectedProduct['name'] ?? 'Selected Product');
                                $selectedProductDescription = (string) ($selectedProduct['description'] ?? '');
                            @endphp

                            <div class="mb-4">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                    <div>
                                        <h6 class="mb-1">Product Subscription Options</h6>
                                        <p class="text-muted mb-0">
                                            @if($selectedProductSupportsCheckout)
                                                @if(empty($subscription) && empty($selectedProductWasExplicit))
                                                    Choose a product from the catalog first, then review its trial and paid options here.
                                                @else
                                                    Review trial and paid options for <strong>{{ $selectedProductName }}</strong>, compare the real limits, then continue with the right subscription flow.
                                                @endif
                                            @else
                                                Review activation and enablement status for <strong>{{ $selectedProductName }}</strong>. Additional products that are not directly billable yet will ask for enablement first.
                                            @endif
                                        </p>
                                    </div>

                                    @if(!empty($visibleCouponCode))
                                        <span class="badge bg-soft-success text-success">
                                            Reserved Coupon: {{ $visibleCouponCode }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            @if(($selectedProductCapabilities ?? collect())->isNotEmpty())
                                <div class="alert alert-light border mb-4">
                                    <div class="fw-semibold mb-2">Included Product Capabilities</div>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach($selectedProductCapabilities as $capabilityName)
                                            <span class="badge bg-white text-dark border">{{ $capabilityName }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if(empty($subscription) && $freeTrialEnabled && $selectedProductHasTrialPlan && !empty($selectedProductWasExplicit))
                                <div class="alert alert-primary border mb-4">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                        <div>
                                            <div class="fw-semibold mb-1">{{ $selectedProductName }} Free Trial</div>
                                            <div class="text-muted">
                                                Start a dedicated trial for this product. Current admin setting will provision <strong>{{ (int) ($selectedProductTrialDays ?? 14) }}</strong> day(s).
                                            </div>
                                        </div>
                                        <form method="POST" action="{{ route('automotive.portal.start-trial') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="product_id" value="{{ $selectedProduct['id'] }}">
                                            <button type="submit" class="btn btn-primary">
                                                Start {{ $selectedProductName }} Free Trial
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endif

                            @if(!$selectedProductSupportsCheckout)
                                @php
                                    $selectedProductEnablementStatus = (string) ($selectedProductEnablementRequest->status ?? '');
                                @endphp
                                <div class="alert alert-info">
                                    {{ $selectedProductName }} is visible in the shared workspace catalog.
                                    @if(empty($subscription))
                                        This product is visible in the shared workspace catalog, but it does not have a direct trial or paid checkout configured yet.
                                    @else
                                        {{ $selectedProductName }} is visible in the shared workspace catalog.
                                        Submit or review enablement first. Billing checkout becomes available here after approval.
                                    @endif
                                </div>

                                @if($selectedProductEnablementStatus === 'rejected')
                                    <div class="alert alert-warning">
                                        Your last enablement request for {{ $selectedProductName }} was rejected. You can submit a new request when ready.
                                    </div>
                                @elseif($selectedProductEnablementStatus === 'approved')
                                    <div class="alert alert-success">
                                        Your enablement request for {{ $selectedProductName }} was approved and the product is now attached to your workspace.
                                    </div>
                                @endif

                                <div class="mb-4 d-flex flex-wrap gap-2">
                                    @if(empty($selectedProduct['is_active']))
                                        <button type="button" class="btn btn-outline-white" disabled>
                                            Product Coming Soon
                                        </button>
                                    @elseif(!empty($selectedProduct['is_subscribed']))
                                        <button type="button" class="btn btn-success" disabled>
                                            Product Already Attached
                                        </button>
                                    @elseif($selectedProductEnablementStatus === 'pending')
                                        <button type="button" class="btn btn-outline-white" disabled>
                                            Enablement Request Pending
                                        </button>
                                    @elseif($selectedProductEnablementStatus === 'approved')
                                        <button type="button" class="btn btn-success" disabled>
                                            Enablement Approved
                                        </button>
                                    @else
                                        <form method="POST" action="{{ route('automotive.portal.products.request-enable') }}">
                                            @csrf
                                            <input type="hidden" name="product_id" value="{{ $selectedProduct['id'] }}">
                                            <button type="submit" class="btn btn-primary">
                                                {{ $selectedProductEnablementStatus === 'rejected' ? 'Request Product Enablement Again' : 'Request Product Enablement' }}
                                            </button>
                                        </form>
                                    @endif
                                </div>

                                @if($paidPlans->count() === 0)
                                    <div class="alert alert-light border mb-0">
                                        {{ $selectedProductName }} does not have active paid plans configured yet. This portal section is now ready for product-level enablement once plans are added.
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
                                                                <p class="mb-0">{{ $paidPlan->description ?: ($selectedProductDescription ?: 'Configured product plan.') }}</p>
                                                            </div>
                                                            <div class="mb-3">
                                                                <h3 class="d-flex align-items-center mb-1">
                                                                    {{ str_replace(' ' . $paidPlan->currency_code, '', $paidPlan->display_price) }}
                                                                </h3>
                                                                <p class="mb-0">
                                                                    {{ strtoupper((string) ($paidPlan->slug ?? 'PLAN')) }} · {{ $paidPlan->currency_code }} billing
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
                                                                <i class="isax isax-lock me-1"></i> Approval Required Before Checkout
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            @elseif($hasLiveStripeSubscription)
                                <div class="alert alert-info mb-0">
                                    This account already has a live Stripe subscription. Further billing changes should be managed from inside the tenant billing area.
                                </div>
                            @elseif($paidPlans->count() === 0)
                                <div class="alert alert-light border mb-0">
                                    No active paid plans are available right now.
                                </div>
                            @else
                                @if(!($selectedProduct['is_automotive'] ?? false) && $selectedProductHasPendingCheckout)
                                    <div class="alert alert-warning mb-3">
                                        Your last checkout for {{ $selectedProductName }} is still pending. Continue the product checkout below, or wait for Stripe webhook sync if payment already completed.
                                    </div>
                                @elseif(!($selectedProduct['is_automotive'] ?? false) && $selectedProductHasLiveBilling)
                                    <div class="alert alert-info mb-3">
                                        {{ $selectedProductName }} already has a live billed subscription on this workspace. Changes should be managed from the tenant billing area.
                                    </div>
                                @elseif(!($selectedProduct['is_automotive'] ?? false) && in_array($selectedProductStatus ?? '', ['past_due', 'suspended'], true))
                                    <div class="alert alert-warning mb-3">
                                        {{ $selectedProductName }} is currently <strong>{{ strtoupper(str_replace('_', ' ', (string) $selectedProductStatus)) }}</strong>.
                                        Review billing below and continue checkout when ready.
                                    </div>
                                @elseif(!($selectedProduct['is_automotive'] ?? false) && in_array($selectedProductStatus ?? '', ['expired', 'canceled', 'cancelled'], true))
                                    <div class="alert alert-warning mb-3">
                                        Your previous {{ $selectedProductName }} subscription is <strong>{{ strtoupper(str_replace('_', ' ', (string) $selectedProductStatus)) }}</strong>.
                                        You can start a new checkout below.
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
                                                            'yearly' => '/year',
                                                            'one_time' => ' one-time',
                                                            default => '/month',
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
                                                                                <p class="mb-0">{{ $paidPlan->description ?: 'Configured paid plan for production billing.' }}</p>
                                                                            </div>
                                                                            @if($isCurrentPaidPlan && $status === 'active')
                                                                                <span class="badge bg-success position-absolute top-0 end-0">Current</span>
                                                                            @elseif($loop->first)
                                                                                <span class="badge bg-soft-info text-info position-absolute top-0 end-0">Popular</span>
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
                                                                            {{ strtoupper((string) ($paidPlan->slug ?? 'PLAN')) }} · {{ $paidPlan->currency_code }} billing
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                                <div class="mt-3 mb-3">
                                                                    <div class="mb-1">
                                                                        <h6 class="fs-16 mb-2">What you get:</h6>
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
                                                                                <p class="text-muted mb-0">No extra features are configured for this plan yet.</p>
                                                                            @endif
                                                                        @endforelse
                                                                    </div>
                                                                </div>
                                                                <div class="mt-auto">
                                                                    @if($isCurrentPaidPlan && $status === 'active')
                                                                        <button type="button" class="d-flex align-items-center justify-content-center btn border w-100" disabled>
                                                                            <i class="isax isax-bill me-1"></i> Current Plan
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
                                                                                    Upgrade to {{ $paidPlan->name }}
                                                                                @elseif(($selectedProduct['is_automotive'] ?? false) && $status === 'past_due')
                                                                                    Continue Checkout
                                                                                @elseif(!($selectedProduct['is_automotive'] ?? false) && !empty($selectedProductSubscription?->gateway_checkout_session_id) && empty($selectedProductSubscription?->gateway_subscription_id))
                                                                                    Continue Product Checkout
                                                                                @else
                                                                                    Select &amp; Continue
                                                                                @endif
                                                                            </button>
                                                                        </form>
                                                                    @else
                                                                        <button type="button" class="d-flex align-items-center justify-content-center btn border w-100" disabled>
                                                                            <i class="isax isax-bill me-1"></i> Billing Managed In System
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
                </div>
            </div>

            @component('automotive.portal.layouts.portalLayout.components.footer')
            @endcomponent
        </div>
    </div>
@endsection
