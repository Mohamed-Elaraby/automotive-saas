<?php $page = 'products-manifest-sync'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>Manifest Sync Preview</h5>
                    <p class="text-muted mb-0">Preview the workspace manifest payload that can be derived from the UI drafts for <strong>{{ $product->name }}</strong>.</p>
                </div>

                <a href="{{ route('admin.products.show', $product) }}" class="btn btn-outline-white">Back to Product Builder</a>
            </div>

            <div class="row g-4">
                <div class="col-xl-4">
                    <div class="card">
                        <div class="card-body">
                            @if(session('success'))
                                <div class="alert alert-success">{{ session('success') }}</div>
                            @endif

                            <h6 class="mb-3">Sync Checklist</h6>
                            <div class="list-group">
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Experience Draft</span>
                                    <span class="badge {{ $syncChecklist['experience_draft'] ? 'bg-success' : 'bg-warning text-dark' }}">{{ $syncChecklist['experience_draft'] ? 'Ready' : 'Missing' }}</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Runtime Modules</span>
                                    <span class="badge {{ $syncChecklist['runtime_modules'] ? 'bg-success' : 'bg-warning text-dark' }}">{{ $syncChecklist['runtime_modules'] ? 'Ready' : 'Missing' }}</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Integrations</span>
                                    <span class="badge {{ $syncChecklist['integrations'] ? 'bg-success' : 'bg-warning text-dark' }}">{{ $syncChecklist['integrations'] ? 'Ready' : 'Missing' }}</span>
                                </div>
                            </div>

                            <hr>

                            <h6 class="mb-2">Target Family</h6>
                            <div class="fw-semibold">{{ $draftFamilyKey }}</div>
                            <div class="small text-muted mt-2">
                                Current manifest status:
                                {{ $currentFamilyDefinition === [] ? 'Not configured in code yet.' : 'Family already exists in config/workspace_products.php.' }}
                            </div>

                            <hr>

                            <h6 class="mb-3">Apply Workflow</h6>
                            <form method="POST" action="{{ route('admin.products.manifest-sync.update', $product) }}">
                                @csrf
                                @method('PUT')
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="draft" @selected(($workflow['status'] ?? 'draft') === 'draft')>Draft</option>
                                        <option value="approved" @selected(($workflow['status'] ?? '') === 'approved')>Approved For Sync</option>
                                        <option value="applied" @selected(($workflow['status'] ?? '') === 'applied')>Applied In Code</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Execution Notes</label>
                                    <textarea name="notes" rows="5" class="form-control" placeholder="What still needs to happen in config/workspace_products.php or runtime wiring?">{{ old('notes', $workflow['notes'] ?? '') }}</textarea>
                                </div>
                                <div class="small text-muted mb-3">
                                    Last reviewed:
                                    {{ !empty($workflow['reviewed_at']) ? $workflow['reviewed_at'] : 'Not reviewed yet' }}
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Save Workflow State</button>
                            </form>

                            @if($latestSnapshot !== [])
                                <hr>
                                <h6 class="mb-2">Latest Snapshot</h6>
                                <div class="small text-muted mb-1">Status: <strong>{{ strtoupper((string) ($latestSnapshot['status'] ?? 'draft')) }}</strong></div>
                                <div class="small text-muted mb-1">Family: <strong>{{ $latestSnapshot['family_key'] ?? '-' }}</strong></div>
                                <div class="small text-muted mb-1">Captured At: <strong>{{ $latestSnapshot['captured_at'] ?? '-' }}</strong></div>
                                <div class="small text-muted">Notes: {{ $latestSnapshot['notes'] ?? '-' }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-xl-8">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                <h6 class="mb-0">Derived Manifest Payload</h6>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="{{ route('admin.products.manifest-sync.export', [$product, 'json']) }}" class="btn btn-sm btn-outline-primary">Open JSON</a>
                                    <a href="{{ route('admin.products.manifest-sync.export', [$product, 'php']) }}" class="btn btn-sm btn-outline-primary">Open PHP</a>
                                    <a href="{{ route('admin.products.manifest-sync.export', [$product, 'family']) }}" class="btn btn-sm btn-outline-primary">Open Family Snippet</a>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">PHP Payload</label>
                                <textarea class="form-control font-monospace" rows="14" readonly>{{ $payloadExport }}</textarea>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">JSON Payload</label>
                                <textarea class="form-control font-monospace" rows="14" readonly>{{ $payloadJson }}</textarea>
                            </div>

                            <div>
                                <label class="form-label fw-semibold">Family Snippet</label>
                                <textarea class="form-control font-monospace" rows="16" readonly>{{ $familyExport }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
