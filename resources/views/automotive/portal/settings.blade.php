<?php $page = 'portal-settings'; ?>
@extends('automotive.portal.layouts.portalLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="row justify-content-center">
                <div class="col-xl-11">
                    <div class="mb-3 border-bottom pb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <h6 class="mb-1">{{ __('shared.account_settings') }}</h6>
                            <p class="text-muted mb-0">{{ __('portal.account_settings_intro') }}</p>
                        </div>

                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <a href="{{ route('automotive.portal') }}" class="btn btn-outline-white">
                                {{ __('portal.back_to_portal') }}
                            </a>

                            @if(!empty($systemUrl) && $allowSystemAccess)
                                <a href="{{ $systemUrl }}" target="_blank" class="btn btn-primary">
                                    {{ __('shared.open_my_workspace') }}
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
                                    <h5 class="card-title mb-0">{{ __('portal.tenant_account_profile') }}</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="{{ route('automotive.portal.settings.profile.update') }}">
                                        @csrf
                                        @method('PUT')

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">{{ __('portal.full_name') }}</label>
                                                    <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">{{ __('portal.email_address') }}</label>
                                                    <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="mb-0">
                                                    <label class="form-label">{{ __('portal.company_workspace_name') }}</label>
                                                    <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $profile->company_name ?? '') }}" required>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-end mt-3">
                                            <button type="submit" class="btn btn-primary">
                                                {{ __('portal.save_profile_changes') }}
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">{{ __('portal.credential_controls') }}</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="{{ route('automotive.portal.settings.security.update') }}">
                                        @csrf
                                        @method('PUT')

                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label">{{ __('portal.current_password') }}</label>
                                                    <input type="password" name="current_password" class="form-control" required>
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label">{{ __('portal.new_password') }}</label>
                                                    <input type="password" name="password" class="form-control" required>
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label">{{ __('portal.confirm_new_password') }}</label>
                                                    <input type="password" name="password_confirmation" class="form-control" required>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-end">
                                            <button type="submit" class="btn btn-dark">
                                                {{ __('portal.update_password') }}
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">{{ __('portal.workspace_snapshot') }}</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="text-muted small">{{ __('portal.reserved_subdomain') }}</div>
                                        <div class="fw-semibold">{{ $profile->subdomain ?? '-' }}</div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="text-muted small">{{ __('portal.primary_domain') }}</div>
                                        <div class="fw-semibold">{{ $primaryDomainValue ?: '-' }}</div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="text-muted small">{{ __('portal.workspace_tenant_id') }}</div>
                                        <div class="fw-semibold">{{ $workspaceTenantId ?: '-' }}</div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="text-muted small">{{ __('shared.current_plan') }}</div>
                                        <div class="fw-semibold">{{ $plan->name ?? $plan->slug ?? __('portal.no_active_plan') }}</div>
                                    </div>

                                    <div class="mb-0">
                                        <div class="text-muted small">{{ __('portal.portal_access_to_runtime') }}</div>
                                        <div class="fw-semibold">{{ $allowSystemAccess ? __('portal.workspace_access_available') : __('portal.runtime_access_not_available_yet') }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">{{ __('portal.workspace_directory') }}</h5>
                                </div>
                                <div class="card-body">
                                    @forelse($workspaceSnapshots as $workspaceSnapshot)
                                        <div class="border rounded p-3 mb-3">
                                            <div class="fw-semibold mb-1">{{ $workspaceSnapshot['company_name'] }}</div>
                                            <div class="text-muted small mb-1">{{ __('portal.tenant') }}: {{ $workspaceSnapshot['tenant_id'] }}</div>
                                            <div class="text-muted small">{{ $workspaceSnapshot['owner_email'] ?: __('portal.no_owner_email_snapshot_yet') }}</div>
                                        </div>
                                    @empty
                                        <p class="text-muted mb-0">{{ __('portal.no_workspace_provisioned_yet') }}</p>
                                    @endforelse
                                </div>
                            </div>

                            <div class="card mb-0">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">{{ __('portal.domain_subdomain_snapshot') }}</h5>
                                </div>
                                <div class="card-body">
                                    @forelse($domains as $domain)
                                        <div class="border rounded p-3 mb-3">
                                            <div class="fw-semibold mb-2">{{ $domain['domain'] }}</div>
                                            <div class="d-flex flex-wrap gap-2">
                                                <a href="{{ $domain['url'] }}" target="_blank" class="btn btn-sm btn-outline-white">
                                                    {{ __('portal.open_domain') }}
                                                </a>

                                                @if($allowSystemAccess && !empty($domain['admin_login_url']))
                                                    <a href="{{ $domain['admin_login_url'] }}" target="_blank" class="btn btn-sm btn-primary">
                                                        {{ __('portal.open_workspace_login') }}
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-muted mb-0">{{ __('portal.domain_snapshot_after_provisioning') }}</p>
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
