@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper"><div class="content content-two">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div><h4 class="mb-1">{{ __('maintenance.fleet.title') }}</h4><p class="mb-0 text-muted">{{ __('maintenance.fleet.subtitle') }}</p></div>
            <div class="d-flex gap-2">
                <a href="{{ route('automotive.admin.maintenance.fleet.export') }}" class="btn btn-outline-light">{{ __('maintenance.fleet.export') }}</a>
                <a href="{{ route('automotive.admin.maintenance.index') }}" class="btn btn-outline-light">{{ __('tenant.back') }}</a>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-4 d-flex">
                <div class="card flex-fill">
                    <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.fleet.create') }}</h5></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('automotive.admin.maintenance.fleet.store') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">{{ __('tenant.name') }}</label>
                                <select name="customer_id" class="form-select" required>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}">{{ $customer->name }} · {{ strtoupper($customer->customer_type ?? 'customer') }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.status') }}</label><select name="status" class="form-select"><option value="active">Active</option><option value="on_hold">On hold</option><option value="suspended">Suspended</option><option value="expired">Expired</option></select></div>
                                <div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.fleet.contract_type') }}</label><select name="contract_type" class="form-select"><option value="standard">Standard</option><option value="credit">Credit</option><option value="government">Government</option><option value="monthly_billing">Monthly billing</option><option value="custom">Custom</option></select></div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.start_date') }}</label><input type="date" name="contract_starts_on" class="form-control"></div>
                                <div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.end_date') }}</label><input type="date" name="contract_ends_on" class="form-control"></div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.fleet.credit_limit') }}</label><input type="number" step="0.01" min="0" name="credit_limit" class="form-control"></div>
                                <div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.fleet.approval_limit') }}</label><input type="number" step="0.01" min="0" name="approval_limit" class="form-control"></div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.mileage_interval') }}</label><input type="number" min="0" name="default_mileage_interval" class="form-control"></div>
                                <div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.months_interval') }}</label><input type="number" min="0" name="default_months_interval" class="form-control"></div>
                            </div>
                            <div class="mb-3"><label class="form-label">{{ __('maintenance.fleet.billing_cycle_day') }}</label><input name="billing_cycle_day" class="form-control"></div>
                            <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="monthly_billing_enabled" value="1"><label class="form-check-label">{{ __('maintenance.fleet.monthly_billing') }}</label></div>
                            <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="approval_required" value="1"><label class="form-check-label">{{ __('maintenance.fleet.approval_required') }}</label></div>
                            <div class="mb-3"><label class="form-label">{{ __('maintenance.terms') }}</label><textarea name="terms" rows="2" class="form-control"></textarea></div>
                            <div class="mb-3"><label class="form-label">{{ __('maintenance.internal_notes') }}</label><textarea name="internal_notes" rows="2" class="form-control"></textarea></div>
                            <button type="submit" class="btn btn-primary w-100">{{ __('maintenance.fleet.save') }}</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-xl-8 d-flex">
                <div class="card flex-fill">
                    <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.fleet.accounts') }}</h5></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-nowrap">
                                <thead><tr><th>{{ __('maintenance.document_number') }}</th><th>{{ __('tenant.name') }}</th><th>{{ __('maintenance.status') }}</th><th>{{ __('maintenance.fleet.credit_limit') }}</th><th>{{ __('maintenance.fleet.monthly_billing') }}</th><th></th></tr></thead>
                                <tbody>
                                @forelse($accounts as $fleet)
                                    <tr>
                                        <td><strong>{{ $fleet->fleet_number }}</strong><div class="text-muted small">{{ $fleet->contract_type }}</div></td>
                                        <td>{{ $fleet->customer?->name }}</td>
                                        <td><span class="badge bg-light text-dark">{{ strtoupper(str_replace('_', ' ', $fleet->status)) }}</span></td>
                                        <td>{{ $fleet->credit_limit ? number_format((float) $fleet->credit_limit, 2) : '-' }}</td>
                                        <td>{{ $fleet->monthly_billing_enabled ? __('maintenance.enabled') : __('maintenance.disabled') }}</td>
                                        <td class="text-end"><a href="{{ route('automotive.admin.maintenance.fleet.show', $fleet) }}" class="btn btn-sm btn-outline-light">{{ __('tenant.view') }}</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-muted">{{ __('maintenance.fleet.no_accounts') }}</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div></div>
@endsection
