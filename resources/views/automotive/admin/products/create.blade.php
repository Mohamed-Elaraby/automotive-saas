<?php $page = 'products'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    @php($workspaceQuery = request()->attributes->get('workspace_product_code') ? ['workspace_product' => request()->attributes->get('workspace_product_code')] : [])
    <div class="page-wrapper">
        <div class="content">
            <div class="row">
                <div class="col-md-10 mx-auto">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6>
                                <a href="{{ route('automotive.admin.products.index', $workspaceQuery) }}">
                                    <i class="isax isax-arrow-left me-2"></i>Stock Items
                                </a>
                            </h6>
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

                        <div class="card">
                            <div class="card-body">
                                <h6 class="mb-3">Basic Details</h6>

                                <form method="POST" action="{{ route('automotive.admin.products.store') }}">
                                    @csrf

                                    <div class="row gx-3">
                                        <div class="col-lg-4 col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Name<span class="text-danger ms-1">*</span></label>
                                                <input type="text" name="name" class="form-control" value="{{ old('name') }}">
                                            </div>
                                        </div>

                                        <div class="col-lg-4 col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">SKU<span class="text-danger ms-1">*</span></label>
                                                <input type="text" name="sku" class="form-control" value="{{ old('sku') }}">
                                            </div>
                                        </div>

                                        <div class="col-lg-4 col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Barcode</label>
                                                <input type="text" name="barcode" class="form-control" value="{{ old('barcode') }}">
                                            </div>
                                        </div>

                                        <div class="col-lg-4 col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Unit<span class="text-danger ms-1">*</span></label>
                                                <input type="text" name="unit" class="form-control" value="{{ old('unit', 'pcs') }}">
                                            </div>
                                        </div>

                                        <div class="col-lg-4 col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Selling Price<span class="text-danger ms-1">*</span></label>
                                                <input type="number" step="0.01" min="0" name="sale_price" class="form-control" value="{{ old('sale_price', 0) }}">
                                            </div>
                                        </div>

                                        <div class="col-lg-4 col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Purchase Price<span class="text-danger ms-1">*</span></label>
                                                <input type="number" step="0.01" min="0" name="cost_price" class="form-control" value="{{ old('cost_price', 0) }}">
                                            </div>
                                        </div>

                                        <div class="col-lg-4 col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Alert Quantity</label>
                                                <input type="number" min="0" name="min_stock_alert" class="form-control" value="{{ old('min_stock_alert', 0) }}">
                                            </div>
                                        </div>

                                        <div class="col-lg-4 col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Status</label>
                                                <div class="form-check form-switch mt-2">
                                                    <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                                    <label class="form-check-label">Active</label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-lg-12">
                                            <div class="mb-3">
                                                <label class="form-label">Item Description</label>
                                                <textarea name="description" class="form-control" rows="4">{{ old('description') }}</textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center justify-content-between">
                                        <a href="{{ route('automotive.admin.products.index', $workspaceQuery) }}" class="btn btn-outline-white">Cancel</a>
                                        <button type="submit" class="btn btn-primary">Create Stock Item</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @include('automotive.admin.components.page-footer')
        </div>
    </div>
@endsection
