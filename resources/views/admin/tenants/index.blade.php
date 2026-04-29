<?php $page = 'admin-tenants-index'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    @php
        $statusOptions = [
            '' => __('admin.all_subscription_statuses'),
            'trialing' => __('admin.trialing'),
            'active' => __('admin.active'),
            'past_due' => __('admin.past_due'),
            'suspended' => __('admin.suspended'),
            'cancelled' => __('admin.cancelled'),
            'expired' => __('admin.expired'),
        ];

        $statusBadgeClass = function (?string $status): string {
            return match ($status) {
                'active' => 'bg-success',
                'trialing' => 'bg-info text-dark',
                'past_due' => 'bg-warning text-dark',
                'suspended' => 'bg-danger',
                'cancelled' => 'bg-secondary',
                'expired' => 'bg-dark',
                default => 'bg-light text-dark',
            };
        };

        $gatewayBadgeClass = function (?string $gateway): string {
            return match ($gateway) {
                'stripe' => 'bg-primary',
                null, '' => 'bg-light text-dark',
                default => 'bg-dark',
            };
        };
    @endphp

    <div class="page-wrapper">
        <div class="content">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>{{ __('admin.tenants') }}</h5>
                    <p class="text-muted mb-0">{{ __('admin.tenants_intro') }}</p>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="text-muted mb-1">{{ __('admin.total_tenants') }}</div>
                            <h4 class="mb-0">{{ number_format((int) ($stats['total_tenants'] ?? 0)) }}</h4>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="text-muted mb-1">{{ __('admin.with_domains') }}</div>
                            <h4 class="mb-0 text-primary">{{ number_format((int) ($stats['tenants_with_domains'] ?? 0)) }}</h4>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="text-muted mb-1">{{ __('admin.active') }}</div>
                            <h4 class="mb-0 text-success">{{ number_format((int) ($stats['active_subscriptions'] ?? 0)) }}</h4>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="text-muted mb-1">{{ __('admin.trialing') }}</div>
                            <h4 class="mb-0 text-info">{{ number_format((int) ($stats['trialing_subscriptions'] ?? 0)) }}</h4>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="text-muted mb-1">{{ __('admin.past_due') }}</div>
                            <h4 class="mb-0 text-warning">{{ number_format((int) ($stats['past_due_subscriptions'] ?? 0)) }}</h4>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="text-muted mb-1">{{ __('admin.suspended') }}</div>
                            <h4 class="mb-0 text-danger">{{ number_format((int) ($stats['suspended_subscriptions'] ?? 0)) }}</h4>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.tenants.index') }}">
                        <div class="row g-3">
                            <div class="col-xl-4 col-lg-6">
                                <label class="form-label">{{ __('admin.search') }}</label>
                                <input
                                    type="text"
                                    name="q"
                                    value="{{ $filters['q'] ?? '' }}"
                                    class="form-control"
                                    placeholder="{{ __('admin.tenant_search_placeholder') }}"
                                >
                            </div>

                            <div class="col-xl-2 col-lg-3">
                                <label class="form-label">{{ __('admin.subscription_status') }}</label>
                                <select name="status" class="form-select">
                                    @foreach($statusOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-xl-2 col-lg-3">
                                <label class="form-label">{{ __('admin.plan') }}</label>
                                <select name="plan_id" class="form-select">
                                    <option value="">{{ __('admin.all_plans') }}</option>
                                    @foreach(($filterOptions['plans'] ?? []) as $plan)
                                        <option value="{{ $plan['id'] }}" @selected(($filters['plan_id'] ?? '') === (string) $plan['id'])>{{ $plan['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-xl-2 col-lg-3">
                                <label class="form-label">{{ __('admin.gateway') }}</label>
                                <select name="gateway" class="form-select">
                                    <option value="">{{ __('admin.all_gateways') }}</option>
                                    @foreach(($filterOptions['gateway_options'] ?? []) as $gateway)
                                        <option value="{{ $gateway }}" @selected(($filters['gateway'] ?? '') === $gateway)>{{ strtoupper($gateway) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-xl-2 col-lg-3">
                                <label class="form-label">{{ __('admin.has_domain') }}</label>
                                <select name="has_domain" class="form-select">
                                    <option value="">{{ __('admin.all') }}</option>
                                    <option value="yes" @selected(($filters['has_domain'] ?? '') === 'yes')>{{ __('admin.yes') }}</option>
                                    <option value="no" @selected(($filters['has_domain'] ?? '') === 'no')>{{ __('admin.no') }}</option>
                                </select>
                            </div>

                            <div class="col-xl-2 col-lg-3">
                                <label class="form-label">{{ __('admin.created_from') }}</label>
                                <input type="date" name="created_from" value="{{ $filters['created_from'] ?? '' }}" class="form-control">
                            </div>

                            <div class="col-xl-2 col-lg-3">
                                <label class="form-label">{{ __('admin.created_to') }}</label>
                                <input type="date" name="created_to" value="{{ $filters['created_to'] ?? '' }}" class="form-control">
                            </div>

                            <div class="col-xl-2 col-lg-3 d-flex align-items-end">
                                <div class="d-grid w-100">
                                    <button type="submit" class="btn btn-primary">{{ __('admin.apply_filters') }}</button>
                                </div>
                            </div>

                            <div class="col-xl-2 col-lg-3 d-flex align-items-end">
                                <div class="d-grid w-100">
                                    <a href="{{ route('admin.tenants.index') }}" class="btn btn-light">{{ __('admin.reset') }}</a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    @if($tenants->count() === 0)
                        <div class="alert alert-warning mb-0">{{ __('admin.no_tenants_match_filters') }}</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>{{ __('admin.tenant_id') }}</th>
                                    <th>{{ __('admin.company') }}</th>
                                    <th>{{ __('admin.primary_domain') }}</th>
                                    <th>{{ __('admin.owner_admin') }}</th>
                                    <th>{{ __('admin.plan') }}</th>
                                    <th>{{ __('admin.status') }}</th>
                                    <th>{{ __('admin.gateway') }}</th>
                                    <th>{{ __('admin.billing_period') }}</th>
                                    <th>{{ __('admin.created_at') }}</th>
                                    <th class="text-end">{{ __('admin.actions') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($tenants as $tenant)
                                    @php
                                        $row = $tenantRows[(string) $tenant->getKey()] ?? null;
                                        $status = $row['subscription_status'] ?? null;
                                    @endphp

                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $row['tenant_id'] ?? $tenant->getKey() }}</div>
                                            @if(!empty($row['domains_count']))
                                                <small class="text-muted">{{ __('admin.domains_count_short', ['count' => $row['domains_count']]) }}</small>
                                            @endif
                                        </td>

                                        <td>
                                            <div>{{ $row['company_name'] ?: '-' }}</div>
                                        </td>

                                        <td>
                                            @if(!empty($row['primary_domain']))
                                                <div>{{ $row['primary_domain'] }}</div>
                                                @if(!empty($row['open_url']))
                                                    <a href="{{ $row['open_url'] }}" target="_blank" class="small text-primary">{{ __('admin.open_tenant') }}</a>
                                                @endif
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>

                                        <td>{{ $row['owner_email'] ?: '-' }}</td>

                                        <td>{{ $row['plan_name'] ?: '-' }}</td>

                                        <td>
                                            <span class="badge {{ $statusBadgeClass($status) }}">
                                                {{ $status ? strtoupper(str_replace('_', ' ', $status)) : __('admin.no_subscription') }}
                                            </span>
                                        </td>

                                        <td>
                                            <div class="d-flex flex-column gap-1">
                                                <span class="badge {{ $gatewayBadgeClass($row['gateway'] ?? null) }}">
                                                    {{ !empty($row['gateway']) ? strtoupper($row['gateway']) : __('admin.local') }}
                                                </span>
                                                @if(!empty($row['is_stripe_linked']))
                                                    <small class="text-muted">{{ __('admin.stripe_linked') }}</small>
                                                @endif
                                            </div>
                                        </td>

                                        <td>{{ $row['billing_period'] ? strtoupper($row['billing_period']) : '-' }}</td>

                                        <td>
                                            @if(!empty($row['created_at']))
                                                {{ \Illuminate\Support\Carbon::parse($row['created_at'])->format('Y-m-d H:i') }}
                                            @else
                                                -
                                            @endif
                                        </td>

                                        <td class="text-end">
                                            <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                                <a href="{{ $row['show_url'] }}" class="btn btn-sm btn-primary">{{ __('admin.view') }}</a>

                                                @if(!empty($row['subscription_show_url']))
                                                    <a href="{{ $row['subscription_show_url'] }}" class="btn btn-sm btn-light">{{ __('admin.open_subscription') }}</a>
                                                @endif

                                                @if(!empty($row['admin_login_url']))
                                                    <form method="POST" action="{{ route('admin.tenants.impersonate', $row['tenant_id']) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-warning">{{ __('admin.impersonate_tenant_admin') }}</button>
                                                    </form>
                                                @endif

                                                @if(($row['subscription_status'] ?? null) === 'suspended')
                                                    <form method="POST" action="{{ route('admin.tenants.activate', $row['tenant_id']) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success">{{ __('admin.activate') }}</button>
                                                    </form>
                                                @elseif(!empty($row['subscription_status']) && !in_array($row['subscription_status'], ['cancelled', 'expired'], true))
                                                    <form method="POST" action="{{ route('admin.tenants.suspend', $row['tenant_id']) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('admin.suspend') }}</button>
                                                    </form>
                                                @endif

                                                @if(!empty($row['open_url']))
                                                    <a href="{{ $row['open_url'] }}" target="_blank" class="btn btn-sm btn-outline-secondary">{{ __('admin.open') }}</a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            {{ $tenants->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
