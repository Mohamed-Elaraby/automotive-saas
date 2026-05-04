@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="mb-1">{{ $checkIn->check_in_number }}</h4>
                    <p class="mb-0 text-muted">{{ $checkIn->customer?->name }} · {{ $checkIn->vehicle?->make }} {{ $checkIn->vehicle?->model }}</p>
                </div>
                <a href="{{ route('automotive.admin.maintenance.check-ins.index') }}" class="btn btn-outline-light">{{ __('tenant.back') }}</a>
            </div>

            <div class="row">
                <div class="col-xl-8 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.check_in_overview') }}</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4"><div class="text-muted small">{{ __('tenant.branch') }}</div><div>{{ $checkIn->branch?->name }}</div></div>
                                <div class="col-md-4"><div class="text-muted small">{{ __('maintenance.odometer') }}</div><div>{{ $checkIn->odometer ?: '—' }}</div></div>
                                <div class="col-md-4"><div class="text-muted small">{{ __('maintenance.fuel_level') }}</div><div>{{ $checkIn->fuel_level !== null ? $checkIn->fuel_level.'%' : '—' }}</div></div>
                                <div class="col-md-4"><div class="text-muted small">{{ __('maintenance.status') }}</div><span class="badge bg-success">{{ strtoupper(str_replace('_', ' ', $checkIn->status)) }}</span></div>
                                <div class="col-md-4"><div class="text-muted small">{{ __('maintenance.expected_delivery_at') }}</div><div>{{ optional($checkIn->expected_delivery_at)->format('Y-m-d H:i') ?: '—' }}</div></div>
                                <div class="col-md-4"><div class="text-muted small">{{ __('maintenance.work_order') }}</div><div>{{ $checkIn->workOrder?->work_order_number ?: '—' }}</div></div>
                                <div class="col-12"><div class="text-muted small">{{ __('maintenance.customer_complaint') }}</div><div>{{ $checkIn->customer_complaint ?: '—' }}</div></div>
                                <div class="col-12"><div class="text-muted small">{{ __('maintenance.existing_damage_notes') }}</div><div>{{ $checkIn->existing_damage_notes ?: '—' }}</div></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.vin_verification') }}</h5></div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('automotive.admin.maintenance.check-ins.verify-vin', $checkIn) }}">
                                @csrf
                                <div class="mb-3"><label class="form-label">{{ __('maintenance.vin_number') }}</label><input type="text" name="vin_number" class="form-control text-uppercase" value="{{ old('vin_number', $checkIn->vin_number ?: $checkIn->vehicle?->vin) }}" required></div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('maintenance.method') }}</label>
                                    <select name="vin_verification_method" class="form-select">
                                        <option value="manual">{{ __('maintenance.manual') }}</option>
                                        <option value="ocr">{{ __('maintenance.ocr') }}</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary">{{ __('maintenance.confirm_vin') }}</button>
                            </form>
                            @if($checkIn->vin_verified_at)
                                <div class="alert alert-success mt-3 mb-0">{{ __('maintenance.vin_verified_at', ['date' => $checkIn->vin_verified_at->format('Y-m-d H:i')]) }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-5 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.photo_capture') }}</h5></div>
                        <div class="card-body">
                            <form id="attachmentForm" method="POST" action="{{ route('automotive.admin.maintenance.attachments.store') }}" enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="attachable_type" value="check_in">
                                <input type="hidden" name="attachable_id" value="{{ $checkIn->id }}">
                                <input type="hidden" name="branch_id" value="{{ $checkIn->branch_id }}">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('maintenance.photo_category') }}</label>
                                    <select name="category" class="form-select">
                                        @foreach(['front','rear','left_side','right_side','interior','dashboard','engine_bay','vin','existing_damage','other'] as $category)
                                            <option value="{{ $category }}">{{ __('maintenance.photo_categories.'.$category) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('maintenance.capture_or_upload') }}</label>
                                    <input type="file" name="photo" class="form-control" accept="image/*,application/pdf" capture="environment" required>
                                </div>
                                <div class="mb-3"><label class="form-label">{{ __('tenant.notes') }}</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
                                <button type="submit" class="btn btn-primary">{{ __('maintenance.upload_photo') }}</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-xl-7 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.attachments') }}</h5></div>
                        <div class="card-body">
                            @forelse($checkIn->attachments as $attachment)
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">{{ __('maintenance.photo_categories.'.$attachment->category) }}</h6>
                                            <div class="text-muted small">{{ $attachment->original_name }} · {{ number_format($attachment->size / 1024, 1) }} KB</div>
                                            <div class="text-muted small">{{ $attachment->uploader?->name ?: __('tenant.system_user') }} · {{ optional($attachment->captured_at)->format('Y-m-d H:i') }}</div>
                                        </div>
                                        <a href="{{ Storage::disk($attachment->file_disk)->url($attachment->file_path) }}" target="_blank" class="btn btn-sm btn-outline-light">{{ __('tenant.view') }}</a>
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted mb-0">{{ __('maintenance.no_attachments') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.condition_map') }}</h5></div>
                <div class="card-body">
                    @forelse($checkIn->conditionMaps as $map)
                        @forelse($map->items as $item)
                            <div class="border-bottom pb-2 mb-2">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="mb-1">{{ $item->label }}</h6>
                                        <div class="text-muted small">{{ strtoupper($item->note_type) }} · {{ strtoupper($item->severity) }}</div>
                                        <div>{{ $item->description }}</div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">{{ __('maintenance.no_condition_items') }}</p>
                        @endforelse
                    @empty
                        <p class="text-muted mb-0">{{ __('maintenance.no_condition_items') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
