<?php $page = 'products-runtime-modules'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>Runtime Module Builder</h5>
                    <p class="text-muted mb-0">Define the runtime modules that should eventually power <strong>{{ $product->name }}</strong> inside the tenant workspace.</p>
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

            <form method="POST" action="{{ route('admin.products.runtime-modules.update', $product) }}">
                @csrf
                @method('PUT')

                <div class="card">
                    <div class="card-body">
                        <div class="alert alert-info">
                            Capture runtime structure here first, then map it into workspace manifest/runtime routes later.
                        </div>

                        @foreach($modules as $index => $module)
                            <div class="border rounded p-3 mb-3">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h6 class="mb-0">Module {{ $index + 1 }}</h6>
                                    <span class="badge bg-light text-dark">Draft Row</span>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Module Key</label>
                                        <input type="text" name="modules[{{ $index }}][key]" value="{{ old("modules.{$index}.key", $module['key']) }}" class="form-control" placeholder="sales-pos">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Title</label>
                                        <input type="text" name="modules[{{ $index }}][title]" value="{{ old("modules.{$index}.title", $module['title']) }}" class="form-control" placeholder="Sales POS">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Focus Code</label>
                                        <input type="text" name="modules[{{ $index }}][focus_code]" value="{{ old("modules.{$index}.focus_code", $module['focus_code']) }}" class="form-control" placeholder="{{ $product->code }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Route Slug</label>
                                        <input type="text" name="modules[{{ $index }}][route_slug]" value="{{ old("modules.{$index}.route_slug", $module['route_slug']) }}" class="form-control" placeholder="sales-pos">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Icon</label>
                                        <input type="text" name="modules[{{ $index }}][icon]" value="{{ old("modules.{$index}.icon", $module['icon']) }}" class="form-control" placeholder="isax-shop">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Description</label>
                                        <textarea name="modules[{{ $index }}][description]" rows="3" class="form-control" placeholder="Describe what this runtime module owns.">{{ old("modules.{$index}.description", $module['description']) }}</textarea>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save Runtime Modules</button>
                    <a href="{{ route('admin.products.show', $product) }}" class="btn btn-outline-white">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
