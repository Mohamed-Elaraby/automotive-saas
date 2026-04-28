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
                                    <i class="isax isax-arrow-left me-2"></i>{{ __('tenant.stock_items') }}
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
                                <h6 class="mb-3">{{ __('tenant.basic_details') }}</h6>

                                <form method="POST" action="{{ route('automotive.admin.products.update', $product) }}">
                                    @csrf
                                    @method('PUT')

                                    <div class="row gx-3">
                                        <div class="col-lg-4 col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">{{ __('tenant.name') }}<span class="text-danger ms-1">*</span></label>
                                                <input type="text" name="name" class="form-control" value="{{ old('name', $product->name) }}">
                                            </div>
                                        </div>

                                        <div class="col-lg-4 col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">{{ __('tenant.sku') }}<span class="text-danger ms-1">*</span></label>
                                                <input type="text" name="sku" class="form-control" value="{{ old('sku', $product->sku) }}">
                                            </div>
                                        </div>

                                        <div class="col-lg-4 col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">{{ __('tenant.barcode') }}</label>
                                                <input type="text" name="barcode" class="form-control" value="{{ old('barcode', $product->barcode) }}">
                                            </div>
                                        </div>

                                        <div class="col-lg-4 col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">{{ __('tenant.unit') }}<span class="text-danger ms-1">*</span></label>
                                                <input type="text" name="unit" class="form-control" value="{{ old('unit', $product->unit) }}">
                                            </div>
                                        </div>

                                        <div class="col-lg-4 col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">{{ __('tenant.selling_price') }}<span class="text-danger ms-1">*</span></label>
                                                <input type="number" step="0.01" min="0" name="sale_price" class="form-control" value="{{ old('sale_price', $product->sale_price) }}">
                                            </div>
                                        </div>

                                        <div class="col-lg-4 col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">{{ __('tenant.purchase_price') }}<span class="text-danger ms-1">*</span></label>
                                                <input type="number" step="0.01" min="0" name="cost_price" class="form-control" value="{{ old('cost_price', $product->cost_price) }}">
                                            </div>
                                        </div>

                                        <div class="col-lg-4 col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">{{ __('tenant.alert_quantity') }}</label>
                                                <input type="number" min="0" name="min_stock_alert" class="form-control" value="{{ old('min_stock_alert', $product->min_stock_alert) }}">
                                            </div>
                                        </div>

                                        <div class="col-lg-4 col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">{{ __('tenant.status') }}</label>
                                                <div class="form-check form-switch mt-2">
                                                    <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" {{ old('is_active', $product->is_active) ? 'checked' : '' }}>
                                                    <label class="form-check-label">{{ __('tenant.active') }}</label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-lg-12">
                                            <div class="mb-3">
                                                <label class="form-label">{{ __('tenant.item_description') }}</label>
                                                <textarea name="description" class="form-control" rows="4">{{ old('description', $product->description) }}</textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center justify-content-between">
                                        <a href="{{ route('automotive.admin.products.index', $workspaceQuery) }}" class="btn btn-outline-white">{{ __('tenant.cancel') }}</a>
                                        <button type="submit" class="btn btn-primary">{{ __('tenant.save_changes') }}</button>
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
