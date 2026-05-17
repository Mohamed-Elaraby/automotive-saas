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

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('automotive.admin.maintenance.check-ins.store') }}" id="checkInWizardForm">
                @csrf

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="maintenance-wizard-steps" role="tablist">
                            @foreach([
                                1 => __('maintenance.wizard.customer_vehicle'),
                                2 => __('maintenance.wizard.vin'),
                                3 => __('maintenance.wizard.arrival_state'),
                                4 => __('maintenance.wizard.condition_map'),
                                5 => __('maintenance.wizard.finish'),
                            ] as $step => $label)
                                <button type="button" class="maintenance-wizard-step {{ $step === 1 ? 'active' : '' }}" data-wizard-go="{{ $step }}">
                                    <span>{{ $step }}</span>
                                    <strong>{{ $label }}</strong>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="card maintenance-wizard-panel" data-wizard-panel="1">
                    <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.customer_vehicle') }}</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-4 mb-3">
                                <label class="form-label">{{ __('tenant.branch') }}</label>
                                <select name="branch_id" class="form-select" required>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }} ({{ $branch->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-4 mb-3">
                                <label class="form-label">{{ __('maintenance.customer_search') }}</label>
                                <input type="search" class="form-control" id="customerSearch" value="{{ old('customer_search') }}" placeholder="{{ __('maintenance.customer_search_placeholder') }}">
                            </div>
                            <div class="col-lg-4 mb-3">
                                <label class="form-label">{{ __('maintenance.vehicle_search') }}</label>
                                <input type="search" class="form-control" id="vehicleSearch" value="{{ old('vehicle_search') }}" placeholder="{{ __('maintenance.vehicle_search_placeholder') }}">
                            </div>
                            <div class="col-lg-6 mb-3">
                                <label class="form-label">{{ __('maintenance.existing_customer') }}</label>
                                <select name="customer_id" class="form-select" id="customerSelect">
                                    <option value="">{{ __('maintenance.create_new_customer') }}</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }}{{ $customer->phone ? ' · '.$customer->phone : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-6 mb-3">
                                <label class="form-label">{{ __('maintenance.existing_vehicle') }}</label>
                                <select name="vehicle_id" class="form-select" id="vehicleSelect">
                                    <option value="">{{ __('maintenance.create_new_vehicle') }}</option>
                                    @foreach($vehicles as $vehicle)
                                        <option value="{{ $vehicle->id }}" @selected(old('vehicle_id') == $vehicle->id)>{{ $vehicle->make }} {{ $vehicle->model }}{{ $vehicle->plate_number ? ' · '.$vehicle->plate_number : '' }}{{ $vehicle->vin ? ' · '.$vehicle->vin : '' }}{{ $vehicle->customer ? ' · '.$vehicle->customer->name : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-xl-6">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="mb-3">{{ __('maintenance.customer_details') }}</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3"><label class="form-label">{{ __('tenant.customer_name') }}</label><input type="text" name="customer_name" class="form-control" value="{{ old('customer_name') }}"></div>
                                        <div class="col-md-6 mb-3"><label class="form-label">{{ __('tenant.phone') }}</label><input type="text" name="customer_phone" class="form-control" value="{{ old('customer_phone') }}"></div>
                                        <div class="col-md-6 mb-3"><label class="form-label">{{ __('tenant.email') }}</label><input type="email" name="customer_email" class="form-control" value="{{ old('customer_email') }}"></div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">{{ __('maintenance.profiles.customer_type') }}</label>
                                            <select name="customer_type" class="form-select">
                                                @foreach(['individual','fleet','company','government'] as $type)
                                                    <option value="{{ $type }}" @selected(old('customer_type', 'individual') === $type)>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12 mb-0"><label class="form-label">{{ __('maintenance.profiles.company_name') }}</label><input type="text" name="company_name" class="form-control" value="{{ old('company_name') }}"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-6 mt-3 mt-xl-0">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="mb-3">{{ __('maintenance.vehicle_details') }}</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3"><label class="form-label">{{ __('tenant.make') }}</label><input type="text" name="make" class="form-control" value="{{ old('make') }}"></div>
                                        <div class="col-md-6 mb-3"><label class="form-label">{{ __('tenant.model') }}</label><input type="text" name="model" class="form-control" value="{{ old('model') }}"></div>
                                        <div class="col-md-4 mb-3"><label class="form-label">{{ __('tenant.year') }}</label><input type="number" name="year" class="form-control" value="{{ old('year') }}"></div>
                                        <div class="col-md-4 mb-3"><label class="form-label">{{ __('maintenance.profiles.trim') }}</label><input type="text" name="trim" class="form-control" value="{{ old('trim') }}"></div>
                                        <div class="col-md-4 mb-3"><label class="form-label">{{ __('maintenance.profiles.color') }}</label><input type="text" name="color" class="form-control" value="{{ old('color') }}"></div>
                                        <div class="col-md-4 mb-3"><label class="form-label">{{ __('tenant.plate_number') }}</label><input type="text" name="plate_number" class="form-control" value="{{ old('plate_number') }}"></div>
                                        <div class="col-md-4 mb-3"><label class="form-label">{{ __('maintenance.plate_source') }}</label><input type="text" name="plate_source" class="form-control" value="{{ old('plate_source') }}"></div>
                                        <div class="col-md-4 mb-0"><label class="form-label">{{ __('maintenance.plate_country') }}</label><input type="text" name="plate_country" class="form-control" value="{{ old('plate_country') }}"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card maintenance-wizard-panel d-none" data-wizard-panel="2">
                    <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.vin_verification') }}</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-xl-7">
                                <div class="border rounded p-3 mb-3">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                        <div>
                                            <h6 class="mb-1">Step 1 - Capture or enter VIN / chassis</h6>
                                            <p class="text-muted small mb-0">OCR is only a suggestion. The VIN must be reviewed and confirmed by the advisor before search.</p>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-primary" id="vinCaptureButton">
                                                <i class="isax isax-camera me-1"></i>Capture VIN Photo
                                            </button>
                                            <button type="button" class="btn btn-outline-light" id="vinUploadButton">
                                                <i class="isax isax-document-upload me-1"></i>Upload VIN Photo
                                            </button>
                                            <button type="button" class="btn btn-outline-light" id="vinManualButton">Manual VIN Entry</button>
                                        </div>
                                    </div>

                                    <input type="file" class="d-none" id="vinPhotoInput" accept="image/*" capture="environment">
                                    <div id="vinCameraWrap" class="d-none mb-3" data-vin-camera>
                                        <div class="vin-camera-box">
                                            <video id="vinCameraPreview" playsinline autoplay muted></video>
                                            <canvas id="vinCameraCanvas" class="d-none"></canvas>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2 mt-2">
                                            <button type="button" class="btn btn-success" id="vinCameraCaptureButton">
                                                <i class="isax isax-camera me-1"></i>Capture Photo
                                            </button>
                                            <button type="button" class="btn btn-outline-light" id="vinCameraCancelButton">Cancel Camera</button>
                                        </div>
                                    </div>
                                    <div id="vinImagePreviewWrap" class="d-none mb-3">
                                        <div class="vin-preview-box">
                                            <img src="" alt="VIN photo preview" id="vinImagePreview">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">VIN / Chassis Number</label>
                                        <input type="text" name="vin_number" class="form-control text-uppercase" value="{{ old('vin_number') }}" maxlength="40" id="vinInput" autocomplete="off">
                                        <div class="form-text">Compare this value with the physical VIN on the vehicle. Edit it manually if OCR is wrong.</div>
                                    </div>

                                    <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                                        <span class="badge bg-light text-dark border" id="vinOcrStatus">OCR not run</span>
                                        <span class="badge bg-light text-dark border d-none" id="vinConfidenceBadge"></span>
                                    </div>

                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="button" class="btn btn-success" id="vinConfirmButton">
                                            <i class="isax isax-tick-circle me-1"></i>Confirm VIN
                                        </button>
                                        <button type="button" class="btn btn-outline-light" id="vinRetakeButton">Retake Photo</button>
                                        <button type="button" class="btn btn-outline-danger" id="vinClearButton">Clear</button>
                                    </div>

                                    <div class="form-check form-switch mt-3 mb-0">
                                        <input class="form-check-input" type="checkbox" name="vin_confirmed" value="1" id="vinConfirmed" @checked(old('vin_confirmed'))>
                                        <label class="form-check-label" for="vinConfirmed">{{ __('maintenance.vin_confirmed_manual') }}</label>
                                    </div>
                                </div>

                                <div class="border rounded p-3">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                        <h6 class="mb-0">Step 3 - Tenant-wide vehicle lookup</h6>
                                        <span class="badge bg-light text-dark border" id="vinSearchStatus">Waiting for VIN confirmation</span>
                                    </div>
                                    <div id="vinSearchResult">
                                        <div class="text-muted small">Confirm the VIN to search existing vehicles across this tenant.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-5">
                                <div class="border rounded p-3 bg-light h-100">
                                    <div class="fw-semibold mb-2">{{ __('maintenance.vin_confirmation_checklist') }}</div>
                                    <div class="small text-muted mb-1">{{ __('maintenance.vin_check_17_chars') }}</div>
                                    <div class="small text-muted mb-1">{{ __('maintenance.vin_check_human_confirmation') }}</div>
                                    <div class="small text-muted">{{ __('maintenance.vin_check_ocr_confusion') }}</div>
                                    <hr>
                                    <div class="fw-semibold mb-2">Available actions after lookup</div>
                                    <div class="small text-muted mb-1">Use an existing vehicle for a new check-in.</div>
                                    <div class="small text-muted mb-1">Open vehicle profile/history.</div>
                                    <div class="small text-muted">Continue as a new vehicle when no match is found.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card maintenance-wizard-panel d-none" data-wizard-panel="3">
                    <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.check_in_details') }}</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3"><label class="form-label">{{ __('maintenance.odometer') }}</label><input type="number" name="odometer" class="form-control" value="{{ old('odometer') }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">{{ __('maintenance.fuel_level') }}</label><input type="number" min="0" max="100" name="fuel_level" class="form-control" value="{{ old('fuel_level') }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">{{ __('maintenance.profiles.fuel_type') }}</label><input type="text" name="fuel_type" class="form-control" value="{{ old('fuel_type') }}"></div>
                            <div class="col-md-3 mb-3"><label class="form-label">{{ __('maintenance.profiles.transmission') }}</label><input type="text" name="transmission" class="form-control" value="{{ old('transmission') }}"></div>
                            <div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.warning_lights') }}</label><input type="text" name="warning_lights" class="form-control" value="{{ old('warning_lights') }}" placeholder="{{ __('maintenance.comma_separated') }}"></div>
                            <div class="col-md-6 mb-3"><label class="form-label">{{ __('maintenance.personal_belongings') }}</label><input type="text" name="personal_belongings" class="form-control" value="{{ old('personal_belongings') }}" placeholder="{{ __('maintenance.comma_separated') }}"></div>
                            <div class="col-lg-6 mb-3"><label class="form-label">{{ __('maintenance.customer_complaint') }}</label><textarea name="customer_complaint" class="form-control" rows="4">{{ old('customer_complaint') }}</textarea></div>
                            <div class="col-lg-6 mb-3"><label class="form-label">{{ __('maintenance.existing_damage_notes') }}</label><textarea name="existing_damage_notes" class="form-control" rows="4">{{ old('existing_damage_notes') }}</textarea></div>
                            <div class="col-lg-6 mb-3"><label class="form-label">{{ __('maintenance.customer_visible_notes') }}</label><textarea name="customer_visible_notes" class="form-control" rows="3">{{ old('customer_visible_notes') }}</textarea></div>
                            <div class="col-lg-6 mb-3"><label class="form-label">{{ __('maintenance.internal_notes') }}</label><textarea name="internal_notes" class="form-control" rows="3">{{ old('internal_notes') }}</textarea></div>
                            <div class="col-md-4 mb-0"><label class="form-label">{{ __('maintenance.expected_delivery_at') }}</label><input type="datetime-local" name="expected_delivery_at" class="form-control" value="{{ old('expected_delivery_at') }}"></div>
                        </div>
                    </div>
                </div>

                <div class="card maintenance-wizard-panel d-none" data-wizard-panel="4">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h5 class="card-title mb-0">{{ __('maintenance.condition_map') }}</h5>
                        <button type="button" class="btn btn-sm btn-outline-light" id="clearConditionItems">{{ __('maintenance.clear_condition_items') }}</button>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-xl-7">
                                <div class="vehicle-map-wrap">
                                    <svg viewBox="0 0 720 360" class="vehicle-map" role="img" aria-label="{{ __('maintenance.condition_map') }}">
                                        <rect x="180" y="60" width="360" height="240" rx="80" class="vehicle-shell" data-area="roof"></rect>
                                        <rect x="300" y="20" width="120" height="55" rx="18" class="vehicle-glass" data-area="windshield"></rect>
                                        <rect x="300" y="285" width="120" height="55" rx="18" class="vehicle-glass" data-area="rear_glass"></rect>
                                        <rect x="270" y="95" width="180" height="80" rx="20" class="vehicle-panel" data-area="hood"></rect>
                                        <rect x="270" y="185" width="180" height="75" rx="20" class="vehicle-panel" data-area="trunk"></rect>
                                        <rect x="190" y="35" width="340" height="35" rx="18" class="vehicle-panel" data-area="front_bumper"></rect>
                                        <rect x="190" y="290" width="340" height="35" rx="18" class="vehicle-panel" data-area="rear_bumper"></rect>
                                        <rect x="170" y="90" width="75" height="75" rx="18" class="vehicle-panel" data-area="left_front_fender"></rect>
                                        <rect x="475" y="90" width="75" height="75" rx="18" class="vehicle-panel" data-area="right_front_fender"></rect>
                                        <rect x="170" y="170" width="75" height="65" rx="16" class="vehicle-panel" data-area="left_front_door"></rect>
                                        <rect x="475" y="170" width="75" height="65" rx="16" class="vehicle-panel" data-area="right_front_door"></rect>
                                        <rect x="170" y="238" width="75" height="55" rx="16" class="vehicle-panel" data-area="left_rear_door"></rect>
                                        <rect x="475" y="238" width="75" height="55" rx="16" class="vehicle-panel" data-area="right_rear_door"></rect>
                                        <rect x="112" y="228" width="70" height="62" rx="18" class="vehicle-panel" data-area="left_quarter_panel"></rect>
                                        <rect x="538" y="228" width="70" height="62" rx="18" class="vehicle-panel" data-area="right_quarter_panel"></rect>
                                        <rect x="140" y="150" width="34" height="20" rx="10" class="vehicle-panel" data-area="left_mirror"></rect>
                                        <rect x="546" y="150" width="34" height="20" rx="10" class="vehicle-panel" data-area="right_mirror"></rect>
                                        <circle cx="170" cy="88" r="30" class="vehicle-tire" data-area="front_left_tire"></circle>
                                        <circle cx="550" cy="88" r="30" class="vehicle-tire" data-area="front_right_tire"></circle>
                                        <circle cx="170" cy="285" r="30" class="vehicle-tire" data-area="rear_left_tire"></circle>
                                        <circle cx="550" cy="285" r="30" class="vehicle-tire" data-area="rear_right_tire"></circle>
                                        <rect x="310" y="135" width="100" height="80" rx="18" class="vehicle-glass" data-area="interior"></rect>
                                        <rect x="312" y="118" width="96" height="22" rx="10" class="vehicle-panel" data-area="dashboard"></rect>
                                        <rect x="298" y="80" width="124" height="26" rx="12" class="vehicle-panel" data-area="engine_bay"></rect>
                                    </svg>
                                </div>
                            </div>
                            <div class="col-xl-5 mt-3 mt-xl-0">
                                <div class="border rounded p-3">
                                    <input type="hidden" id="conditionAreaCode">
                                    <div class="mb-2">
                                        <label class="form-label">{{ __('maintenance.vehicle_area') }}</label>
                                        <select id="conditionAreaSelect" class="form-select">
                                            <option value="">{{ __('maintenance.select_area') }}</option>
                                            @foreach($vehicleAreas as $code => $label)
                                                <option value="{{ $code }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <select id="conditionNoteType" class="form-select">
                                                <option value="existing_damage">{{ __('maintenance.existing_damage') }}</option>
                                                <option value="complaint">{{ __('maintenance.complaint') }}</option>
                                                <option value="inspection_note">{{ __('maintenance.inspection_note') }}</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <select id="conditionSeverity" class="form-select">
                                                <option value="low">{{ __('maintenance.low') }}</option>
                                                <option value="medium">{{ __('maintenance.medium') }}</option>
                                                <option value="high">{{ __('maintenance.high') }}</option>
                                                <option value="urgent">{{ __('maintenance.urgent') }}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mb-2"><textarea id="conditionDescription" class="form-control" rows="3" placeholder="{{ __('maintenance.condition_description') }}"></textarea></div>
                                    <div class="mb-2"><textarea id="conditionCustomerNote" class="form-control" rows="2" placeholder="{{ __('maintenance.customer_visible_notes') }}"></textarea></div>
                                    <div class="mb-3"><textarea id="conditionInternalNote" class="form-control" rows="2" placeholder="{{ __('maintenance.internal_notes') }}"></textarea></div>
                                    <button type="button" class="btn btn-primary w-100" id="addConditionItem">{{ __('maintenance.add_condition_item') }}</button>
                                </div>
                                <div id="conditionItemsList" class="mt-3"></div>
                                <div id="conditionItemsInputs"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card maintenance-wizard-panel d-none" data-wizard-panel="5">
                    <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.finish_check_in') }}</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-6 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="mb-3">{{ __('maintenance.work_order') }}</h6>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" name="create_work_order" value="1" id="createWorkOrder" @checked(old('create_work_order', true))>
                                        <label class="form-check-label" for="createWorkOrder">{{ __('maintenance.create_work_order_from_check_in') }}</label>
                                    </div>
                                    <div class="mb-3"><label class="form-label">{{ __('maintenance.job_title') }}</label><input type="text" name="work_order_title" class="form-control" value="{{ old('work_order_title') }}"></div>
                                    <div class="mb-0">
                                        <label class="form-label">{{ __('maintenance.priority') }}</label>
                                        <select name="priority" class="form-select">
                                            @foreach(['low','normal','high','urgent'] as $priority)
                                                <option value="{{ $priority }}" @selected(old('priority', 'normal') === $priority)>{{ __('maintenance.priorities.'.$priority) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="mb-3">{{ __('maintenance.photo_capture') }}</h6>
                                    <div class="row g-2">
                                        @foreach(['front','rear','left_side','right_side','interior','dashboard','engine_bay','vin','existing_damage','other'] as $category)
                                            <div class="col-6">
                                                <span class="badge bg-light text-dark border w-100 text-start">{{ __('maintenance.photo_categories.'.$category) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body d-flex justify-content-between flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-light" id="wizardPrev" disabled>{{ __('tenant.back') }}</button>
                        <div class="d-flex gap-2">
                            <a href="{{ route('automotive.admin.maintenance.index') }}" class="btn btn-outline-light">{{ __('tenant.cancel') }}</a>
                            <button type="button" class="btn btn-primary" id="wizardNext">{{ __('maintenance.next_step') }}</button>
                            <button type="submit" class="btn btn-success d-none" id="wizardSubmit">{{ __('maintenance.save_and_continue_to_photos') }}</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .maintenance-wizard-steps { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .75rem; }
        .maintenance-wizard-step { border: 1px solid #e5e7eb; background: #fff; border-radius: 8px; padding: .8rem; display: flex; gap: .55rem; align-items: center; text-align: start; color: #475569; }
        .maintenance-wizard-step span { width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: #f1f5f9; color: #0f172a; font-weight: 700; flex: 0 0 28px; }
        .maintenance-wizard-step.active { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, .08); color: #0f172a; }
        .maintenance-wizard-step.active span { background: #2563eb; color: #fff; }
        .vehicle-map-wrap { border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; background: #f8fafc; min-height: 390px; display: flex; align-items: center; }
        .vehicle-map { width: 100%; min-height: 340px; }
        .vehicle-shell { fill: #e2e8f0; stroke: #64748b; stroke-width: 2; cursor: pointer; }
        .vehicle-panel { fill: #fff; stroke: #64748b; stroke-width: 2; cursor: pointer; transition: fill .15s, stroke .15s; }
        .vehicle-glass { fill: #dbeafe; stroke: #2563eb; stroke-width: 2; cursor: pointer; transition: fill .15s, stroke .15s; }
        .vehicle-tire { fill: #1f2937; stroke: #94a3b8; stroke-width: 4; cursor: pointer; transition: fill .15s, stroke .15s; }
        .vehicle-map [data-area]:hover,
        .vehicle-map [data-area].active { fill: #fde68a; stroke: #d97706; }
        .condition-pill { border: 1px solid #e5e7eb; border-radius: 8px; padding: .75rem; margin-bottom: .5rem; background: #fff; }
        .vin-camera-box { border: 1px solid #e5e7eb; border-radius: 8px; background: #0f172a; overflow: hidden; max-width: 520px; }
        .vin-camera-box video { display: block; width: 100%; max-height: 320px; object-fit: cover; background: #0f172a; }
        .vin-preview-box { border: 1px solid #e5e7eb; border-radius: 8px; background: #f8fafc; padding: .5rem; max-width: 420px; }
        .vin-preview-box img { display: block; width: 100%; max-height: 220px; object-fit: contain; border-radius: 6px; }
        .vin-result-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
        .vin-result-item { border: 1px solid #e5e7eb; border-radius: 8px; padding: .75rem; background: #fff; }
        .vin-result-label { color: #64748b; font-size: .75rem; margin-bottom: .25rem; }
        @media (max-width: 991.98px) { .maintenance-wizard-steps { grid-template-columns: 1fr; } }
        @media (max-width: 767.98px) { .vin-result-grid { grid-template-columns: 1fr; } }
    </style>
@endpush

@push('scripts')
    <script>
        (() => {
            const customerOptions = @json($customerOptions);
            const vehicleOptions = @json($vehicleOptions);
            const vehicleAreas = @json($vehicleAreas);
            const vinCaptureUrl = @json(route('automotive.admin.maintenance.check-ins.capture-vin-draft'));
            const vinSearchUrl = @json(route('automotive.admin.maintenance.vehicles.search-vin'));
            const csrfToken = @json(csrf_token());
            const panels = [...document.querySelectorAll('[data-wizard-panel]')];
            const stepButtons = [...document.querySelectorAll('[data-wizard-go]')];
            const prevButton = document.getElementById('wizardPrev');
            const nextButton = document.getElementById('wizardNext');
            const submitButton = document.getElementById('wizardSubmit');
            const customerSelect = document.getElementById('customerSelect');
            const vehicleSelect = document.getElementById('vehicleSelect');
            const customerSearch = document.getElementById('customerSearch');
            const vehicleSearch = document.getElementById('vehicleSearch');
            const conditionAreaSelect = document.getElementById('conditionAreaSelect');
            const conditionItemsList = document.getElementById('conditionItemsList');
            const conditionItemsInputs = document.getElementById('conditionItemsInputs');
            const vinPhotoInput = document.getElementById('vinPhotoInput');
            const vinInput = document.getElementById('vinInput');
            const vinConfirmed = document.getElementById('vinConfirmed');
            const vinOcrStatus = document.getElementById('vinOcrStatus');
            const vinConfidenceBadge = document.getElementById('vinConfidenceBadge');
            const vinSearchStatus = document.getElementById('vinSearchStatus');
            const vinSearchResult = document.getElementById('vinSearchResult');
            const vinImagePreview = document.getElementById('vinImagePreview');
            const vinImagePreviewWrap = document.getElementById('vinImagePreviewWrap');
            const vinCameraWrap = document.getElementById('vinCameraWrap');
            const vinCameraPreview = document.getElementById('vinCameraPreview');
            const vinCameraCanvas = document.getElementById('vinCameraCanvas');
            const vinCaptureButton = document.getElementById('vinCaptureButton');
            const vinUploadButton = document.getElementById('vinUploadButton');
            const vinCameraCaptureButton = document.getElementById('vinCameraCaptureButton');
            const vinCameraCancelButton = document.getElementById('vinCameraCancelButton');
            const conditions = [];
            let currentStep = 1;
            let vinCaptureInFlight = false;
            let vinSearchInFlight = false;
            let vinCameraStream = null;

            const escapeHtml = value => String(value || '').replace(/[&<>"']/g, character => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            }[character]));

            const setStep = step => {
                currentStep = Math.max(1, Math.min(5, step));
                panels.forEach(panel => panel.classList.toggle('d-none', Number(panel.dataset.wizardPanel) !== currentStep));
                stepButtons.forEach(button => button.classList.toggle('active', Number(button.dataset.wizardGo) === currentStep));
                prevButton.disabled = currentStep === 1;
                nextButton.classList.toggle('d-none', currentStep === 5);
                submitButton.classList.toggle('d-none', currentStep !== 5);
            };

            const normalizeVin = value => String(value || '').toUpperCase().replace(/[\s\-_.:\n\r\t]/g, '').replace(/[^A-Z0-9]/g, '');

            const fillField = (name, value, force = false) => {
                const input = document.querySelector(`[name="${name}"]`);
                if (input && (force || !input.value) && value !== null && value !== undefined) {
                    input.value = value;
                }
            };

            const rebuildSelect = (select, options, labelCallback, selectedId) => {
                const firstLabel = select.name === 'customer_id' ? @json(__('maintenance.create_new_customer')) : @json(__('maintenance.create_new_vehicle'));
                select.innerHTML = `<option value="">${firstLabel}</option>`;
                options.forEach(option => {
                    const element = document.createElement('option');
                    element.value = option.id;
                    element.textContent = labelCallback(option);
                    element.selected = String(selectedId || '') === String(option.id);
                    select.appendChild(element);
                });
            };

            const filterCustomers = () => {
                const term = customerSearch.value.trim().toLowerCase();
                const selectedId = customerSelect.value;
                const filtered = term ? customerOptions.filter(customer => customer.search.includes(term)) : customerOptions;
                rebuildSelect(customerSelect, filtered, customer => [customer.name, customer.phone, customer.email].filter(Boolean).join(' · '), selectedId);
            };

            const filterVehicles = () => {
                const term = vehicleSearch.value.trim().toLowerCase();
                const selectedId = vehicleSelect.value;
                const filtered = term ? vehicleOptions.filter(vehicle => vehicle.search.includes(term)) : vehicleOptions;
                rebuildSelect(vehicleSelect, filtered, vehicle => [vehicle.make + ' ' + vehicle.model, vehicle.plate_number, vehicle.vin, vehicle.customer_name].filter(Boolean).join(' · '), selectedId);
            };

            const selectCustomer = id => {
                const customer = customerOptions.find(item => String(item.id) === String(id));
                if (!customer) return;
                fillField('customer_name', customer.name);
                fillField('customer_phone', customer.phone);
                fillField('customer_email', customer.email);
                fillField('company_name', customer.company_name);
                const typeSelect = document.querySelector('[name="customer_type"]');
                if (typeSelect && customer.customer_type) typeSelect.value = customer.customer_type;
            };

            const selectVehicle = id => {
                const vehicle = vehicleOptions.find(item => String(item.id) === String(id));
                if (!vehicle) return;
                if (vehicle.customer_id) {
                    customerSelect.value = vehicle.customer_id;
                    selectCustomer(vehicle.customer_id);
                }
                fillField('make', vehicle.make);
                fillField('model', vehicle.model);
                fillField('year', vehicle.year);
                fillField('trim', vehicle.trim);
                fillField('color', vehicle.color);
                fillField('plate_number', vehicle.plate_number);
                fillField('plate_source', vehicle.plate_source);
                fillField('plate_country', vehicle.plate_country);
                fillField('vin_number', vehicle.vin);
                fillField('odometer', vehicle.odometer);
                fillField('fuel_type', vehicle.fuel_type);
                fillField('transmission', vehicle.transmission);
            };

            const setVinStatus = (message, type = 'light') => {
                vinOcrStatus.className = `badge bg-${type} ${type === 'light' ? 'text-dark border' : ''}`;
                vinOcrStatus.textContent = message;
            };

            const setSearchStatus = (message, type = 'light') => {
                vinSearchStatus.className = `badge bg-${type} ${type === 'light' ? 'text-dark border' : ''}`;
                vinSearchStatus.textContent = message;
            };

            const setVinCaptureBusy = busy => {
                vinCaptureButton.disabled = busy;
                vinUploadButton.disabled = busy;
                vinCameraCaptureButton.disabled = busy;
                document.getElementById('vinRetakeButton').disabled = busy;
            };

            const stopVinCamera = () => {
                if (vinCameraStream) {
                    vinCameraStream.getTracks().forEach(track => track.stop());
                    vinCameraStream = null;
                }

                vinCameraPreview.srcObject = null;
                vinCameraWrap.classList.add('d-none');
            };

            const openVinCamera = async () => {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    setVinStatus('Camera access is unavailable or blocked. Upload a VIN photo or enter manually.', 'warning');
                    return;
                }

                try {
                    stopVinCamera();
                    setVinStatus('Opening camera...', 'primary');
                    vinCameraStream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: { ideal: 'environment' },
                            width: { ideal: 1280 },
                            height: { ideal: 720 },
                        },
                        audio: false,
                    });
                    vinCameraPreview.srcObject = vinCameraStream;
                    vinCameraWrap.classList.remove('d-none');
                    await vinCameraPreview.play();
                    setVinStatus('Camera ready - align VIN and capture photo', 'success');
                } catch (error) {
                    stopVinCamera();
                    setVinStatus('Camera access is unavailable or blocked. You can upload a VIN photo or enter the VIN manually.', 'warning');
                }
            };

            const captureVinCameraPhoto = () => {
                if (!vinCameraStream || !vinCameraPreview.videoWidth) {
                    setVinStatus('Camera is not ready. Try opening it again or upload a VIN photo.', 'warning');
                    return;
                }

                vinCameraCanvas.width = vinCameraPreview.videoWidth;
                vinCameraCanvas.height = vinCameraPreview.videoHeight;
                vinCameraCanvas.getContext('2d').drawImage(vinCameraPreview, 0, 0);

                vinCameraCanvas.toBlob(blob => {
                    if (!blob) {
                        setVinStatus('Could not capture photo. Try again or upload a VIN photo.', 'danger');
                        return;
                    }

                    stopVinCamera();
                    uploadVinPhoto(blob, 'vin-camera-capture.jpg');
                }, 'image/jpeg', 0.92);
            };

            const renderNotFoundVin = response => {
                vinSearchResult.innerHTML = `
                    <div class="alert alert-info mb-0">
                        <div class="fw-semibold">No existing vehicle found for this VIN.</div>
                        <div class="small">You can continue creating a new vehicle using VIN <strong>${escapeHtml(response.normalized_vin || vinInput.value)}</strong>.</div>
                        <button type="button" class="btn btn-sm btn-primary mt-2" id="vinContinueNew">Continue as new vehicle</button>
                    </div>
                `;
            };

            const renderFoundVin = response => {
                const vehicle = response.vehicle || {};
                const customer = response.customer || {};
                const branch = response.branch || {};
                const history = response.history || {};
                const actions = response.actions || {};
                const vehicleTitle = [vehicle.make, vehicle.model, vehicle.year].filter(Boolean).join(' ') || vehicle.vehicle_number || 'Existing vehicle';
                const restricted = response.restricted_history
                    ? '<div class="alert alert-warning py-2 mb-3">Some previous branch history is restricted by your branch access.</div>'
                    : '';
                const openWarning = history.open_work_order
                    ? '<div class="alert alert-warning py-2 mb-3">This vehicle has an open work order. Review before starting a new check-in.</div>'
                    : '';

                vinSearchResult.innerHTML = `
                    <div class="card border mb-0">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
                                <div>
                                    <h6 class="mb-1">${escapeHtml(vehicleTitle)}</h6>
                                    <div class="text-muted small">${escapeHtml(vehicle.vin || response.normalized_vin || '')}</div>
                                </div>
                                <span class="badge bg-success">Existing vehicle found</span>
                            </div>
                            ${restricted}
                            ${openWarning}
                            <div class="vin-result-grid mb-3">
                                <div class="vin-result-item"><div class="vin-result-label">Plate</div><strong>${escapeHtml(vehicle.plate_number || '-')}</strong></div>
                                <div class="vin-result-item"><div class="vin-result-label">Customer</div><strong>${escapeHtml(customer.name || '-')}</strong><div class="small text-muted">${escapeHtml(customer.phone || '')}</div></div>
                                <div class="vin-result-item"><div class="vin-result-label">Last branch</div><strong>${escapeHtml(branch.name || '-')}</strong></div>
                                <div class="vin-result-item"><div class="vin-result-label">Last visit</div><strong>${escapeHtml(history.last_check_in_at || '-')}</strong></div>
                                <div class="vin-result-item"><div class="vin-result-label">Last work order</div><strong>${escapeHtml(history.last_work_order_number || '-')}</strong><div class="small text-muted">${escapeHtml(history.last_status || '')}</div></div>
                                <div class="vin-result-item"><div class="vin-result-label">Total visits</div><strong>${escapeHtml(history.total_visits ?? '-')}</strong></div>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-primary" id="vinUseExisting">Use this vehicle for new check-in</button>
                                ${actions.profile_url ? `<a class="btn btn-outline-light" href="${escapeHtml(actions.profile_url)}" target="_blank" rel="noopener">View vehicle profile/history</a>` : ''}
                                <button type="button" class="btn btn-outline-light" id="vinStartInspection">Start inspection/check-in</button>
                            </div>
                        </div>
                    </div>
                `;

                document.getElementById('vinUseExisting')?.addEventListener('click', () => useVehicleFromVin(response, false));
                document.getElementById('vinStartInspection')?.addEventListener('click', () => useVehicleFromVin(response, true));
            };

            const useVehicleFromVin = (response, goNext = false) => {
                const vehicle = response.vehicle || {};
                const customer = response.customer || {};

                if (vehicle.id) {
                    if (!vehicleOptions.find(item => String(item.id) === String(vehicle.id))) {
                        vehicleOptions.unshift({
                            id: vehicle.id,
                            customer_id: customer.id,
                            customer_name: customer.name,
                            make: vehicle.make,
                            model: vehicle.model,
                            year: vehicle.year,
                            trim: vehicle.trim,
                            color: vehicle.color,
                            plate_number: vehicle.plate_number,
                            plate_source: vehicle.plate_source,
                            plate_country: vehicle.plate_country,
                            vin: vehicle.vin,
                            odometer: vehicle.odometer,
                            fuel_type: vehicle.fuel_type,
                            transmission: vehicle.transmission,
                            search: [vehicle.vehicle_number, vehicle.plate_number, vehicle.vin, vehicle.make, vehicle.model, customer.name, customer.phone].filter(Boolean).join(' ').toLowerCase(),
                        });
                    }
                    filterVehicles();
                    vehicleSelect.value = vehicle.id;
                }

                if (customer.id) {
                    if (!customerOptions.find(item => String(item.id) === String(customer.id))) {
                        customerOptions.unshift({
                            id: customer.id,
                            name: customer.name,
                            phone: customer.phone,
                            email: customer.email,
                            customer_type: customer.customer_type,
                            company_name: customer.company_name,
                            search: [customer.name, customer.phone, customer.email, customer.company_name].filter(Boolean).join(' ').toLowerCase(),
                        });
                    }
                    filterCustomers();
                    customerSelect.value = customer.id;
                }

                fillField('customer_name', customer.name, true);
                fillField('customer_phone', customer.phone, true);
                fillField('customer_email', customer.email, true);
                fillField('company_name', customer.company_name, true);
                fillField('make', vehicle.make, true);
                fillField('model', vehicle.model, true);
                fillField('year', vehicle.year, true);
                fillField('trim', vehicle.trim, true);
                fillField('color', vehicle.color, true);
                fillField('plate_number', vehicle.plate_number, true);
                fillField('vin_number', vehicle.vin || response.normalized_vin, true);
                fillField('odometer', vehicle.odometer, false);
                fillField('fuel_type', vehicle.fuel_type, false);
                fillField('transmission', vehicle.transmission, false);

                const typeSelect = document.querySelector('[name="customer_type"]');
                if (typeSelect && customer.customer_type) typeSelect.value = customer.customer_type;

                setSearchStatus('Existing vehicle selected', 'success');
                if (goNext) setStep(3);
            };

            const searchConfirmedVin = async () => {
                if (vinSearchInFlight) return;

                const vin = normalizeVin(vinInput.value);
                if (!vin) {
                    vinSearchResult.innerHTML = '<div class="alert alert-warning mb-0">Enter or capture a VIN before confirming.</div>';
                    setSearchStatus('VIN required', 'warning');
                    return;
                }

                vinInput.value = vin;
                vinConfirmed.checked = true;
                vinSearchInFlight = true;
                setSearchStatus('Searching vehicle history...', 'primary');
                vinSearchResult.innerHTML = '<div class="text-muted small">Searching vehicle history...</div>';

                try {
                    const response = await fetch(vinSearchUrl + '?vin=' + encodeURIComponent(vin), {
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                    });
                    const payload = await response.json();

                    if (!response.ok) {
                        throw new Error(payload.message || 'VIN search failed.');
                    }

                    if (payload.found) {
                        setSearchStatus('Vehicle found', 'success');
                        renderFoundVin(payload);
                    } else {
                        setSearchStatus('No match', 'info');
                        renderNotFoundVin(payload);
                    }
                } catch (error) {
                    setSearchStatus('Search failed', 'danger');
                    vinSearchResult.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(error.message || 'Unable to search VIN.')}</div>`;
                } finally {
                    vinSearchInFlight = false;
                }
            };

            const uploadVinPhoto = async (file, filename = 'vin-photo.jpg') => {
                if (!file || vinCaptureInFlight) return;

                vinCaptureInFlight = true;
                setVinCaptureBusy(true);
                setVinStatus('Analyzing VIN photo...', 'primary');
                vinConfidenceBadge.classList.add('d-none');
                vinImagePreview.src = URL.createObjectURL(file);
                vinImagePreviewWrap.classList.remove('d-none');

                const formData = new FormData();
                formData.append('vin_photo', file, filename);

                try {
                    const response = await fetch(vinCaptureUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: formData,
                    });
                    const payload = await response.json();

                    if (!response.ok) {
                        throw new Error(payload.message || 'VIN OCR failed.');
                    }

                    const analysis = payload.analysis || {};
                    if (analysis.detected_vin) {
                        vinInput.value = analysis.detected_vin;
                        setVinStatus('OCR suggestion ready - please verify', 'success');
                        if (analysis.confidence_score) {
                            vinConfidenceBadge.textContent = `OCR confidence ${analysis.confidence_score}%`;
                            vinConfidenceBadge.classList.remove('d-none');
                        }
                    } else {
                        setVinStatus(analysis.ocr_available ? 'No VIN detected - enter manually' : 'OCR unavailable - enter manually', 'warning');
                    }
                } catch (error) {
                    setVinStatus('OCR failed - enter manually', 'danger');
                    vinSearchResult.innerHTML = `<div class="alert alert-warning mb-0">${escapeHtml(error.message || 'OCR unavailable. Enter VIN manually.')}</div>`;
                } finally {
                    vinCaptureInFlight = false;
                    setVinCaptureBusy(false);
                }
            };

            const setSelectedArea = area => {
                conditionAreaSelect.value = area;
                document.querySelectorAll('.vehicle-map [data-area]').forEach(part => {
                    part.classList.toggle('active', part.dataset.area === area);
                });
            };

            const renderConditions = () => {
                conditionItemsList.innerHTML = '';
                conditionItemsInputs.innerHTML = '';

                conditions.forEach((item, index) => {
                    const pill = document.createElement('div');
                    pill.className = 'condition-pill';
                    pill.innerHTML = `
                        <div class="d-flex justify-content-between gap-2">
                            <div>
                                <strong>${escapeHtml(item.label)}</strong>
                                <div class="small text-muted">${escapeHtml(item.note_type.replace('_', ' '))} · ${escapeHtml(item.severity)}</div>
                                <div>${escapeHtml(item.description)}</div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-condition-remove="${index}">&times;</button>
                        </div>
                    `;
                    conditionItemsList.appendChild(pill);

                    Object.entries(item).forEach(([key, value]) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = `condition_items[${index}][${key}]`;
                        input.value = value || '';
                        conditionItemsInputs.appendChild(input);
                    });
                });
            };

            prevButton.addEventListener('click', () => setStep(currentStep - 1));
            nextButton.addEventListener('click', () => setStep(currentStep + 1));
            stepButtons.forEach(button => button.addEventListener('click', () => setStep(Number(button.dataset.wizardGo))));
            customerSearch.addEventListener('input', filterCustomers);
            vehicleSearch.addEventListener('input', filterVehicles);
            customerSelect.addEventListener('change', event => selectCustomer(event.target.value));
            vehicleSelect.addEventListener('change', event => selectVehicle(event.target.value));
            conditionAreaSelect.addEventListener('change', event => setSelectedArea(event.target.value));
            vinCaptureButton.addEventListener('click', openVinCamera);
            vinUploadButton.addEventListener('click', () => vinPhotoInput.click());
            vinCameraCaptureButton.addEventListener('click', captureVinCameraPhoto);
            vinCameraCancelButton.addEventListener('click', () => {
                stopVinCamera();
                setVinStatus('Camera cancelled. Upload a VIN photo or enter manually.', 'light');
            });
            document.getElementById('vinManualButton').addEventListener('click', () => {
                stopVinCamera();
                vinInput.focus();
                setVinStatus('Manual entry active', 'light');
            });
            document.getElementById('vinRetakeButton').addEventListener('click', openVinCamera);
            document.getElementById('vinClearButton').addEventListener('click', () => {
                stopVinCamera();
                vinInput.value = '';
                vinConfirmed.checked = false;
                vinPhotoInput.value = '';
                vinImagePreview.src = '';
                vinImagePreviewWrap.classList.add('d-none');
                vinConfidenceBadge.classList.add('d-none');
                setVinStatus('OCR not run', 'light');
                setSearchStatus('Waiting for VIN confirmation', 'light');
                vinSearchResult.innerHTML = '<div class="text-muted small">Confirm the VIN to search existing vehicles across this tenant.</div>';
            });
            document.getElementById('vinConfirmButton').addEventListener('click', searchConfirmedVin);
            vinPhotoInput.addEventListener('change', event => uploadVinPhoto(event.target.files[0]));
            vinInput.addEventListener('input', () => {
                vinConfirmed.checked = false;
                setSearchStatus('Waiting for VIN confirmation', 'light');
            });

            window.addEventListener('beforeunload', stopVinCamera);
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) stopVinCamera();
            });

            document.querySelectorAll('.vehicle-map [data-area]').forEach(part => {
                part.addEventListener('click', () => setSelectedArea(part.dataset.area));
            });

            document.getElementById('addConditionItem').addEventListener('click', () => {
                const area = conditionAreaSelect.value;
                const description = document.getElementById('conditionDescription').value.trim();
                if (!area || !description) return;

                conditions.push({
                    vehicle_area_code: area,
                    label: vehicleAreas[area] || area,
                    note_type: document.getElementById('conditionNoteType').value,
                    severity: document.getElementById('conditionSeverity').value,
                    description,
                    customer_visible_note: document.getElementById('conditionCustomerNote').value.trim(),
                    internal_note: document.getElementById('conditionInternalNote').value.trim(),
                });

                document.getElementById('conditionDescription').value = '';
                document.getElementById('conditionCustomerNote').value = '';
                document.getElementById('conditionInternalNote').value = '';
                renderConditions();
            });

            document.getElementById('clearConditionItems').addEventListener('click', () => {
                conditions.splice(0, conditions.length);
                renderConditions();
            });

            document.addEventListener('click', event => {
                const removeIndex = event.target?.dataset?.conditionRemove;
                if (removeIndex !== undefined) {
                    conditions.splice(Number(removeIndex), 1);
                    renderConditions();
                    return;
                }

                if (event.target?.id === 'vinContinueNew') {
                    vinConfirmed.checked = true;
                    setSearchStatus('Continuing as new vehicle', 'info');
                    setStep(3);
                }
            });

            if (customerSelect.value) selectCustomer(customerSelect.value);
            if (vehicleSelect.value) selectVehicle(vehicleSelect.value);
            setStep(1);
        })();
    </script>
@endpush
