<?php $page = 'product-capabilities-index'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>{{ $product->name }} Capabilities</h5>
                    <p class="text-muted mb-0">Manage product-level modules and capabilities for this product.</p>
                </div>

                <div class="d-flex gap-2">
                    <a href="{{ route('admin.products.index') }}" class="btn btn-outline-white">Back to Products</a>
                    <a href="{{ route('admin.products.capabilities.create', $product) }}" class="btn btn-primary">Add Capability</a>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.products.capabilities.index', $product) }}">
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
                    @if($capabilities->isEmpty())
                        <div class="alert alert-light mb-0">No capabilities found for this product yet.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Slug</th>
                                    <th>Status</th>
                                    <th>Sort</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($capabilities as $capability)
                                    <tr>
                                        <td>{{ $capability->code }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $capability->name }}</div>
                                            <div class="text-muted small">{{ $capability->description ?: '-' }}</div>
                                        </td>
                                        <td>{{ $capability->slug }}</td>
                                        <td>
                                            <span class="badge {{ $capability->is_active ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $capability->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td>{{ $capability->sort_order }}</td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-2">
                                                <a href="{{ route('admin.products.capabilities.edit', [$product, $capability]) }}" class="btn btn-sm btn-outline-primary">
                                                    Edit
                                                </a>
                                                <form method="POST" action="{{ route('admin.products.capabilities.destroy', [$product, $capability]) }}" onsubmit="return confirm('Delete this capability?');">
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

                        {{ $capabilities->links() }}
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
