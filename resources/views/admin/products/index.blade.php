<?php $page = 'products-index'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            @component('admin.layouts.components.title-meta')
                @slot('title')
                    {{ __('admin.products') }}
                @endslot
            @endcomponent

            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>{{ __('admin.products') }}</h5>
                    <p class="text-muted mb-0">{{ __('admin.manage_products_intro') }}</p>
                </div>

                <a href="{{ route('admin.products.create') }}" class="btn btn-primary">
                    {{ __('admin.add_product') }}
                </a>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.products.index') }}">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">{{ __('admin.search') }}</label>
                                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="{{ __('admin.product_search_placeholder') }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">{{ __('admin.status') }}</label>
                                <select name="is_active" class="form-select">
                                    <option value="">{{ __('admin.all') }}</option>
                                    <option value="1" {{ ($filters['is_active'] ?? '') === '1' ? 'selected' : '' }}>{{ __('admin.active') }}</option>
                                    <option value="0" {{ ($filters['is_active'] ?? '') === '0' ? 'selected' : '' }}>{{ __('admin.inactive') }}</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary w-100">{{ __('admin.filter') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    @if($products->isEmpty())
                        <div class="alert alert-light mb-0">{{ __('admin.no_products_found') }}</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <thead>
                                <tr>
                                    <th>{{ __('admin.code') }}</th>
                                    <th>{{ __('admin.name') }}</th>
                                    <th>{{ __('admin.slug') }}</th>
                                    <th>{{ __('admin.status') }}</th>
                                    <th>{{ __('admin.plans') }}</th>
                                    <th>{{ __('admin.capabilities') }}</th>
                                    <th>{{ __('admin.subscriptions') }}</th>
                                    <th>{{ __('admin.sort') }}</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($products as $product)
                                    <tr>
                                        <td>{{ $product->code }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $product->name }}</div>
                                            <div class="text-muted small">{{ $product->description ?: '-' }}</div>
                                        </td>
                                        <td>{{ $product->slug }}</td>
                                        <td>
                                            <span class="badge {{ $product->is_active ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $product->is_active ? __('admin.active') : __('admin.inactive') }}
                                            </span>
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.plans.index', ['product_id' => $product->id]) }}" class="btn btn-sm btn-outline-primary">
                                                {{ $product->plans_count }} {{ __('admin.plans') }}
                                            </a>
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.products.capabilities.index', $product) }}" class="btn btn-sm btn-outline-info">
                                                {{ $product->capabilities_count }} {{ __('admin.capabilities') }}
                                            </a>
                                        </td>
                                        <td>{{ $product->tenant_product_subscriptions_count }}</td>
                                        <td>{{ $product->sort_order }}</td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-2">
                                                <a href="{{ route('admin.products.show', $product) }}" class="btn btn-sm btn-primary">
                                                    {{ __('admin.builder') }}
                                                </a>
                                                <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-outline-primary">
                                                    {{ __('admin.edit') }}
                                                </a>

                                                <form method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('{{ __('admin.delete_product_confirm') }}');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        {{ __('admin.delete') }}
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{ $products->links() }}
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
