<?php $page = 'profile'; ?>
@extends('automotive.layouts.portalLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="mb-3 border-bottom pb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <h6 class="mb-1">Profile</h6>
                            <p class="text-muted mb-0">Your front customer portal for onboarding, subscription status, and system access.</p>
                        </div>

                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <form method="POST" action="{{ route('automotive.logout') }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-white">
                                    Sign Out
                                </button>
                            </form>

                            <a href="#paid-plans" class="btn btn-outline-white">
                                View Plans &amp; Subscribe
                            </a>

                            @if($allowSystemAccess && !empty($systemUrl))
                                <a href="{{ $systemUrl }}" target="_blank" class="btn btn-primary">
                                    Open My Trial System
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
                            Your current subscription status is
                            <strong>{{ strtoupper(str_replace('_', ' ', $status)) }}</strong>.
                            Please review your plan and billing before opening the system workspace.
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
                                        @if(!empty($profile?->coupon_code))
                                            <span class="badge bg-soft-success text-success mt-2">
                                                Coupon Reserved: {{ $profile->coupon_code }}
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
                                                                Open Admin Login
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
                                    @if(empty($subscription) && $freeTrialEnabled)
                                        <form method="POST" action="{{ route('automotive.portal.start-trial') }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-primary">
                                                Start Free Trial
                                            </button>
                                        </form>
                                    @endif

                                    <a href="#paid-plans" class="btn btn-outline-white">
                                        View Paid Plans
                                    </a>

                                    @if($allowSystemAccess && !empty($systemUrl))
                                        <a href="{{ $systemUrl }}" target="_blank" class="btn btn-primary">
                                            Go to My System
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

                    <div class="card mt-4" id="paid-plans">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                <div>
                                    <h6 class="mb-1">Paid Plan Selection</h6>
                                    <p class="text-muted mb-0">Choose a paid plan from your customer portal, then continue to checkout.</p>
                                </div>

                                @if(!empty($profile?->coupon_code))
                                    <span class="badge bg-soft-success text-success">
                                        Reserved Coupon: {{ $profile->coupon_code }}
                                    </span>
                                @endif
                            </div>

                            @if($hasLiveStripeSubscription)
                                <div class="alert alert-info mb-0">
                                    This account already has a live Stripe subscription. Further billing changes should be managed from inside the tenant billing area.
                                </div>
                            @elseif($paidPlans->count() === 0)
                                <div class="alert alert-light border mb-0">
                                    No active paid plans are available right now.
                                </div>
                            @else
                                <div class="row">
                                    @foreach($paidPlans as $paidPlan)
                                        @php
                                            $isCurrentPaidPlan = !empty($plan)
                                                && (int) ($plan->id ?? 0) === (int) $paidPlan->id
                                                && !in_array((string) $status, ['trialing', ''], true);
                                        @endphp
                                        <div class="col-xl-4 col-md-6 d-flex">
                                            <div class="border rounded p-3 mb-3 w-100 d-flex flex-column">
                                                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                                    <div>
                                                        <h6 class="mb-1">{{ $paidPlan->name }}</h6>
                                                        <div class="text-muted fs-13">{{ $paidPlan->billing_period_label }}</div>
                                                    </div>
                                                    @if($isCurrentPaidPlan && $status === 'active')
                                                        <span class="badge bg-success">Current</span>
                                                    @endif
                                                </div>

                                                <div class="mb-2">
                                                    <span class="fs-24 fw-bold text-dark">{{ $paidPlan->display_price }}</span>
                                                </div>

                                                @if(!empty($paidPlan->description))
                                                    <p class="text-muted mb-3">{{ $paidPlan->description }}</p>
                                                @endif

                                                @if(!empty($paidPlan->features_array))
                                                    <div class="mb-3">
                                                        @foreach($paidPlan->features_array as $feature)
                                                            <div class="d-flex align-items-start mb-2">
                                                                <span class="badge bg-soft-primary text-primary me-2">+</span>
                                                                <span class="text-muted">{{ $feature }}</span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif

                                                <div class="mt-auto">
                                                    @if($isCurrentPaidPlan && $status === 'active')
                                                        <button type="button" class="btn btn-outline-white w-100" disabled>
                                                            Current Active Plan
                                                        </button>
                                                    @elseif($canStartPaidCheckout)
                                                        <form method="POST" action="{{ route('automotive.portal.subscribe') }}">
                                                            @csrf
                                                            <input type="hidden" name="plan_id" value="{{ $paidPlan->id }}">
                                                            <button type="submit" class="btn btn-primary w-100">
                                                                @if($status === 'trialing')
                                                                    Upgrade to {{ $paidPlan->name }}
                                                                @elseif($status === 'past_due')
                                                                    Continue Checkout
                                                                @else
                                                                    Select &amp; Continue
                                                                @endif
                                                            </button>
                                                        </form>
                                                    @else
                                                        <button type="button" class="btn btn-outline-white w-100" disabled>
                                                            Billing Managed In System
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            @component('components.footer')
            @endcomponent
        </div>
    </div>
@endsection
