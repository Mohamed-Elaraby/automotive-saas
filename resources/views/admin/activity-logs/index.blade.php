<?php $page = 'admin-activity-logs-index'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>Activity Logs</h5>
                    <p class="text-muted mb-0">Track admin actions performed across tenant subscription and SaaS management workflows.</p>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.activity-logs.index') }}">
                        <div class="row g-3">
                            <div class="col-xl-3 col-md-6">
                                <label class="form-label">Action</label>
                                <select name="action" class="form-select">
                                    <option value="">All Actions</option>
                                    @foreach($actionOptions as $actionOption)
                                        <option value="{{ $actionOption }}" @selected(($filters['action'] ?? '') === $actionOption)>
                                        {{ $actionOption }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-xl-3 col-md-6">
                                <label class="form-label">Tenant ID</label>
                                <input
                                    type="text"
                                    name="tenant_id"
                                    value="{{ $filters['tenant_id'] ?? '' }}"
                                    class="form-control"
                                    placeholder="Search tenant ID"
                                >
                            </div>

                            <div class="col-xl-3 col-md-6">
                                <label class="form-label">Admin Email</label>
                                <input
                                    type="text"
                                    name="admin_email"
                                    value="{{ $filters['admin_email'] ?? '' }}"
                                    class="form-control"
                                    placeholder="Search admin email"
                                >
                            </div>

                            <div class="col-xl-3 col-md-6">
                                <label class="form-label">Subject Type</label>
                                <select name="subject_type" class="form-select">
                                    <option value="">All Subject Types</option>
                                    @foreach($subjectTypeOptions as $subjectTypeOption)
                                        <option value="{{ $subjectTypeOption }}" @selected(($filters['subject_type'] ?? '') === $subjectTypeOption)>
                                        {{ $subjectTypeOption }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12 d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Apply Filters</button>
                                <a href="{{ route('admin.activity-logs.index') }}" class="btn btn-light">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    @if($logs->count() === 0)
                        <div class="alert alert-warning mb-0">No activity logs matched the current filters.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>When</th>
                                    <th>Admin</th>
                                    <th>Action</th>
                                    <th>Tenant</th>
                                    <th>Subject</th>
                                    <th>Context Preview</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($logs as $log)
                                    @php
                                        $context = is_array($log->context_payload) ? $log->context_payload : [];
                                        $contextPreview = json_encode($context, JSON_UNESCAPED_SLASHES);
                                    @endphp
                                    <tr>
                                        <td>{{ $log->id }}</td>
                                        <td>{{ optional($log->created_at)->format('Y-m-d H:i:s') ?: '-' }}</td>
                                        <td>
                                            <div>{{ $log->admin_email ?: '-' }}</div>
                                            @if($log->admin_user_id)
                                                <small class="text-muted">User ID: {{ $log->admin_user_id }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">{{ $log->action }}</span>
                                        </td>
                                        <td>{{ $log->tenant_id ?: '-' }}</td>
                                        <td>
                                            <div>{{ $log->subject_type ?: '-' }}</div>
                                            @if($log->subject_id)
                                                <small class="text-muted">ID: {{ $log->subject_id }}</small>
                                            @endif
                                        </td>
                                        <td style="max-width: 340px;">
                                            @if($contextPreview && $contextPreview !== '[]')
                                                <div class="text-truncate" title="{{ $contextPreview }}">
                                                    {{ $contextPreview }}
                                                </div>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('admin.activity-logs.show', $log) }}" class="btn btn-sm btn-primary">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            {{ $logs->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
