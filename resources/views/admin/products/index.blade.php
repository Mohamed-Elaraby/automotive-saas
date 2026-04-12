<?php $page = 'products-index'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            @component('admin.layouts.components.title-meta')
                @slot('title')
                    Products
                @endslot
            @endcomponent

            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>Products</h5>
                    <p class="text-muted mb-0">Manage the central multi-product catalog used by plans and portal enablement.</p>
                </div>

                <a href="{{ route('admin.products.create') }}" class="btn btn-primary">
                    Add Product
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
                                <label class="form-label">Search</label>
                                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Code, name, slug, or description">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select name="is_active" class="form-select">
                                    <option value="">All</option>
                                    <option value="1" {{ ($filters['is_active'] ?? '') === '1' ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ ($filters['is_active'] ?? '') === '0' ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    @if($products->isEmpty())
                        <div class="alert alert-light mb-0">No products found.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Slug</th>
                                    <th>Status</th>
                                    <th>Plans</th>
                                    <th>Capabilities</th>
                                    <th>Subscriptions</th>
                                    <th>Sort</th>
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
                                                {{ $product->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.plans.index', ['product_id' => $product->id]) }}" class="btn btn-sm btn-outline-primary">
                                                {{ $product->plans_count }} Plans
                                            </a>
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.products.capabilities.index', $product) }}" class="btn btn-sm btn-outline-info">
                                                {{ $product->capabilities_count }} Capabilities
                                            </a>
                                        </td>
                                        <td>{{ $product->tenant_product_subscriptions_count }}</td>
                                        <td>{{ $product->sort_order }}</td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-2">
                                                <a href="{{ route('admin.products.show', $product) }}" class="btn btn-sm btn-primary">
                                                    Builder
                                                </a>
                                                <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-outline-primary">
                                                    Edit
                                                </a>

                                                <form method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('Delete this product?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        Delete
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
