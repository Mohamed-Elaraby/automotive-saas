<?php $page = 'saas-settings-general'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="row justify-content-center">
                <div class="col-xl-11">
                    <div class="row settings-wrapper d-flex">
                        <div class="col-xxl-3 col-lg-4">
                            <div class="card settings-card">
                                <div class="card-header">
                                    <h6 class="mb-0">SaaS Settings</h6>
                                </div>
                                <div class="card-body">
                                    <div class="sidebars settings-sidebar">
                                        <div class="sidebar-inner">
                                            <div class="sidebar-menu p-0">
                                                <ul>
                                                    <li>
                                                        <a href="{{ route('admin.settings.general.edit') }}" class="active fs-14 fw-medium d-flex align-items-center">
                                                            <i class="isax isax-setting-2 fs-18 me-1"></i>General Settings
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a href="{{ route('admin.plans.index') }}" class="fs-14 fw-medium d-flex align-items-center">
                                                            <i class="isax isax-crown5 fs-18 me-1"></i>Plans
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a href="{{ route('admin.subscriptions.index') }}" class="fs-14 fw-medium d-flex align-items-center">
                                                            <i class="isax isax-receipt-2 fs-18 me-1"></i>Subscriptions
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a href="{{ route('admin.coupons.index') }}" class="fs-14 fw-medium d-flex align-items-center">
                                                            <i class="isax isax-ticket-discount fs-18 me-1"></i>Coupons
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xxl-9 col-lg-8">
                            <div class="mb-3">
                                <div class="pb-3 border-bottom mb-3">
                                    <h6>SaaS Settings</h6>
                                    <p class="text-muted mb-0">Control public onboarding behavior and availability.</p>
                                </div>

                                @if(session('success'))
                                    <div class="alert alert-success">{{ session('success') }}</div>
                                @endif

                                @if($errors->any())
                                    <div class="alert alert-danger">
                                        <ul class="mb-0 ps-3">
                                            @foreach($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <div class="d-flex align-items-center mb-3">
                                    <span class="bg-dark avatar avatar-sm me-2 flex-shrink-0">
                                        <i class="isax isax-info-circle fs-14"></i>
                                    </span>
                                    <h6 class="fs-16 fw-semibold mb-0">Onboarding Controls</h6>
                                </div>

                                <form method="POST" action="{{ route('admin.settings.general.update') }}">
                                    @csrf
                                    @method('PUT')

                                    <div class="card border-0 shadow-sm mb-3">
                                        <div class="card-body">
                                            <div class="form-check form-switch mb-3">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    role="switch"
                                                    id="free_trial_enabled"
                                                    name="free_trial_enabled"
                                                    value="1"
                                                    @checked($freeTrialEnabled)
                                                >
                                                <label class="form-check-label" for="free_trial_enabled">
                                                    Enable and show Free Trial entry to public users
                                                </label>
                                            </div>

                                            <div class="text-muted">
                                                When disabled, the public Free Trial option will be hidden, and direct access to the trial registration page will be blocked.
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center justify-content-between">
                                        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-white">Cancel</a>
                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
