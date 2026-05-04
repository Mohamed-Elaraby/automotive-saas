@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper"><div class="content content-two">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div><h4 class="mb-1">{{ __('maintenance.quality_control') }}</h4><p class="mb-0 text-muted">{{ __('maintenance.quality_control_subtitle') }}</p></div>
            <a href="{{ route('automotive.admin.maintenance.board') }}" class="btn btn-outline-light">{{ __('maintenance.workshop_board') }}</a>
        </div>

        <div class="row">
            <div class="col-xl-4 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.start_qc') }}</h5></div><div class="card-body">
                <form method="POST" action="{{ route('automotive.admin.maintenance.qc.store') }}">
                    @csrf
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.work_order') }}</label><select name="work_order_id" class="form-select" required>@foreach($workOrders as $workOrder)<option value="{{ $workOrder->id }}">{{ $workOrder->work_order_number }} · {{ $workOrder->customer?->name }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label">{{ __('maintenance.qc_inspector') }}</label><select name="qc_inspector_id" class="form-select"><option value="">{{ __('maintenance.unassigned') }}</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div>
                    <button type="submit" class="btn btn-primary">{{ __('maintenance.start_qc') }}</button>
                </form>
            </div></div></div>
            <div class="col-xl-8 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.qc_records') }}</h5></div><div class="card-body">
                @forelse($qcRecords as $record)
                    <div class="border-bottom pb-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div><h6 class="mb-1">{{ $record->qc_number }} · {{ $record->workOrder?->work_order_number }}</h6><div class="text-muted small">{{ $record->workOrder?->vehicle?->plate_number }} · {{ $record->workOrder?->customer?->name }} · {{ $record->inspector?->name ?: __('maintenance.unassigned') }}</div></div>
                            <span class="badge bg-primary">{{ strtoupper(str_replace('_', ' ', $record->result ?: $record->status)) }}</span>
                        </div>
                        @if($record->status !== 'completed')
                            <form method="POST" action="{{ route('automotive.admin.maintenance.qc.complete', $record) }}" class="mt-3">
                                @csrf
                                @foreach($record->items as $item)
                                    <div class="row g-2 align-items-center mb-2">
                                        <div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="items[{{ $item->id }}][passed]" value="1" @checked($item->passed)><label class="form-check-label">{{ $item->label }}</label></div></div>
                                        <div class="col-md-8"><input type="text" name="items[{{ $item->id }}][note]" class="form-control" value="{{ $item->note }}" placeholder="{{ __('maintenance.note') }}"></div>
                                    </div>
                                @endforeach
                                <div class="row g-2 mt-2"><div class="col-md-4"><select name="result" class="form-select"><option value="passed">{{ __('maintenance.qc_results.passed') }}</option><option value="failed">{{ __('maintenance.qc_results.failed') }}</option><option value="rework_required">{{ __('maintenance.qc_results.rework_required') }}</option></select></div><div class="col-md-8"><input type="text" name="final_notes" class="form-control" placeholder="{{ __('maintenance.final_notes') }}"></div></div>
                                <button type="submit" class="btn btn-success btn-sm mt-2">{{ __('maintenance.complete_qc') }}</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-muted mb-0">{{ __('maintenance.no_qc') }}</p>
                @endforelse
            </div></div></div>
        </div>
    </div></div>
@endsection
