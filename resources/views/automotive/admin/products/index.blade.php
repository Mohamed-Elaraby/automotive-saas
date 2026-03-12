<?php $page = 'products'; ?>
@extends('automotive.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">

            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
                <div>
                    <h6>Products</h6>
                    @if ($limitInfo)
                        <p class="mb-0 text-muted">
                            Current products: {{ $limitInfo['current'] }}
                            @if (!is_null($limitInfo['limit']))
                                / {{ $limitInfo['limit'] }} — Remaining: {{ $limitInfo['remaining'] }}
                            @else
                                / Unlimited
                            @endif
                        </p>
                    @endif
                </div>
                <div>
                    <a href="{{ route('automotive.admin.products.create') }}" class="btn btn-primary d-flex align-items-center">
                        <i class="isax isax-add-circle5 me-1"></i>New Product
                    </a>
                </div>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-nowrap datatable">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>SKU</th>
                        <th>Barcode</th>
                        <th>Unit</th>
                        <th>Cost Price</th>
                        <th>Sale Price</th>
                        <th>Min Alert</th>
                        <th>Status</th>
                        <th class="no-sort"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($products as $product)
                        <tr>
                            <td>
                                <h6 class="fs-14 fw-medium mb-0">{{ $product->name }}</h6>
                            </td>
                            <td>{{ $product->sku }}</td>
                            <td>{{ $product->barcode ?: '—' }}</td>
                            <td>{{ $product->unit }}</td>
                            <td>{{ $product->cost_price }}</td>
                            <td>{{ $product->sale_price }}</td>
                            <td>{{ $product->min_stock_alert }}</td>
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" disabled {{ $product->is_active ? 'checked' : '' }}>
                                </div>
                            </td>
                            <td class="action-item">
                                <a href="javascript:void(0);" data-bs-toggle="dropdown">
                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                </a>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a href="{{ route('automotive.admin.products.edit', $product) }}" class="dropdown-item d-flex align-items-center">
                                            <i class="isax isax-edit me-2"></i>Edit
                                        </a>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('automotive.admin.products.destroy', $product) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item d-flex align-items-center border-0 bg-transparent w-100" onclick="return confirm('Are you sure you want to delete this product?');">
                                                <i class="isax isax-trash me-2"></i>Delete
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @include('automotive.admin.components.page-footer')
        </div>
    </div>
@endsection
