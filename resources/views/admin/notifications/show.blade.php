<?php $page = 'admin-notifications-show'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>Notification Details</h5>
                    <p class="text-muted mb-0">Full details for the selected system notification.</p>
                </div>

                <a href="{{ route('admin.notifications.index') }}" class="btn btn-light">Back</a>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="row">
                <div class="col-xl-8">
                    <div class="card">
                        <div class="card-body">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <tbody>
                                <tr>
                                    <th style="width: 220px;">Type</th>
                                    <td>{{ $notification->type }}</td>
                                </tr>
                                <tr>
                                    <th>Title</th>
                                    <td>{{ $notification->title }}</td>
                                </tr>
                                <tr>
                                    <th>Message</th>
                                    <td>{{ $notification->message ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Severity</th>
                                    <td>{{ strtoupper($notification->severity) }}</td>
                                </tr>
                                <tr>
                                    <th>Route Name</th>
                                    <td>{{ $notification->route_name ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Target URL</th>
                                    <td>{{ $notification->target_url ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Tenant ID</th>
                                    <td>{{ $notification->tenant_id ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>User Email</th>
                                    <td>{{ $notification->user_email ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Read</th>
                                    <td>{{ $notification->is_read ? 'Yes' : 'No' }}</td>
                                </tr>
                                <tr>
                                    <th>Archived</th>
                                    <td>{{ $notification->is_archived ? 'Yes' : 'No' }}</td>
                                </tr>
                                <tr>
                                    <th>Notified At</th>
                                    <td>{{ optional($notification->notified_at)->format('Y-m-d H:i:s') ?: '-' }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-body">
                            <h6 class="mb-3">Context Payload</h6>
                            <pre class="bg-light p-3 rounded small mb-0" style="white-space: pre-wrap;">{{ json_encode($notification->context_payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="mb-3">Actions</h6>

                            <div class="d-grid gap-2">
                                @if(! $notification->is_read)
                                    <form method="POST" action="{{ route('admin.notifications.mark-read', $notification->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-dark w-100">Mark as Read</button>
                                    </form>
                                @endif

                                @if(! $notification->is_archived)
                                    <form method="POST" action="{{ route('admin.notifications.archive', $notification->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-secondary w-100">Archive</button>
                                    </form>
                                @endif

                                <a href="{{ $notification->resolvedUrl() }}" class="btn btn-primary w-100">Open Target</a>
                                <a href="{{ route('admin.notifications.index') }}" class="btn btn-light w-100">Back to Notifications</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
