<?php $page = 'automotive/admin/subscription-expired'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="container-fuild">
        <div class="w-100 overflow-hidden position-relative flex-wrap d-block vh-100">
            <div class="row justify-content-center align-items-center vh-100 overflow-auto flex-wrap ">
                <div class="col-lg-5 mx-auto">
                    <div class="d-flex justify-content-center align-items-center">
                        <div class="d-flex flex-column justify-content-lg-center p-4 p-lg-0 pb-0 flex-fill">
                            <div class="mx-auto mb-5 text-center">
                                Automotive Tenant Admin
                            </div>

                            <div class="card border-0 p-lg-3 shadow-lg">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <span class="avatar avatar-xl bg-danger-transparent text-danger rounded-circle">
                                            <i class="isax isax-warning-2 fs-32"></i>
                                        </span>
                                    </div>

                                    <h4 class="mb-2">Subscription Expired</h4>
                                    <p class="text-muted mb-4">
                                        Your trial or subscription is no longer active. Please renew your subscription to continue using the tenant admin area.
                                    </p>

                                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                                        <a href="{{ route('automotive.admin.login') }}" class="btn bg-primary-gradient text-white">
                                            Back To Login
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
