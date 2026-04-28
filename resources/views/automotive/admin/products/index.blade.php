<?php $page = 'products'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    @php($workspaceQuery = request()->attributes->get('workspace_product_code') ? ['workspace_product' => request()->attributes->get('workspace_product_code')] : [])
    <div class="page-wrapper">
        <div class="content content-two">

            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
                <div>
                    <h6>{{ __('tenant.stock_items') }}</h6>
                    @if ($limitInfo)
                        <p class="mb-0 text-muted">
                            {{ __('tenant.current_stock_items') }}: {{ $limitInfo['current'] }}
                            @if (!is_null($limitInfo['limit']))
                                / {{ $limitInfo['limit'] }} - {{ __('tenant.remaining') }}: {{ $limitInfo['remaining'] }}
                            @else
                                / {{ __('tenant.unlimited') }}
                            @endif
                        </p>
                    @endif
                </div>
                <div>
                    <a href="{{ route('automotive.admin.products.create', $workspaceQuery) }}" class="btn btn-primary d-flex align-items-center">
                        <i class="isax isax-add-circle5 me-1"></i>{{ __('tenant.new_stock_item') }}
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
                        <th>{{ __('tenant.name') }}</th>
                        <th>{{ __('tenant.sku') }}</th>
                        <th>{{ __('tenant.barcode') }}</th>
                        <th>{{ __('tenant.unit') }}</th>
                        <th>{{ __('tenant.cost_price') }}</th>
                        <th>{{ __('tenant.sale_price') }}</th>
                        <th>{{ __('tenant.min') }}</th>
                        <th>{{ __('tenant.status') }}</th>
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
                                            <i class="isax isax-edit me-2"></i>{{ __('tenant.edit') }}
                                        </a>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('automotive.admin.products.destroy', $product) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item d-flex align-items-center border-0 bg-transparent w-100" onclick="return confirm('{{ __('tenant.delete_product_confirm') }}');">
                                                <i class="isax isax-trash me-2"></i>{{ __('tenant.delete') }}
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
