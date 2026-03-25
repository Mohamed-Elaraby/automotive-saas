<?php $page = 'system-errors-index'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>System Errors</h5>
                    <p class="text-muted mb-0">Track exceptions captured by the system with request, route, and user context.</p>
                </div>

                <form method="POST" action="{{ route('admin.system-errors.destroy-all') }}" onsubmit="return confirm('Delete all system errors in the current view?');">
                    @csrf
                    @foreach(request()->query() as $key => $value)
                        @if(!is_array($value))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <button type="submit" class="btn btn-danger">Delete Current View</button>
                </form>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="row mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card"><div class="card-body"><div class="text-muted mb-1">Total Errors</div><h4 class="mb-0">{{ number_format((int) ($stats['total'] ?? 0)) }}</h4></div></div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card"><div class="card-body"><div class="text-muted mb-1">Unread</div><h4 class="mb-0 text-danger">{{ number_format((int) ($stats['unread'] ?? 0)) }}</h4></div></div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card"><div class="card-body"><div class="text-muted mb-1">Unresolved</div><h4 class="mb-0 text-warning">{{ number_format((int) ($stats['unresolved'] ?? 0)) }}</h4></div></div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card"><div class="card-body"><div class="text-muted mb-1">Today</div><h4 class="mb-0 text-primary">{{ number_format((int) ($stats['today'] ?? 0)) }}</h4></div></div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.system-errors.index') }}">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Message, exception, URL, email, tenant">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Route</label>
                                <select name="route_name" class="form-select">
                                    <option value="">All</option>
                                    @foreach($routeNames as $routeName)
                                        <option value="{{ $routeName }}" {{ ($filters['route_name'] ?? '') === $routeName ? 'selected' : '' }}>
                                            {{ $routeName }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Level</label>
                                <select name="level" class="form-select">
                                    <option value="">All</option>
                                    @foreach($levels as $level)
                                        <option value="{{ $level }}" {{ ($filters['level'] ?? '') === $level ? 'selected' : '' }}>
                                            {{ strtoupper($level) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-1">
                                <label class="form-label">Read</label>
                                <select name="is_read" class="form-select">
                                    <option value="">All</option>
                                    <option value="1" {{ ($filters['is_read'] ?? '') === '1' ? 'selected' : '' }}>Y</option>
                                    <option value="0" {{ ($filters['is_read'] ?? '') === '0' ? 'selected' : '' }}>N</option>
                                </select>
                            </div>

                            <div class="col-md-1">
                                <label class="form-label">Resolved</label>
                                <select name="is_resolved" class="form-select">
                                    <option value="">All</option>
                                    <option value="1" {{ ($filters['is_resolved'] ?? '') === '1' ? 'selected' : '' }}>Y</option>
                                    <option value="0" {{ ($filters['is_resolved'] ?? '') === '0' ? 'selected' : '' }}>N</option>
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
                    @if($logs->isEmpty())
                        <div class="alert alert-light mb-0">No system errors found.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Level</th>
                                    <th>Message</th>
                                    <th>Route</th>
                                    <th>User</th>
                                    <th>Read</th>
                                    <th>Resolved</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($logs as $log)
                                    <tr>
                                        <td>{{ optional($log->occurred_at)->format('Y-m-d H:i:s') ?: '-' }}</td>
                                        <td><span class="badge bg-danger">{{ strtoupper($log->level) }}</span></td>
                                        <td>
                                            <div class="fw-semibold">{{ \Illuminate\Support\Str::limit($log->message, 120) }}</div>
                                            <div class="small text-muted">{{ $log->exception_class }}</div>
                                        </td>
                                        <td>{{ $log->route_name ?: '-' }}</td>
                                        <td>
                                            @if($log->user_email)
                                                <div>{{ $log->user_email }}</div>
                                            @endif
                                            <div class="small text-muted">{{ $log->tenant_id ?: '-' }}</div>
                                        </td>
                                        <td>
                                            <span class="badge {{ $log->is_read ? 'bg-success' : 'bg-warning text-dark' }}">
                                                {{ $log->is_read ? 'Read' : 'Unread' }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge {{ $log->is_resolved ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $log->is_resolved ? 'Resolved' : 'Open' }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                                <a href="{{ route('admin.system-errors.show', $log->id) }}" class="btn btn-sm btn-outline-primary">
                                                    View
                                                </a>

                                                @if(! $log->is_read)
                                                    <form method="POST" action="{{ route('admin.system-errors.mark-read', $log->id) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-dark">
                                                            Mark Read
                                                        </button>
                                                    </form>
                                                @endif

                                                @if(! $log->is_resolved)
                                                    <form method="POST" action="{{ route('admin.system-errors.mark-resolved', $log->id) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-success">
                                                            Resolve
                                                        </button>
                                                    </form>
                                                @endif

                                                <form method="POST" action="{{ route('admin.system-errors.destroy', $log->id) }}" onsubmit="return confirm('Delete this system error?');">
                                                    @csrf
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

                        {{ $logs->links() }}
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
