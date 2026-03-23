<?php $page = 'reference-currencies-index'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            @component('admin.layouts.components.title-meta')
                @slot('title')
                    Currencies
                @endslot
            @endcomponent

            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>Currencies</h5>
                    <p class="text-muted mb-0">Manage central currency reference data.</p>
                </div>

                <a href="{{ route('admin.reference-data.currencies.create') }}" class="btn btn-primary">
                    Add Currency
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
                    <form method="GET" action="{{ route('admin.reference-data.currencies.index') }}">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Code, name, or symbol">
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
                    @if($currencies->isEmpty())
                        <div class="alert alert-light mb-0">No currencies found.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Symbol</th>
                                    <th>Native Symbol</th>
                                    <th>Decimals</th>
                                    <th>Status</th>
                                    <th>Sort</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($currencies as $currency)
                                    <tr>
                                        <td>{{ $currency->code }}</td>
                                        <td>{{ $currency->name }}</td>
                                        <td>{{ $currency->symbol ?: '-' }}</td>
                                        <td>{{ $currency->native_symbol ?: '-' }}</td>
                                        <td>{{ $currency->decimal_places }}</td>
                                        <td>
                                            <span class="badge {{ $currency->is_active ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $currency->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td>{{ $currency->sort_order }}</td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-2">
                                                <a href="{{ route('admin.reference-data.currencies.edit', $currency->id) }}" class="btn btn-sm btn-outline-primary">
                                                    Edit
                                                </a>

                                                <form method="POST" action="{{ route('admin.reference-data.currencies.destroy', $currency->id) }}" onsubmit="return confirm('Delete this currency?');">
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

                        {{ $currencies->links() }}
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
