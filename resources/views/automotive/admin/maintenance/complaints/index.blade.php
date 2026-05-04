@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper"><div class="content content-two">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div><h4 class="mb-1">{{ __('maintenance.complaints') }}</h4><p class="mb-0 text-muted">{{ __('maintenance.complaints_subtitle') }}</p></div>
            <a href="{{ route('automotive.admin.maintenance.index') }}" class="btn btn-outline-light">{{ __('tenant.back') }}</a>
        </div>

        <div class="row">
            <div class="col-xl-4 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.create_complaint') }}</h5></div><div class="card-body">
                <form method="POST" action="{{ route('automotive.admin.maintenance.complaints.store') }}">
                    @csrf
                    <div class="mb-3"><label class="form-label">{{ __('tenant.branch') }}</label><select name="branch_id" class="form-select"><option value="">{{ __('maintenance.none') }}</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.work_order') }}</label><select name="work_order_id" class="form-select"><option value="">{{ __('maintenance.none') }}</option>@foreach($workOrders as $workOrder)<option value="{{ $workOrder->id }}">{{ $workOrder->work_order_number }} · {{ $workOrder->customer?->name }}</option>@endforeach</select></div>
                    <div class="row"><div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.source') }}</label><select name="source" class="form-select">@foreach(['in_branch','phone','whatsapp','email','portal','follow_up'] as $source)<option value="{{ $source }}">{{ __('maintenance.complaint_sources.' . $source) }}</option>@endforeach</select></div><div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.severity') }}</label><select name="severity" class="form-select">@foreach(['low','medium','high','urgent'] as $severity)<option value="{{ $severity }}">{{ __('maintenance.' . $severity) }}</option>@endforeach</select></div></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.assignee') }}</label><select name="assigned_to" class="form-select"><option value="">{{ __('maintenance.unassigned') }}</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.customer_visible_notes') }}</label><textarea name="customer_visible_note" class="form-control" rows="3"></textarea></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.internal_notes') }}</label><textarea name="internal_note" class="form-control" rows="3"></textarea></div>
                    <button type="submit" class="btn btn-primary">{{ __('maintenance.create_complaint') }}</button>
                </form>
            </div></div></div>
            <div class="col-xl-8 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.complaint_records') }}</h5></div><div class="card-body">
                @forelse($complaints as $complaint)
                    <div class="border-bottom pb-3 mb-3">
                        <div class="d-flex justify-content-between gap-2"><div><h6 class="mb-1">{{ $complaint->complaint_number }}</h6><div class="text-muted small">{{ $complaint->customer?->name }} · {{ $complaint->vehicle?->plate_number }} · {{ __('maintenance.complaint_sources.' . $complaint->source) }} · {{ $complaint->assignee?->name ?: __('maintenance.unassigned') }}</div><div class="small mt-1">{{ $complaint->customer_visible_note }}</div></div><span class="badge bg-primary">{{ strtoupper(str_replace('_', ' ', $complaint->status)) }}</span></div>
                        @if($complaint->status !== 'resolved')
                            <form method="POST" action="{{ route('automotive.admin.maintenance.complaints.resolve', $complaint) }}" class="row g-2 mt-2">
                                @csrf
                                <div class="col-md-9"><input type="text" name="resolution" class="form-control form-control-sm" placeholder="{{ __('maintenance.resolution') }}" required></div>
                                <div class="col-md-3"><button type="submit" class="btn btn-sm btn-success w-100">{{ __('maintenance.resolve') }}</button></div>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-muted mb-0">{{ __('maintenance.no_complaints') }}</p>
                @endforelse
            </div></div></div>
        </div>
    </div></div>
@endsection
