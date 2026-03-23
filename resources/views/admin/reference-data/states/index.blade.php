<?php $page = 'reference-states-index'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>States</h5>
                    <p class="text-muted mb-0">Manage states, provinces, governorates, and regions.</p>
                </div>

                <a href="{{ route('admin.reference-data.states.create') }}" class="btn btn-primary">
                    Add State
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
                    <form method="GET" action="{{ route('admin.reference-data.states.index') }}">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Name or code">
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

                            <div class="col-md-2">
                                <label class="form-label">Type</label>
                                <select name="type" class="form-select">
                                    <option value="">All</option>
                                    @foreach($types as $type)
                                        <option value="{{ $type }}" {{ ($filters['type'] ?? '') === $type ? 'selected' : '' }}>
                                            {{ ucfirst($type) }}
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

                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Go</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    @if($states->isEmpty())
                        <div class="alert alert-light mb-0">No states found.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <thead>
                                <tr>
                                    <th>Country</th>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Sort</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($states as $state)
                                    <tr>
                                        <td>{{ $state->country?->name ?: '-' }}</td>
                                        <td>{{ $state->code ?: '-' }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $state->name }}</div>
                                            @if($state->native_name)
                                                <div class="small text-muted">{{ $state->native_name }}</div>
                                            @endif
                                        </td>
                                        <td>{{ ucfirst((string) $state->type) }}</td>
                                        <td>
                                            <span class="badge {{ $state->is_active ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $state->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td>{{ $state->sort_order }}</td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-2">
                                                <a href="{{ route('admin.reference-data.states.edit', $state->id) }}" class="btn btn-sm btn-outline-primary">
                                                    Edit
                                                </a>

                                                <form method="POST" action="{{ route('admin.reference-data.states.destroy', $state->id) }}" onsubmit="return confirm('Delete this state?');">
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

                        {{ $states->links() }}
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
