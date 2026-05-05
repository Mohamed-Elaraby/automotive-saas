@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="mb-1">{{ __('maintenance.appointments.title') }}</h4>
                    <p class="mb-0 text-muted">{{ __('maintenance.appointments.subtitle') }}</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('automotive.admin.maintenance.check-ins.create') }}" class="btn btn-outline-light">
                        <i class="isax isax-login me-1"></i>{{ __('maintenance.new_check_in') }}
                    </a>
                    <a href="{{ route('automotive.admin.maintenance.index') }}" class="btn btn-outline-light">{{ __('tenant.back') }}</a>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">{{ __('maintenance.appointments.today') }}</div><h4 class="mb-0">{{ $dashboard['today_count'] }}</h4></div></div></div>
                <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">{{ __('maintenance.appointments.scheduled') }}</div><h4 class="mb-0">{{ $dashboard['scheduled_count'] }}</h4></div></div></div>
                <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">{{ __('maintenance.appointments.arrived') }}</div><h4 class="mb-0">{{ $dashboard['arrived_count'] }}</h4></div></div></div>
                <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">{{ __('maintenance.appointments.converted') }}</div><h4 class="mb-0">{{ $dashboard['converted_count'] }}</h4></div></div></div>
            </div>

            <div class="row">
                <div class="col-xl-4 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.appointments.create') }}</h5></div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('automotive.admin.maintenance.appointments.store') }}">
                                @csrf
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">{{ __('maintenance.appointments.type') }}</label>
                                        <select name="type" class="form-select">
                                            <option value="appointment">{{ __('maintenance.appointments.appointment') }}</option>
                                            <option value="walk_in">{{ __('maintenance.appointments.walk_in') }}</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">{{ __('maintenance.priority') }}</label>
                                        <select name="priority" class="form-select">
                                            @foreach(['low','normal','high','urgent'] as $priority)
                                                <option value="{{ $priority }}">{{ __('maintenance.priorities.'.$priority) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('tenant.branch') }}</label>
                                    <select name="branch_id" class="form-select" required>
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}">{{ $branch->name }} ({{ $branch->code }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('maintenance.appointments.scheduled_at') }}</label>
                                    <input type="datetime-local" name="scheduled_at" class="form-control" value="{{ now()->format('Y-m-d\\TH:i') }}">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('maintenance.appointments.source') }}</label>
                                    <select name="source" class="form-select">
                                        @foreach(['in_branch','phone','whatsapp','email','portal','other'] as $source)
                                            <option value="{{ $source }}">{{ __('maintenance.appointments.sources.'.$source) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('maintenance.existing_customer') }}</label>
                                    <select name="customer_id" class="form-select">
                                        <option value="">{{ __('maintenance.create_new_customer') }}</option>
                                        @foreach($customers as $customer)
                                            <option value="{{ $customer->id }}">{{ $customer->name }}{{ $customer->phone ? ' · '.$customer->phone : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3"><label class="form-label">{{ __('tenant.customer_name') }}</label><input name="customer_name" class="form-control"></div>
                                    <div class="col-md-6 mb-3"><label class="form-label">{{ __('tenant.phone') }}</label><input name="customer_phone" class="form-control"></div>
                                </div>
                                <div class="mb-3"><label class="form-label">{{ __('tenant.email') }}</label><input type="email" name="customer_email" class="form-control"></div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('maintenance.existing_vehicle') }}</label>
                                    <select name="vehicle_id" class="form-select">
                                        <option value="">{{ __('maintenance.create_new_vehicle') }}</option>
                                        @foreach($vehicles as $vehicle)
                                            <option value="{{ $vehicle->id }}">{{ $vehicle->make }} {{ $vehicle->model }}{{ $vehicle->plate_number ? ' · '.$vehicle->plate_number : '' }}{{ $vehicle->customer ? ' · '.$vehicle->customer->name : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3"><label class="form-label">{{ __('tenant.make') }}</label><input name="vehicle_make" class="form-control"></div>
                                    <div class="col-md-6 mb-3"><label class="form-label">{{ __('tenant.model') }}</label><input name="vehicle_model" class="form-control"></div>
                                    <div class="col-md-6 mb-3"><label class="form-label">{{ __('tenant.year') }}</label><input name="vehicle_year" class="form-control"></div>
                                    <div class="col-md-6 mb-3"><label class="form-label">{{ __('tenant.plate_number') }}</label><input name="plate_number" class="form-control"></div>
                                </div>
                                <div class="mb-3"><label class="form-label">{{ __('maintenance.vin_number') }}</label><input name="vin_number" class="form-control text-uppercase"></div>
                                <div class="mb-3"><label class="form-label">{{ __('maintenance.appointments.service_type') }}</label><input name="service_type" class="form-control"></div>
                                <div class="mb-3"><label class="form-label">{{ __('maintenance.customer_complaint') }}</label><textarea name="customer_complaint" rows="3" class="form-control"></textarea></div>
                                <button type="submit" class="btn btn-primary w-100">{{ __('maintenance.appointments.save') }}</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-xl-8 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h5 class="card-title mb-0">{{ __('maintenance.appointments.day_schedule') }}</h5>
                            <form method="GET" class="d-flex gap-2">
                                <input type="date" name="date" value="{{ $selectedDate }}" class="form-control form-control-sm">
                                <button class="btn btn-sm btn-outline-light">{{ __('tenant.view') }}</button>
                            </form>
                        </div>
                        <div class="card-body">
                            @forelse($dayAppointments as $appointment)
                                <div class="border rounded p-3 mb-3">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                        <div>
                                            <h6 class="mb-1">{{ $appointment->appointment_number }} · {{ optional($appointment->scheduled_at)->format('H:i') }}</h6>
                                            <div class="text-muted small">{{ $appointment->customer?->name ?: $appointment->customer_name }} · {{ $appointment->vehicle?->make ?: $appointment->vehicle_make }} {{ $appointment->vehicle?->model ?: $appointment->vehicle_model }}{{ ($appointment->vehicle?->plate_number ?: $appointment->plate_number) ? ' · '.($appointment->vehicle?->plate_number ?: $appointment->plate_number) : '' }}</div>
                                            <div class="small">{{ $appointment->customer_complaint ?: $appointment->service_type }}</div>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-light text-dark">{{ strtoupper(str_replace('_', ' ', $appointment->status)) }}</span>
                                            <div class="mt-2 d-flex gap-1 justify-content-end flex-wrap">
                                                @if(in_array($appointment->status, ['scheduled', 'confirmed'], true))
                                                    <form method="POST" action="{{ route('automotive.admin.maintenance.appointments.arrived', $appointment) }}">@csrf<button class="btn btn-sm btn-outline-light">{{ __('maintenance.appointments.mark_arrived') }}</button></form>
                                                @endif
                                                @if(! $appointment->check_in_id && ! in_array($appointment->status, ['cancelled'], true))
                                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#convert-{{ $appointment->id }}">{{ __('maintenance.appointments.convert_to_check_in') }}</button>
                                                @elseif($appointment->checkIn)
                                                    <a href="{{ route('automotive.admin.maintenance.check-ins.show', $appointment->checkIn) }}" class="btn btn-sm btn-outline-light">{{ __('maintenance.open_check_in') }}</a>
                                                @endif
                                                @if(! in_array($appointment->status, ['converted', 'cancelled'], true))
                                                    <form method="POST" action="{{ route('automotive.admin.maintenance.appointments.cancel', $appointment) }}">@csrf<button class="btn btn-sm btn-outline-danger">{{ __('tenant.cancel') }}</button></form>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="collapse mt-3" id="convert-{{ $appointment->id }}">
                                        <form method="POST" action="{{ route('automotive.admin.maintenance.appointments.convert', $appointment) }}" class="border-top pt-3">
                                            @csrf
                                            <div class="row">
                                                <div class="col-md-3 mb-2"><input type="number" name="odometer" class="form-control" placeholder="{{ __('maintenance.odometer') }}"></div>
                                                <div class="col-md-3 mb-2"><input type="number" min="0" max="100" name="fuel_level" class="form-control" placeholder="{{ __('maintenance.fuel_level') }}"></div>
                                                <div class="col-md-3 mb-2"><input type="datetime-local" name="expected_delivery_at" class="form-control"></div>
                                                <div class="col-md-3 mb-2">
                                                    <select name="priority" class="form-select">
                                                        @foreach(['low','normal','high','urgent'] as $priority)
                                                            <option value="{{ $priority }}" @selected($appointment->priority === $priority)>{{ __('maintenance.priorities.'.$priority) }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-12 mb-2"><textarea name="customer_complaint" class="form-control" rows="2" placeholder="{{ __('maintenance.customer_complaint') }}">{{ $appointment->customer_complaint }}</textarea></div>
                                            </div>
                                            <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="create_work_order" value="1" id="wo-{{ $appointment->id }}" checked><label class="form-check-label" for="wo-{{ $appointment->id }}">{{ __('maintenance.create_work_order_from_check_in') }}</label></div>
                                            <button class="btn btn-primary">{{ __('maintenance.appointments.create_check_in') }}</button>
                                        </form>
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted mb-0">{{ __('maintenance.appointments.no_appointments') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.appointments.recent') }}</h5></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-nowrap">
                            <thead><tr><th>{{ __('maintenance.document_number') }}</th><th>{{ __('maintenance.customer_vehicle') }}</th><th>{{ __('maintenance.appointments.scheduled_at') }}</th><th>{{ __('maintenance.status') }}</th><th>{{ __('maintenance.branch') }}</th></tr></thead>
                            <tbody>
                            @forelse($appointments as $appointment)
                                <tr>
                                    <td><strong>{{ $appointment->appointment_number }}</strong><div class="text-muted small">{{ strtoupper($appointment->type) }} · {{ strtoupper($appointment->source) }}</div></td>
                                    <td>{{ $appointment->customer?->name ?: $appointment->customer_name }}<div class="text-muted small">{{ $appointment->vehicle?->make ?: $appointment->vehicle_make }} {{ $appointment->vehicle?->model ?: $appointment->vehicle_model }}</div></td>
                                    <td>{{ optional($appointment->scheduled_at)->format('Y-m-d H:i') ?: __('maintenance.none') }}</td>
                                    <td><span class="badge bg-light text-dark">{{ strtoupper(str_replace('_', ' ', $appointment->status)) }}</span></td>
                                    <td>{{ $appointment->branch?->name }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted">{{ __('maintenance.appointments.no_appointments') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
