<?php $page = 'reference-countries-index'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>Countries</h5>
                    <p class="text-muted mb-0">Manage countries and their default currencies.</p>
                </div>

                <a href="{{ route('admin.reference-data.countries.create') }}" class="btn btn-primary">
                    Add Country
                </a>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.reference-data.countries.index') }}">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="ISO, name, capital">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Currency</label>
                                <select name="currency_code" class="form-select">
                                    <option value="">All</option>
                                    @foreach($currencies as $currency)
                                        <option value="{{ $currency->code }}" {{ ($filters['currency_code'] ?? '') === $currency->code ? 'selected' : '' }}>
                                            {{ $currency->code }} - {{ $currency->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select name="is_active" class="form-select">
                                    <option value="">All</option>
                                    <option value="1" {{ ($filters['is_active'] ?? '') === '1' ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ ($filters['is_active'] ?? '') === '0' ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    @if($countries->isEmpty())
                        <div class="alert alert-light mb-0">No countries found.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <thead>
                                <tr>
                                    <th>ISO2</th>
                                    <th>ISO3</th>
                                    <th>Name</th>
                                    <th>Capital</th>
                                    <th>Currency</th>
                                    <th>Status</th>
                                    <th>Sort</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($countries as $country)
                                    <tr>
                                        <td>{{ $country->iso2 }}</td>
                                        <td>{{ $country->iso3 }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $country->name }}</div>
                                            @if($country->native_name)
                                                <div class="small text-muted">{{ $country->native_name }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $country->capital ?: '-' }}</td>
                                        <td>{{ $country->currency_code ?: '-' }}</td>
                                        <td>
                                            <span class="badge {{ $country->is_active ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $country->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td>{{ $country->sort_order }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('admin.reference-data.countries.edit', $country->id) }}" class="btn btn-sm btn-outline-primary">
                                                Edit
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{ $countries->links() }}
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
