@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="mb-1">{{ __('maintenance.settings.title') }}</h4>
                    <p class="mb-0 text-muted">{{ __('maintenance.settings.subtitle') }}</p>
                </div>
                <a href="{{ route('automotive.admin.maintenance.index') }}" class="btn btn-outline-light">
                    <i class="isax isax-arrow-left me-1"></i>{{ __('tenant.back') }}
                </a>
            </div>

            <div class="row">
                <div class="col-xl-8 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.settings.operational_settings') }}</h5></div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('automotive.admin.maintenance.settings.update') }}">
                                @csrf
                                <div class="row">
                                    @foreach($settings as $group => $items)
                                        <div class="col-lg-6 mb-4">
                                            <h6 class="mb-3">{{ __('maintenance.settings.groups.' . $group) }}</h6>
                                            @foreach($items as $item)
                                                @php($key = $item['key'])
                                                @php($value = $item['value'])
                                                <div class="mb-3">
                                                    <label class="form-label">{{ __('maintenance.settings.keys.' . str_replace('.', '_', $key)) }}</label>
                                                    @if(is_bool($value))
                                                        <input type="hidden" name="settings[{{ $key }}]" value="0">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" name="settings[{{ $key }}]" value="1" @checked($value)>
                                                            <span class="form-check-label">{{ $value ? __('maintenance.enabled') : __('maintenance.disabled') }}</span>
                                                        </div>
                                                    @elseif(is_numeric($value))
                                                        <input type="number" step="0.01" name="settings[{{ $key }}]" value="{{ $value }}" class="form-control">
                                                    @else
                                                        <input type="text" name="settings[{{ $key }}]" value="{{ $value }}" class="form-control">
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                                <button class="btn btn-primary" type="submit">{{ __('maintenance.settings.save_settings') }}</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.settings.approval_readiness') }}</h5></div>
                        <div class="card-body">
                            @forelse($approvalRequests as $approvalRequest)
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="d-flex justify-content-between">
                                        <strong>{{ $approvalRequest->approval_type }}</strong>
                                        <span class="badge bg-light text-dark">{{ strtoupper($approvalRequest->status) }}</span>
                                    </div>
                                    <div class="text-muted small">{{ $approvalRequest->requester?->name }} · {{ optional($approvalRequest->requested_at)->format('Y-m-d H:i') }}</div>
                                </div>
                            @empty
                                <p class="text-muted mb-0">{{ __('maintenance.settings.no_approval_requests') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-7 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.settings.user_permissions') }}</h5></div>
                        <div class="card-body">
                            @foreach($users as $user)
                                <form method="POST" action="{{ route('automotive.admin.maintenance.settings.users.permissions', $user) }}" class="border-bottom pb-3 mb-3">
                                    @csrf
                                    <div class="row g-2 align-items-start">
                                        <div class="col-md-3">
                                            <strong>{{ $user->name }}</strong>
                                            <div class="text-muted small">{{ $user->email }}</div>
                                        </div>
                                        <div class="col-md-3">
                                            <select name="maintenance_role" class="form-select form-select-sm">
                                                <option value="">{{ __('maintenance.settings.legacy_full_access') }}</option>
                                                @foreach($roles as $role => $label)
                                                    <option value="{{ $role }}" @selected($user->maintenance_role === $role)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <select name="maintenance_permissions[]" class="form-select form-select-sm" multiple size="5">
                                                <option value="maintenance.*" @selected(in_array('maintenance.*', $user->maintenance_permissions ?? [], true))>{{ __('maintenance.settings.all_permissions') }}</option>
                                                @foreach($permissionDefinitions as $permission => $definition)
                                                    <option value="{{ $permission }}" @selected(in_array($permission, $user->maintenance_permissions ?? [], true))>{{ $definition['label'] }}</option>
                                                @endforeach
                                            </select>
                                            <div class="form-text">{{ __('maintenance.settings.empty_permissions_hint') }}</div>
                                        </div>
                                        <div class="col-md-2 text-end">
                                            <button class="btn btn-sm btn-outline-light" type="submit">{{ __('tenant.save') }}</button>
                                        </div>
                                    </div>
                                </form>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="col-xl-5 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.settings.audit_log') }}</h5></div>
                        <div class="card-body">
                            @forelse($auditEntries as $entry)
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="d-flex justify-content-between">
                                        <strong>{{ $entry->action }}</strong>
                                        <span class="text-muted small">{{ optional($entry->created_at)->format('Y-m-d H:i') }}</span>
                                    </div>
                                    <div class="text-muted small">{{ $entry->module_code }} · {{ $entry->user?->name ?: '-' }}</div>
                                </div>
                            @empty
                                <p class="text-muted mb-0">{{ __('maintenance.settings.no_audit_entries') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
