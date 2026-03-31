<?php $page = 'automotive/portal/get-started'; ?>
@extends('automotive.layouts.portalLayout.mainlayout')

@section('content')
    <div class="container-fuild">
        <div class="w-100 overflow-hidden position-relative flex-wrap d-block vh-100">
            <div class="row justify-content-center align-items-center vh-100 overflow-auto flex-wrap">
                <div class="col-xl-10 mx-auto">
                    <div class="text-center mb-5">
                        <h3 class="mb-2">Create Your Account First</h3>
                        <p class="mb-0 text-muted">After registration, you will enter your customer portal and choose either free trial or a paid plan.</p>
                    </div>

                    <div class="row justify-content-center">
                        @if($freeTrialEnabled)
                            <div class="col-lg-5 d-flex">
                                <div class="card border-0 shadow-lg w-100">
                                    <div class="card-body p-4">
                                        <span class="badge bg-success-subtle text-success mb-3">Free Trial</span>
                                        <h4 class="mb-2">Register Then Start Trial</h4>
                                        <p class="text-muted mb-3">
                                            Create your account first, reserve your preferred subdomain, then start your free trial from the customer portal.
                                        </p>
                                        <ul class="mb-4 ps-3 text-muted">
                                            <li>Account registration first</li>
                                            <li>14-day trial starts later from portal</li>
                                            <li>Upgrade to paid when ready</li>
                                        </ul>
                                        <a href="{{ route('automotive.register') }}" class="btn bg-primary-gradient text-white w-100">Create Account</a>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="col-lg-5 d-flex">
                            <div class="card border-0 shadow-lg w-100">
                                <div class="card-body p-4">
                                    <span class="badge bg-primary-subtle text-primary mb-3">Paid Plans</span>
                                    <h4 class="mb-2">Register Then View Paid Plans</h4>
                                    <p class="text-muted mb-3">
                                        Create your account first, then open the customer portal to choose a paid plan and continue to checkout.
                                    </p>
                                    <ul class="mb-4 ps-3 text-muted">
                                        <li>Account registration first</li>
                                        <li>Paid plan selection inside portal</li>
                                        <li>Stripe checkout starts from portal</li>
                                    </ul>
                                    <a href="{{ route('automotive.register') }}" class="btn btn-dark w-100">Create Account To Continue</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <a href="{{ route('automotive.login') }}" class="text-primary fw-medium">Customer Portal Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
