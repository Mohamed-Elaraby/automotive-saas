<?php $page = 'reference-cities-index'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>Cities</h5>
                    <p class="text-muted mb-0">Manage city reference records for all supported countries and states.</p>
                </div>

                <a href="{{ route('admin.reference-data.cities.create') }}" class="btn btn-primary">
                    Add City
                </a>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.reference-data.cities.index') }}">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="City or postal code">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Country</label>
                                <select name="country_id" class="form-select">
                                    <option value="">All</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}" {{ (int) ($filters['country_id'] ?? 0) === (int) $country->id ? 'selected' : '' }}>
                                            {{ $country->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">State</label>
                                <select name="state_id" class="form-select">
                                    <option value="">All</option>
                                    @foreach($states as $state)
                                        <option value="{{ $state->id }}" {{ (int) ($filters['state_id'] ?? 0) === (int) $state->id ? 'selected' : '' }}>
                                            {{ $state->name }} @if($state->country) - {{ $state->country->name }} @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">Status</label>
                                <select name="is_active" class="form-select">
                                    <option value="">All</option>
                                    <option value="1" {{ ($filters['is_active'] ?? '') === '1' ? 'selected' : '' }}>A</option>
                                    <option value="0" {{ ($filters['is_active'] ?? '') === '0' ? 'selected' : '' }}>I</option>
                                </select>
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Go</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    @if($cities->isEmpty())
                        <div class="alert alert-light mb-0">No cities found.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <thead>
                                <tr>
                                    <th>Country</th>
                                    <th>State</th>
                                    <th>Name</th>
                                    <th>Postal Code</th>
                                    <th>Status</th>
                                    <th>Sort</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($cities as $city)
                                    <tr>
                                        <td>{{ $city->country?->name ?: '-' }}</td>
                                        <td>{{ $city->state?->name ?: '-' }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $city->name }}</div>
                                            @if($city->native_name)
                                                <div class="small text-muted">{{ $city->native_name }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $city->postal_code ?: '-' }}</td>
                                        <td>
                                            <span class="badge {{ $city->is_active ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $city->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td>{{ $city->sort_order }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('admin.reference-data.cities.edit', $city->id) }}" class="btn btn-sm btn-outline-primary">
                                                Edit
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{ $cities->links() }}
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
