<?php $page = 'admin-activity-logs-show'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>Activity Log Details</h5>
                    <p class="text-muted mb-0">Detailed audit snapshot for a single admin action.</p>
                </div>

                <div class="d-flex gap-2">
                    <a href="{{ route('admin.activity-logs.index') }}" class="btn btn-light">Back</a>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Overview</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-striped align-middle mb-0">
                        <tbody>
                        <tr>
                            <th style="width: 240px;">Log ID</th>
                            <td>{{ $activityLog->id }}</td>
                        </tr>
                        <tr>
                            <th>Action</th>
                            <td><span class="badge bg-primary">{{ $activityLog->action }}</span></td>
                        </tr>
                        <tr>
                            <th>Admin User ID</th>
                            <td>{{ $activityLog->admin_user_id ?: '-' }}</td>
                        </tr>
                        <tr>
                            <th>Admin Email</th>
                            <td>{{ $activityLog->admin_email ?: '-' }}</td>
                        </tr>
                        <tr>
                            <th>Tenant ID</th>
                            <td>{{ $activityLog->tenant_id ?: '-' }}</td>
                        </tr>
                        <tr>
                            <th>Subject Type</th>
                            <td>{{ $activityLog->subject_type ?: '-' }}</td>
                        </tr>
                        <tr>
                            <th>Subject ID</th>
                            <td>{{ $activityLog->subject_id ?: '-' }}</td>
                        </tr>
                        <tr>
                            <th>Created At</th>
                            <td>{{ optional($activityLog->created_at)->format('Y-m-d H:i:s') ?: '-' }}</td>
                        </tr>
                        <tr>
                            <th>Updated At</th>
                            <td>{{ optional($activityLog->updated_at)->format('Y-m-d H:i:s') ?: '-' }}</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Context Payload</h6>
                </div>
                <div class="card-body">
                    <pre class="bg-light p-3 rounded mb-0">{{ json_encode($activityLog->context_payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </div>
        </div>
    </div>
@endsection
