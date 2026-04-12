<?php $page = 'products-experience'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>Workspace Experience Builder</h5>
                    <p class="text-muted mb-0">Capture the portal copy, runtime shape, and integration intent for <strong>{{ $product->name }}</strong>.</p>
                </div>

                <a href="{{ route('admin.products.show', $product) }}" class="btn btn-outline-white">Back to Product Builder</a>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.products.experience.update', $product) }}">
                @csrf
                @method('PUT')

                <div class="row g-4">
                    <div class="col-xl-7">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="mb-3">Portal Experience</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Family Key</label>
                                        <input type="text" name="family_key" value="{{ old('family_key', data_get($experience, 'family_key')) }}" class="form-control" placeholder="perfume_retail">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Portal Accent</label>
                                        <input type="text" name="portal_accent" value="{{ old('portal_accent', data_get($experience, 'portal.accent')) }}" class="form-control" placeholder="primary / warning / success">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Portal Eyebrow</label>
                                        <input type="text" name="portal_eyebrow" value="{{ old('portal_eyebrow', data_get($experience, 'portal.eyebrow')) }}" class="form-control" placeholder="Perfume Retail Focus">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Portal Title</label>
                                        <input type="text" name="portal_title" value="{{ old('portal_title', data_get($experience, 'portal.title')) }}" class="form-control" placeholder="Retail and showroom operations">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Portal Description</label>
                                        <textarea name="portal_description" rows="4" class="form-control" placeholder="Describe how this product should appear in the customer portal.">{{ old('portal_description', data_get($experience, 'portal.description')) }}</textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Aliases</label>
                                        <textarea name="aliases" rows="4" class="form-control" placeholder="perfume&#10;fragrance&#10;showroom">{{ old('aliases', collect(data_get($experience, 'aliases', []))->implode("\n")) }}</textarea>
                                        <small class="text-muted">One alias per line.</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-body">
                                <h6 class="mb-3">Runtime Structure</h6>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Sidebar Title</label>
                                        <input type="text" name="sidebar_title" value="{{ old('sidebar_title', data_get($experience, 'sidebar_title')) }}" class="form-control" placeholder="Perfume Retail">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Dashboard Actions</label>
                                        <textarea name="dashboard_actions" rows="5" class="form-control" placeholder="Open POS&#10;Manage Catalog&#10;Open Customers">{{ old('dashboard_actions', collect(data_get($experience, 'dashboard_actions', []))->implode("\n")) }}</textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Runtime Modules</label>
                                        <textarea name="runtime_modules" rows="6" class="form-control" placeholder="catalog-management&#10;sales-pos&#10;inventory-batches&#10;customers-loyalty">{{ old('runtime_modules', collect(data_get($experience, 'runtime_modules', []))->implode("\n")) }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-5">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="mb-3">Integration Planning</h6>
                                <div class="mb-3">
                                    <label class="form-label">Integrations</label>
                                    <textarea name="integrations" rows="6" class="form-control" placeholder="accounting&#10;inventory&#10;customer-portal">{{ old('integrations', collect(data_get($experience, 'integrations', []))->implode("\n")) }}</textarea>
                                </div>
                                <div>
                                    <label class="form-label">Implementation Notes</label>
                                    <textarea name="notes" rows="10" class="form-control" placeholder="Outline provisioning, runtime boundaries, or portal assumptions.">{{ old('notes', data_get($experience, 'notes')) }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-body">
                                <h6 class="mb-3">Draft Purpose</h6>
                                <ul class="mb-0 text-muted ps-3">
                                    <li>This is the UI planning draft for future workspace manifest migration.</li>
                                    <li>Product Builder reads this draft as part of readiness tracking.</li>
                                    <li>Use it to capture runtime intent before writing code-level modules/routes.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save Experience Draft</button>
                    <a href="{{ route('admin.products.show', $product) }}" class="btn btn-outline-white">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
