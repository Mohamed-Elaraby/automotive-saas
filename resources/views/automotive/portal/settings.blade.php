<?php $page = 'portal-settings'; ?>
@extends('automotive.portal.layouts.portalLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="row justify-content-center">
                <div class="col-xl-11">
                    <div class="mb-3 border-bottom pb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <h6 class="mb-1">Account &amp; Settings</h6>
                            <p class="text-muted mb-0">Manage your portal-owned profile, workspace identity, credentials, and domain snapshot here.</p>
                        </div>

                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <a href="{{ route('automotive.portal') }}" class="btn btn-outline-white">
                                Back To Portal
                            </a>

                            @if(!empty($systemUrl) && $allowSystemAccess)
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

                    <div class="row">
                        <div class="col-xl-8">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Tenant Account Profile</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="{{ route('automotive.portal.settings.profile.update') }}">
                                        @csrf
                                        @method('PUT')

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Full Name</label>
                                                    <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Email Address</label>
                                                    <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-0">
                                                    <label class="form-label">Company / Workspace Name</label>
                                                    <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $profile->company_name ?? '') }}" required>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-end mt-3">
                                            <button type="submit" class="btn btn-primary">
                                                Save Profile Changes
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Credential Controls</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="{{ route('automotive.portal.settings.security.update') }}">
                                        @csrf
                                        @method('PUT')

                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label">Current Password</label>
                                                    <input type="password" name="current_password" class="form-control" required>
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label">New Password</label>
                                                    <input type="password" name="password" class="form-control" required>
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label">Confirm New Password</label>
                                                    <input type="password" name="password_confirmation" class="form-control" required>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-end">
                                            <button type="submit" class="btn btn-dark">
                                                Update Password
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Workspace Snapshot</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="text-muted small">Reserved Subdomain</div>
                                        <div class="fw-semibold">{{ $profile->subdomain ?? '-' }}</div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="text-muted small">Primary Domain</div>
                                        <div class="fw-semibold">{{ $primaryDomainValue ?: '-' }}</div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="text-muted small">Workspace Tenant ID</div>
                                        <div class="fw-semibold">{{ $workspaceTenantId ?: '-' }}</div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="text-muted small">Current Plan</div>
                                        <div class="fw-semibold">{{ $plan->name ?? $plan->slug ?? 'No active plan' }}</div>
                                    </div>

                                    <div class="mb-0">
                                        <div class="text-muted small">Portal Access To Runtime</div>
                                        <div class="fw-semibold">{{ $allowSystemAccess ? 'Workspace access available' : 'Runtime access not available yet' }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Workspace Directory</h5>
                                </div>
                                <div class="card-body">
                                    @forelse($workspaceSnapshots as $workspaceSnapshot)
                                        <div class="border rounded p-3 mb-3">
                                            <div class="fw-semibold mb-1">{{ $workspaceSnapshot['company_name'] }}</div>
                                            <div class="text-muted small mb-1">Tenant: {{ $workspaceSnapshot['tenant_id'] }}</div>
                                            <div class="text-muted small">{{ $workspaceSnapshot['owner_email'] ?: 'No owner email snapshot yet' }}</div>
                                        </div>
                                    @empty
                                        <p class="text-muted mb-0">No workspace has been provisioned for this portal account yet.</p>
                                    @endforelse
                                </div>
                            </div>

                            <div class="card mb-0">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Domain / Subdomain Snapshot</h5>
                                </div>
                                <div class="card-body">
                                    @forelse($domains as $domain)
                                        <div class="border rounded p-3 mb-3">
                                            <div class="fw-semibold mb-2">{{ $domain['domain'] }}</div>
                                            <div class="d-flex flex-wrap gap-2">
                                                <a href="{{ $domain['url'] }}" target="_blank" class="btn btn-sm btn-outline-white">
                                                    Open Domain
                                                </a>

                                                @if($allowSystemAccess && !empty($domain['admin_login_url']))
                                                    <a href="{{ $domain['admin_login_url'] }}" target="_blank" class="btn btn-sm btn-primary">
                                                        Open Workspace Login
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-muted mb-0">Your live domain snapshot will appear here after workspace provisioning.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
