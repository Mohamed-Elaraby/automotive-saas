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
                        </div>
                    </div>
                </div>

                <div class="col-xl-8">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="mb-3">Derived Manifest Payload</h6>
                            <pre class="bg-light border rounded p-3 small mb-0" style="white-space: pre-wrap;">{{ $payloadExport }}</pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
