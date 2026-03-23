<?php $page = 'system-errors-show'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>System Error Details</h5>
                    <p class="text-muted mb-0">Full exception details, request context, and tracking metadata.</p>
                </div>

                <a href="{{ route('admin.system-errors.index') }}" class="btn btn-light">Back</a>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="row">
                <div class="col-xl-8">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="mb-3">Exception Summary</h6>

                            <table class="table table-sm table-striped align-middle mb-0">
                                <tbody>
                                <tr>
                                    <th style="width: 220px;">Occurred At</th>
                                    <td>{{ optional($log->occurred_at)->format('Y-m-d H:i:s') ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Level</th>
                                    <td>{{ strtoupper((string) $log->level) }}</td>
                                </tr>
                                <tr>
                                    <th>Exception Class</th>
                                    <td>{{ $log->exception_class }}</td>
                                </tr>
                                <tr>
                                    <th>Message</th>
                                    <td>{{ $log->message }}</td>
                                </tr>
                                <tr>
                                    <th>File</th>
                                    <td>{{ $log->file_path ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Line</th>
                                    <td>{{ $log->file_line ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Read</th>
                                    <td>{{ $log->is_read ? 'Yes' : 'No' }}</td>
                                </tr>
                                <tr>
                                    <th>Resolved</th>
                                    <td>{{ $log->is_resolved ? 'Yes' : 'No' }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-body">
                            <h6 class="mb-3">Request Context</h6>

                            <table class="table table-sm table-striped align-middle mb-0">
                                <tbody>
                                <tr>
                                    <th style="width: 220px;">Method</th>
                                    <td>{{ $log->request_method ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>URL</th>
                                    <td>{{ $log->request_url ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Path</th>
                                    <td>{{ $log->request_path ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Route Name</th>
                                    <td>{{ $log->route_name ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Route Action</th>
                                    <td>{{ $log->route_action ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>IP</th>
                                    <td>{{ $log->ip ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>User Agent</th>
                                    <td>{{ $log->user_agent ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>User ID</th>
                                    <td>{{ $log->user_id ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>User Email</th>
                                    <td>{{ $log->user_email ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Tenant ID</th>
                                    <td>{{ $log->tenant_id ?: '-' }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-body">
                            <h6 class="mb-3">Input Payload</h6>

                            <pre class="bg-light p-3 rounded small mb-0" style="white-space: pre-wrap;">{{ json_encode($log->input_payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-body">
                            <h6 class="mb-3">Context Payload</h6>

                            <pre class="bg-light p-3 rounded small mb-0" style="white-space: pre-wrap;">{{ json_encode($log->context_payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-body">
                            <h6 class="mb-3">Trace Excerpt</h6>

                            <pre class="bg-light p-3 rounded small mb-0" style="white-space: pre-wrap;">{{ $log->trace_excerpt ?: '-' }}</pre>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="mb-3">Actions</h6>

                            <div class="d-grid gap-2">
                                @if(! $log->is_read)
                                    <form method="POST" action="{{ route('admin.system-errors.mark-read', $log->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-dark w-100">Mark as Read</button>
                                    </form>
                                @endif

                                @if(! $log->is_resolved)
                                    <form method="POST" action="{{ route('admin.system-errors.mark-resolved', $log->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-success w-100">Mark as Resolved</button>
                                    </form>
                                @endif

                                <a href="{{ route('admin.system-errors.index') }}" class="btn btn-light w-100">Back to Error List</a>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-body">
                            <h6 class="mb-3">Tracking</h6>

                            <table class="table table-sm align-middle mb-0">
                                <tbody>
                                <tr>
                                    <th style="width: 160px;">Read At</th>
                                    <td>{{ optional($log->read_at)->format('Y-m-d H:i:s') ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Resolved At</th>
                                    <td>{{ optional($log->resolved_at)->format('Y-m-d H:i:s') ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Created At</th>
                                    <td>{{ optional($log->created_at)->format('Y-m-d H:i:s') ?: '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Updated At</th>
                                    <td>{{ optional($log->updated_at)->format('Y-m-d H:i:s') ?: '-' }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
