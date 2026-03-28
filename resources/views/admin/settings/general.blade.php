<?php $page = 'admin-settings-general'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="page-header">
                <div class="content-page-header">
                    <h5>SaaS Settings</h5>
                    <p class="text-muted mb-0">Control public onboarding behavior and availability.</p>
                </div>
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

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Onboarding Controls</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.settings.general.update') }}">
                        @csrf
                        @method('PUT')

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

                        <div class="text-muted mb-4">
                            When disabled, the public Free Trial option will be hidden, and direct access to the trial registration page will be blocked.
                        </div>

                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
