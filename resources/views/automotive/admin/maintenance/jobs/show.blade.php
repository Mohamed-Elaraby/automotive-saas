@php($page = 'maintenance')
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper"><div class="content content-two">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div><h4 class="mb-1">{{ $job->job_number }} · {{ $job->title }}</h4><p class="mb-0 text-muted">{{ $job->workOrder?->work_order_number }} · {{ $job->workOrder?->vehicle?->make }} {{ $job->workOrder?->vehicle?->model }}</p></div>
            <a href="{{ route('automotive.admin.maintenance.jobs.index') }}" class="btn btn-outline-light">{{ __('tenant.back') }}</a>
        </div>

        <div class="row">
            <div class="col-xl-8 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.job_details') }}</h5></div><div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4"><div class="text-muted small">{{ __('maintenance.status') }}</div><strong>{{ strtoupper(str_replace('_', ' ', $job->status)) }}</strong></div>
                    <div class="col-md-4"><div class="text-muted small">{{ __('maintenance.technician') }}</div><strong>{{ $job->technician?->name ?: __('maintenance.unassigned') }}</strong></div>
                    <div class="col-md-4"><div class="text-muted small">{{ __('maintenance.qc_status') }}</div><strong>{{ strtoupper(str_replace('_', ' ', $job->qc_status)) }}</strong></div>
                </div>
                <p class="text-muted">{{ $job->description }}</p>
                <h6>{{ __('maintenance.time_logs') }}</h6>
                @forelse($job->timeLogs as $log)
                    <div class="border-bottom pb-2 mb-2"><strong>{{ strtoupper(str_replace('_', ' ', $log->action)) }}</strong><div class="text-muted small">{{ $log->technician?->name }} · {{ optional($log->started_at)->format('Y-m-d H:i') }} - {{ optional($log->ended_at)->format('Y-m-d H:i') }} · {{ $log->duration_minutes }} {{ __('maintenance.minutes') }}</div>@if($log->note)<div class="small">{{ $log->note }}</div>@endif</div>
                @empty
                    <p class="text-muted">{{ __('maintenance.no_time_logs') }}</p>
                @endforelse
            </div></div></div>
            <div class="col-xl-4 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.technician_actions') }}</h5></div><div class="card-body">
                <div class="d-grid gap-2 mb-3">
                    <form method="POST" action="{{ route('automotive.admin.maintenance.jobs.start', $job) }}">@csrf<button class="btn btn-success w-100" type="submit">{{ __('maintenance.start_job') }}</button></form>
                    <form method="POST" action="{{ route('automotive.admin.maintenance.jobs.resume', $job) }}">@csrf<button class="btn btn-outline-light w-100" type="submit">{{ __('maintenance.resume_job') }}</button></form>
                    <form method="POST" action="{{ route('automotive.admin.maintenance.jobs.complete', $job) }}">@csrf<div class="mb-2"><textarea name="note" class="form-control" rows="2" placeholder="{{ __('maintenance.completion_note') }}"></textarea></div><button class="btn btn-primary w-100" type="submit">{{ __('maintenance.complete_job') }}</button></form>
                </div>
                <form method="POST" action="{{ route('automotive.admin.maintenance.jobs.pause', $job) }}" class="mb-3">
                    @csrf
                    <textarea name="note" class="form-control mb-2" rows="2" placeholder="{{ __('maintenance.pause_note') }}"></textarea>
                    <button class="btn btn-warning w-100" type="submit">{{ __('maintenance.pause_job') }}</button>
                </form>
                <form method="POST" action="{{ route('automotive.admin.maintenance.jobs.blocker', $job) }}">
                    @csrf
                    <textarea name="note" class="form-control mb-2" rows="3" placeholder="{{ __('maintenance.blocker_note') }}" required></textarea>
                    <button class="btn btn-danger w-100" type="submit">{{ __('maintenance.mark_blocker') }}</button>
                </form>
            </div></div></div>
        </div>

        <div class="row">
            <div class="col-xl-5 d-flex">
                <div class="card flex-fill">
                    <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.job_photo_documentation') }}</h5></div>
                    <div class="card-body">
                        <form id="jobAttachmentForm" method="POST" action="{{ route('automotive.admin.maintenance.attachments.store') }}" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="attachable_type" value="job">
                            <input type="hidden" name="attachable_id" value="{{ $job->id }}">
                            <input type="hidden" name="branch_id" value="{{ $job->workOrder?->branch_id }}">
                            <div class="mb-3">
                                <label class="form-label">{{ __('maintenance.photo_category') }}</label>
                                <select name="category" class="form-select" id="jobPhotoCategory">
                                    @foreach(['before','after','blocker','other'] as $category)
                                        <option value="{{ $category }}">{{ __('maintenance.photo_categories.'.$category) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <input type="file" name="photo" class="d-none" accept="image/*,application/pdf" capture="environment" id="jobPhotoFileInput">
                            <div class="d-grid gap-2 mb-3">
                                <button type="button" class="btn btn-outline-light" id="jobCameraButton">
                                    <i class="isax isax-camera me-1"></i>{{ __('maintenance.open_camera') }}
                                </button>
                                <button type="button" class="btn btn-outline-light" id="jobManualFileButton">
                                    <i class="isax isax-document-upload me-1"></i>{{ __('maintenance.select_file') }}
                                </button>
                            </div>
                            <div class="progress mb-3 d-none" id="jobUploadProgressWrap" style="height: 8px;">
                                <div class="progress-bar" id="jobUploadProgressBar" style="width: 0%"></div>
                            </div>
                            <div id="jobUploadStatus" class="small text-muted mb-3"></div>
                            <div class="mb-3"><label class="form-label">{{ __('tenant.notes') }}</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
                            <button type="submit" class="btn btn-primary">{{ __('maintenance.upload_photo') }}</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-xl-7 d-flex">
                <div class="card flex-fill">
                    <div class="card-header"><h5 class="card-title mb-0">{{ __('maintenance.before_after_photos') }}</h5></div>
                    <div class="card-body">
                        <div class="row">
                            @foreach(['before','after','blocker','other'] as $category)
                                <div class="col-lg-6 mb-3">
                                    <h6>{{ __('maintenance.photo_categories.'.$category) }}</h6>
                                    @php($attachments = $job->attachments->where('category', $category))
                                    @forelse($attachments as $attachment)
                                        <div class="border rounded p-2 mb-2">
                                            <div class="d-flex justify-content-between gap-2 align-items-start">
                                                <div>
                                                    <strong class="d-block">{{ $attachment->original_name }}</strong>
                                                    <span class="text-muted small">{{ number_format($attachment->size / 1024, 1) }} KB · {{ optional($attachment->captured_at)->format('Y-m-d H:i') }}</span>
                                                    @if($attachment->notes)<div class="small">{{ $attachment->notes }}</div>@endif
                                                </div>
                                                <a href="{{ Storage::disk($attachment->file_disk)->url($attachment->file_path) }}" target="_blank" class="btn btn-sm btn-outline-light">{{ __('tenant.view') }}</a>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-muted small">{{ __('maintenance.no_attachments') }}</p>
                                    @endforelse
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="jobCameraModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('maintenance.camera_capture') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('tenant.close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <video id="jobCameraVideo" class="w-100 rounded bg-dark" autoplay playsinline muted style="max-height: 420px; object-fit: contain;"></video>
                        <canvas id="jobCameraCanvas" class="d-none"></canvas>
                        <div id="jobCameraError" class="alert alert-warning mt-3 d-none"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">{{ __('tenant.cancel') }}</button>
                        <button type="button" class="btn btn-primary" id="jobCaptureFrameButton">{{ __('maintenance.capture_photo') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div></div>
@endsection

@push('scripts')
    <script>
        (() => {
            const csrf = @json(csrf_token());
            const attachmentUrl = @json(route('automotive.admin.maintenance.attachments.store'));
            const modalElement = document.getElementById('jobCameraModal');
            const modal = modalElement ? new bootstrap.Modal(modalElement) : null;
            const video = document.getElementById('jobCameraVideo');
            const canvas = document.getElementById('jobCameraCanvas');
            const errorBox = document.getElementById('jobCameraError');
            const progressWrap = document.getElementById('jobUploadProgressWrap');
            const progressBar = document.getElementById('jobUploadProgressBar');
            const uploadStatus = document.getElementById('jobUploadStatus');
            const fileInput = document.getElementById('jobPhotoFileInput');
            let stream = null;
            let lastUpload = null;

            const stopCamera = () => {
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                    stream = null;
                }
            };

            const openCamera = async () => {
                errorBox.classList.add('d-none');
                try {
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: { ideal: 'environment' } },
                        audio: false
                    });
                    video.srcObject = stream;
                    modal.show();
                } catch (error) {
                    errorBox.textContent = @json(__('maintenance.camera_unavailable'));
                    errorBox.classList.remove('d-none');
                    modal.show();
                }
            };

            const uploadBlob = blob => new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                const formData = new FormData();
                const category = document.getElementById('jobPhotoCategory')?.value || 'other';

                formData.append('attachable_type', 'job');
                formData.append('attachable_id', @json($job->id));
                formData.append('branch_id', @json($job->workOrder?->branch_id));
                formData.append('category', category);
                formData.append('photo', blob, category + '-job-photo.jpg');
                formData.append('notes', document.querySelector('#jobAttachmentForm textarea[name="notes"]')?.value || '');

                xhr.open('POST', attachmentUrl);
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

            document.getElementById('jobCameraButton')?.addEventListener('click', openCamera);
            document.getElementById('jobManualFileButton')?.addEventListener('click', () => fileInput.click());
            fileInput?.addEventListener('change', () => document.getElementById('jobAttachmentForm').requestSubmit());
            modalElement?.addEventListener('hidden.bs.modal', stopCamera);

            document.getElementById('jobCaptureFrameButton')?.addEventListener('click', () => {
                canvas.width = video.videoWidth || 1280;
                canvas.height = video.videoHeight || 720;
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                canvas.toBlob(async blob => {
                    if (!blob) return;
                    lastUpload = blob;
                    uploadStatus.textContent = @json(__('maintenance.uploading_photo'));
                    try {
                        await uploadBlob(blob);
                        uploadStatus.textContent = @json(__('maintenance.upload_success'));
                        window.location.reload();
                    } catch (error) {
                        uploadStatus.innerHTML = @json(__('maintenance.upload_failed')) + ' <button type="button" class="btn btn-link btn-sm p-0" id="jobRetryUploadButton">' + @json(__('maintenance.retry')) + '</button>';
                    }
                }, 'image/jpeg', 0.82);
                modal.hide();
                stopCamera();
            });

            document.addEventListener('click', async event => {
                if (event.target?.id !== 'jobRetryUploadButton' || !lastUpload) return;
                await uploadBlob(lastUpload);
                uploadStatus.textContent = @json(__('maintenance.upload_success'));
                window.location.reload();
            });
        })();
    </script>
@endpush
