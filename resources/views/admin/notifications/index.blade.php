<?php $page = 'admin-notifications-index'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="page-header">
                <div class="content-page-header">
                    <h5>Notifications</h5>
                    <p class="text-muted mb-0">Central notification center for system-wide in-app alerts.</p>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="row mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted mb-1">Total</div>
                            <h4 class="mb-0">{{ number_format((int) ($stats['total'] ?? 0)) }}</h4>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted mb-1">Unread</div>
                            <h4 class="mb-0 text-danger">{{ number_format((int) ($stats['unread'] ?? 0)) }}</h4>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted mb-1">Active</div>
                            <h4 class="mb-0 text-primary">{{ number_format((int) ($stats['active'] ?? 0)) }}</h4>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted mb-1">Today</div>
                            <h4 class="mb-0 text-success">{{ number_format((int) ($stats['today'] ?? 0)) }}</h4>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.notifications.index') }}">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Title, message, email, tenant">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Type</label>
                                <select name="type" class="form-select">
                                    <option value="">All</option>
                                    @foreach($types as $type)
                                        <option value="{{ $type }}" {{ ($filters['type'] ?? '') === $type ? 'selected' : '' }}>
                                            {{ $type }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Severity</label>
                                <select name="severity" class="form-select">
                                    <option value="">All</option>
                                    @foreach($severities as $severity)
                                        <option value="{{ $severity }}" {{ ($filters['severity'] ?? '') === $severity ? 'selected' : '' }}>
                                            {{ strtoupper($severity) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Read</label>
                                <select name="is_read" class="form-select">
                                    <option value="">All</option>
                                    <option value="1" {{ ($filters['is_read'] ?? '') === '1' ? 'selected' : '' }}>Read</option>
                                    <option value="0" {{ ($filters['is_read'] ?? '') === '0' ? 'selected' : '' }}>Unread</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Archived</label>
                                <select name="is_archived" class="form-select">
                                    <option value="">All</option>
                                    <option value="1" {{ ($filters['is_archived'] ?? '') === '1' ? 'selected' : '' }}>Archived</option>
                                    <option value="0" {{ ($filters['is_archived'] ?? '') === '0' ? 'selected' : '' }}>Active</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="{{ route('admin.notifications.index') }}" class="btn btn-light">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    @if($notifications->isEmpty())
                        <div class="alert alert-light mb-0">No notifications found.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Type</th>
                                    <th>Title</th>
                                    <th>Severity</th>
                                    <th>Read</th>
                                    <th>Archived</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($notifications as $notification)
                                    <tr>
                                        <td>{{ optional($notification->notified_at)->format('Y-m-d H:i:s') ?: '-' }}</td>
                                        <td>{{ $notification->type }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $notification->title }}</div>
                                            <div class="small text-muted">{{ \Illuminate\Support\Str::limit((string) $notification->message, 100) }}</div>
                                        </td>
                                        <td>
                                            <span class="badge {{ match($notification->severity) {
                                                'error' => 'bg-danger',
                                                'warning' => 'bg-warning text-dark',
                                                'success' => 'bg-success',
                                                default => 'bg-primary',
                                            } }}">
                                                {{ strtoupper($notification->severity) }}
                                            </span>
                                        </td>
                                        <td>{{ $notification->is_read ? 'Yes' : 'No' }}</td>
                                        <td>{{ $notification->is_archived ? 'Yes' : 'No' }}</td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-2">
                                                <a href="{{ route('admin.notifications.show', $notification->id) }}" class="btn btn-sm btn-outline-primary">
                                                    View
                                                </a>

                                                @if(! $notification->is_read)
                                                    <form method="POST" action="{{ route('admin.notifications.mark-read', $notification->id) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-dark">
                                                            Mark Read
                                                        </button>
                                                    </form>
                                                @endif

                                                @if(! $notification->is_archived)
                                                    <form method="POST" action="{{ route('admin.notifications.archive', $notification->id) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                            Archive
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{ $notifications->links() }}
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
