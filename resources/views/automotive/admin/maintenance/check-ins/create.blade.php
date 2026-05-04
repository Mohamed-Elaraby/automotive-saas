@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="mb-1">{{ __('maintenance.new_check_in') }}</h4>
                    <p class="mb-0 text-muted">{{ __('maintenance.new_check_in_subtitle') }}</p>
                </div>
                <a href="{{ route('automotive.admin.maintenance.index') }}" class="btn btn-outline-light">{{ __('tenant.back') }}</a>
            </div>

            <form method="POST" action="{{ route('automotive.admin.maintenance.check-ins.store') }}">
                @csrf
                <div class="row">
                    <div class="col-xl-4 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.customer_vehicle') }}</h5></div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('tenant.branch') }}</label>
                                    <select name="branch_id" class="form-select" required>
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }} ({{ $branch->code }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('maintenance.existing_customer') }}</label>
                                    <select name="customer_id" class="form-select">
                                        <option value="">{{ __('maintenance.create_new_customer') }}</option>
                                        @foreach($customers as $customer)
                                            <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }}{{ $customer->phone ? ' · '.$customer->phone : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3"><label class="form-label">{{ __('tenant.customer_name') }}</label><input type="text" name="customer_name" class="form-control" value="{{ old('customer_name') }}"></div>
                                    <div class="col-md-6 mb-3"><label class="form-label">{{ __('tenant.phone') }}</label><input type="text" name="customer_phone" class="form-control" value="{{ old('customer_phone') }}"></div>
                                </div>
                                <div class="mb-3"><label class="form-label">{{ __('tenant.email') }}</label><input type="email" name="customer_email" class="form-control" value="{{ old('customer_email') }}"></div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('maintenance.existing_vehicle') }}</label>
                                    <select name="vehicle_id" class="form-select">
                                        <option value="">{{ __('maintenance.create_new_vehicle') }}</option>
                                        @foreach($vehicles as $vehicle)
                                            <option value="{{ $vehicle->id }}" @selected(old('vehicle_id') == $vehicle->id)>{{ $vehicle->make }} {{ $vehicle->model }}{{ $vehicle->plate_number ? ' · '.$vehicle->plate_number : '' }}{{ $vehicle->customer ? ' · '.$vehicle->customer->name : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3"><label class="form-label">{{ __('tenant.make') }}</label><input type="text" name="make" class="form-control" value="{{ old('make') }}"></div>
                                    <div class="col-md-6 mb-3"><label class="form-label">{{ __('tenant.model') }}</label><input type="text" name="model" class="form-control" value="{{ old('model') }}"></div>
                                    <div class="col-md-6 mb-3"><label class="form-label">{{ __('tenant.year') }}</label><input type="number" name="year" class="form-control" value="{{ old('year') }}"></div>
                                    <div class="col-md-6 mb-3"><label class="form-label">{{ __('tenant.plate_number') }}</label><input type="text" name="plate_number" class="form-control" value="{{ old('plate_number') }}"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.check_in_details') }}</h5></div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.odometer') }}</label><input type="number" name="odometer" class="form-control" value="{{ old('odometer') }}"></div>
                                    <div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.fuel_level') }}</label><input type="number" min="0" max="100" name="fuel_level" class="form-control" value="{{ old('fuel_level') }}"></div>
                                </div>
                                <div class="mb-3"><label class="form-label">{{ __('maintenance.vin_number') }}</label><input type="text" name="vin_number" class="form-control text-uppercase" value="{{ old('vin_number') }}"></div>
                                <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="vin_confirmed" value="1" id="vinConfirmed" @checked(old('vin_confirmed'))><label class="form-check-label" for="vinConfirmed">{{ __('maintenance.vin_confirmed_manual') }}</label></div>
                                <div class="mb-3"><label class="form-label">{{ __('maintenance.warning_lights') }}</label><input type="text" name="warning_lights" class="form-control" value="{{ old('warning_lights') }}" placeholder="{{ __('maintenance.comma_separated') }}"></div>
                                <div class="mb-3"><label class="form-label">{{ __('maintenance.personal_belongings') }}</label><input type="text" name="personal_belongings" class="form-control" value="{{ old('personal_belongings') }}" placeholder="{{ __('maintenance.comma_separated') }}"></div>
                                <div class="mb-3"><label class="form-label">{{ __('maintenance.customer_complaint') }}</label><textarea name="customer_complaint" class="form-control" rows="3">{{ old('customer_complaint') }}</textarea></div>
                                <div class="mb-3"><label class="form-label">{{ __('maintenance.existing_damage_notes') }}</label><textarea name="existing_damage_notes" class="form-control" rows="3">{{ old('existing_damage_notes') }}</textarea></div>
                                <div class="mb-3"><label class="form-label">{{ __('maintenance.expected_delivery_at') }}</label><input type="datetime-local" name="expected_delivery_at" class="form-control" value="{{ old('expected_delivery_at') }}"></div>
                                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="create_work_order" value="1" id="createWorkOrder" checked><label class="form-check-label" for="createWorkOrder">{{ __('maintenance.create_work_order_from_check_in') }}</label></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.condition_map') }}</h5></div>
                            <div class="card-body">
                                @for($i = 0; $i < 3; $i++)
                                    <div class="border rounded p-3 mb-3">
                                        <div class="mb-2">
                                            <label class="form-label">{{ __('maintenance.vehicle_area') }}</label>
                                            <select name="condition_items[{{ $i }}][vehicle_area_code]" class="form-select">
                                                <option value="">{{ __('maintenance.select_area') }}</option>
                                                @foreach($vehicleAreas as $code => $label)
                                                    <option value="{{ $code }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <input type="hidden" name="condition_items[{{ $i }}][label]" value="">
                                        <div class="row">
                                            <div class="col-md-6 mb-2">
                                                <select name="condition_items[{{ $i }}][note_type]" class="form-select">
                                                    <option value="existing_damage">{{ __('maintenance.existing_damage') }}</option>
                                                    <option value="complaint">{{ __('maintenance.complaint') }}</option>
                                                    <option value="inspection_note">{{ __('maintenance.inspection_note') }}</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <select name="condition_items[{{ $i }}][severity]" class="form-select">
                                                    <option value="low">{{ __('maintenance.low') }}</option>
                                                    <option value="medium">{{ __('maintenance.medium') }}</option>
                                                    <option value="high">{{ __('maintenance.high') }}</option>
                                                    <option value="urgent">{{ __('maintenance.urgent') }}</option>
                                                </select>
                                            </div>
                                        </div>
                                        <textarea name="condition_items[{{ $i }}][description]" class="form-control" rows="2" placeholder="{{ __('maintenance.condition_description') }}"></textarea>
                                    </div>
                                @endfor
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body d-flex justify-content-end gap-2">
                        <a href="{{ route('automotive.admin.maintenance.index') }}" class="btn btn-outline-light">{{ __('tenant.cancel') }}</a>
                        <button type="submit" class="btn btn-primary">{{ __('maintenance.save_check_in') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
