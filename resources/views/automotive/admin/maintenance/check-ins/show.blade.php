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
                <div class="d-flex gap-2">
                    @if($checkIn->customer)
                        <a href="{{ route('automotive.admin.maintenance.customers.profile', $checkIn->customer) }}" class="btn btn-outline-light">{{ __('maintenance.profiles.open_customer_360') }}</a>
                    @endif
                    @if($checkIn->vehicle)
                        <a href="{{ route('automotive.admin.maintenance.vehicles.profile', $checkIn->vehicle) }}" class="btn btn-outline-light">{{ __('maintenance.profiles.open_vehicle_360') }}</a>
                    @endif
                    <a href="{{ route('automotive.admin.maintenance.check-ins.index') }}" class="btn btn-outline-light">{{ __('tenant.back') }}</a>
                </div>
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
                            <form id="vinConfirmForm" method="POST" action="{{ route('automotive.admin.maintenance.check-ins.verify-vin', $checkIn) }}">
                                @csrf
                                <input type="hidden" name="vin_source_image_id" id="vinSourceImageId">
                                <input type="hidden" name="vin_confidence_score" id="vinConfidenceScore">
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
                            <hr>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-light" data-camera-open="vin">
                                    <i class="isax isax-camera me-1"></i>{{ __('maintenance.capture_vin_image') }}
                                </button>
                                <button type="button" class="btn btn-outline-light" id="searchVinButton">
                                    <i class="isax isax-search-normal me-1"></i>{{ __('maintenance.search_vehicle_by_vin') }}
                                </button>
                            </div>
                            <div id="vinOcrResult" class="alert alert-light border mt-3 d-none"></div>
                            <div id="vinVehicleMatches" class="mt-3"></div>
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
                                <input type="file" name="photo" class="d-none" accept="image/*,application/pdf" capture="environment" id="photoFileInput">
                                <div class="d-grid gap-2 mb-3">
                                    <button type="button" class="btn btn-outline-light" data-camera-open="photo">
                                        <i class="isax isax-camera me-1"></i>{{ __('maintenance.open_camera') }}
                                    </button>
                                    <button type="button" class="btn btn-outline-light" id="manualFileButton">
                                        <i class="isax isax-document-upload me-1"></i>{{ __('maintenance.select_file') }}
                                    </button>
                                </div>
                                <div class="progress mb-3 d-none" id="uploadProgressWrap" style="height: 8px;">
                                    <div class="progress-bar" id="uploadProgressBar" style="width: 0%"></div>
                                </div>
                                <div id="uploadStatus" class="small text-muted mb-3"></div>
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

            <div class="modal fade" id="cameraModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('maintenance.camera_capture') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('tenant.close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <video id="cameraVideo" class="w-100 rounded bg-dark" autoplay playsinline muted style="max-height: 420px; object-fit: contain;"></video>
                            <canvas id="cameraCanvas" class="d-none"></canvas>
                            <div id="cameraError" class="alert alert-warning mt-3 d-none"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">{{ __('tenant.cancel') }}</button>
                            <button type="button" class="btn btn-primary" id="captureFrameButton">{{ __('maintenance.capture_photo') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const csrf = @json(csrf_token());
            const attachmentUrl = @json(route('automotive.admin.maintenance.attachments.store'));
            const vinCaptureUrl = @json(route('automotive.admin.maintenance.check-ins.capture-vin', $checkIn));
            const vinSearchUrl = @json(route('automotive.admin.maintenance.vehicles.search-vin'));
            const cameraModalElement = document.getElementById('cameraModal');
            const cameraModal = cameraModalElement ? new bootstrap.Modal(cameraModalElement) : null;
            const video = document.getElementById('cameraVideo');
            const canvas = document.getElementById('cameraCanvas');
            const errorBox = document.getElementById('cameraError');
            const progressWrap = document.getElementById('uploadProgressWrap');
            const progressBar = document.getElementById('uploadProgressBar');
            const uploadStatus = document.getElementById('uploadStatus');
            const photoFileInput = document.getElementById('photoFileInput');
            let stream = null;
            let captureMode = 'photo';
            let lastUpload = null;

            const stopCamera = () => {
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                    stream = null;
                }
            };

            const openCamera = async (mode) => {
                captureMode = mode;
                errorBox.classList.add('d-none');
                try {
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: { ideal: 'environment' } },
                        audio: false
                    });
                    video.srcObject = stream;
                    cameraModal.show();
                } catch (error) {
                    errorBox.textContent = @json(__('maintenance.camera_unavailable'));
                    errorBox.classList.remove('d-none');
                    cameraModal.show();
                }
            };

            const uploadBlob = (blob, mode) => new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                const formData = new FormData();
                const category = document.querySelector('[name="category"]')?.value || 'other';

                if (mode === 'vin') {
                    formData.append('vin_photo', blob, 'vin-capture.jpg');
                } else {
                    formData.append('attachable_type', 'check_in');
                    formData.append('attachable_id', @json($checkIn->id));
                    formData.append('branch_id', @json($checkIn->branch_id));
                    formData.append('category', category);
                    formData.append('photo', blob, category + '-capture.jpg');
                    formData.append('notes', document.querySelector('#attachmentForm textarea[name="notes"]')?.value || '');
                }

                xhr.open('POST', mode === 'vin' ? vinCaptureUrl : attachmentUrl);
                xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.upload.addEventListener('progress', event => {
                    if (!event.lengthComputable) return;
                    progressWrap.classList.remove('d-none');
                    progressBar.style.width = Math.round((event.loaded / event.total) * 100) + '%';
                });
                xhr.onload = () => {
                    progressWrap.classList.add('d-none');
                    if (xhr.status >= 200 && xhr.status < 300) {
                        resolve(JSON.parse(xhr.responseText));
                    } else {
                        reject(new Error(xhr.responseText || 'Upload failed'));
                    }
                };
                xhr.onerror = () => reject(new Error('Upload failed'));
                xhr.send(formData);
            });

            const renderVinResult = (response) => {
                const result = document.getElementById('vinOcrResult');
                const matches = document.getElementById('vinVehicleMatches');
                result.classList.remove('d-none');
                result.textContent = response.message;
                document.getElementById('vinSourceImageId').value = response.attachment.id;

                if (response.analysis.detected_vin) {
                    document.querySelector('#vinConfirmForm input[name="vin_number"]').value = response.analysis.detected_vin;
                    document.querySelector('#vinConfirmForm select[name="vin_verification_method"]').value = 'ocr';
                    document.getElementById('vinConfidenceScore').value = response.analysis.confidence_score || '';
                }

                renderMatches(response.analysis.vehicle_matches || []);
            };

            const renderMatches = (vehicles) => {
                const matches = document.getElementById('vinVehicleMatches');
                if (!vehicles.length) {
                    matches.innerHTML = '<div class="text-muted small">' + @json(__('maintenance.no_vin_matches')) + '</div>';
                    return;
                }

                matches.innerHTML = vehicles.map(vehicle => `
                    <div class="border rounded p-2 mb-2">
                        <strong>${vehicle.vehicle_number || ''} ${vehicle.plate_number || ''}</strong>
                        <div class="small text-muted">${vehicle.make || ''} ${vehicle.model || ''} ${vehicle.year || ''} · ${vehicle.customer_name || ''}</div>
                        <div class="small">${vehicle.vin || ''}</div>
                    </div>
                `).join('');
            };

            document.querySelectorAll('[data-camera-open]').forEach(button => {
                button.addEventListener('click', () => openCamera(button.dataset.cameraOpen));
            });

            document.getElementById('captureFrameButton')?.addEventListener('click', () => {
                canvas.width = video.videoWidth || 1280;
                canvas.height = video.videoHeight || 720;
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                canvas.toBlob(async blob => {
                    if (!blob) return;
                    lastUpload = { blob, mode: captureMode };
                    uploadStatus.textContent = @json(__('maintenance.uploading_photo'));
                    try {
                        const response = await uploadBlob(blob, captureMode);
                        uploadStatus.textContent = @json(__('maintenance.upload_success'));
                        if (captureMode === 'vin') {
                            renderVinResult(response);
                        } else {
                            window.location.reload();
                        }
                    } catch (error) {
                        uploadStatus.innerHTML = @json(__('maintenance.upload_failed')) + ' <button type="button" class="btn btn-link btn-sm p-0" id="retryUploadButton">' + @json(__('maintenance.retry')) + '</button>';
                    }
                }, 'image/jpeg', 0.82);
                cameraModal.hide();
                stopCamera();
            });

            cameraModalElement?.addEventListener('hidden.bs.modal', stopCamera);

            document.addEventListener('click', async event => {
                if (event.target?.id !== 'retryUploadButton' || !lastUpload) return;
                const response = await uploadBlob(lastUpload.blob, lastUpload.mode);
                uploadStatus.textContent = @json(__('maintenance.upload_success'));
                if (lastUpload.mode === 'vin') {
                    renderVinResult(response);
                } else {
                    window.location.reload();
                }
            });

            document.getElementById('manualFileButton')?.addEventListener('click', () => photoFileInput.click());
            photoFileInput?.addEventListener('change', () => document.getElementById('attachmentForm').requestSubmit());

            document.getElementById('searchVinButton')?.addEventListener('click', async () => {
                const vin = document.querySelector('#vinConfirmForm input[name="vin_number"]').value;
                if (!vin) return;
                const response = await fetch(vinSearchUrl + '?vin=' + encodeURIComponent(vin), {
                    headers: { 'Accept': 'application/json' }
                });
                const payload = await response.json();
                renderMatches(payload.vehicles || []);
            });
        })();
    </script>
@endpush
