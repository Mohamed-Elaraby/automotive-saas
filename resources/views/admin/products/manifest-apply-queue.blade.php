<?php $page = 'products-manifest-apply-queue'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>Manifest Apply Queue</h5>
                    <p class="text-muted mb-0">Track the actual code writeback and runtime wiring execution for <strong>{{ $product->name }}</strong>.</p>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('admin.products.manifest-sync.show', $product) }}" class="btn btn-outline-white">Back to Manifest Sync</a>
                    <a href="{{ route('admin.products.show', $product) }}" class="btn btn-outline-white">Back to Product Builder</a>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="row g-4">
                <div class="col-xl-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="mb-3">Execution Readiness</h6>
                            <div class="list-group mb-4">
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Manifest Workflow Approved</span>
                                    <span class="badge {{ $readiness['workflow_approved'] ? 'bg-success' : 'bg-warning text-dark' }}">{{ $readiness['workflow_approved'] ? 'Ready' : 'Pending' }}</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Snapshot Captured</span>
                                    <span class="badge {{ $readiness['snapshot_available'] ? 'bg-success' : 'bg-warning text-dark' }}">{{ $readiness['snapshot_available'] ? 'Ready' : 'Missing' }}</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Owner Assigned</span>
                                    <span class="badge {{ $readiness['owner_assigned'] ? 'bg-success' : 'bg-warning text-dark' }}">{{ $readiness['owner_assigned'] ? 'Ready' : 'Missing' }}</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Execution Started</span>
                                    <span class="badge {{ $readiness['status_started'] ? 'bg-success' : 'bg-secondary' }}">{{ $readiness['status_started'] ? 'Yes' : 'Not Yet' }}</span>
                                </div>
                            </div>

                            @if($validationBlockers !== [])
                                <div class="alert alert-warning">
                                    <ul class="mb-0 ps-3">
                                        @foreach($validationBlockers as $blocker)
                                            <li>{{ $blocker }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <h6 class="mb-3">Integration Governance</h6>
                            <div class="list-group mb-4">
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Contracts Ready</span>
                                    <span class="badge {{ ($integrationGovernance['ready'] ?? false) ? 'bg-success' : 'bg-danger' }}">{{ ($integrationGovernance['ready'] ?? false) ? 'Ready' : 'Blocked' }}</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Contract Count</span>
                                    <span class="badge bg-light text-dark">{{ $integrationGovernance['summary']['contract_count'] ?? 0 }}</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Event Count</span>
                                    <span class="badge bg-light text-dark">{{ $integrationGovernance['summary']['event_count'] ?? 0 }}</span>
                                </div>
                            </div>

                            @if(($integrationGovernance['blockers'] ?? []) !== [])
                                <div class="alert alert-danger">
                                    <ul class="mb-0 ps-3">
                                        @foreach($integrationGovernance['blockers'] as $blocker)
                                            <li>{{ $blocker }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <h6 class="mb-2">Manifest Workflow</h6>
                            <div class="small text-muted mb-1">Status: <strong>{{ strtoupper((string) ($workflow['status'] ?? 'draft')) }}</strong></div>
                            <div class="small text-muted mb-1">Reviewed At: <strong>{{ $workflow['reviewed_at'] ?? '-' }}</strong></div>
                            <div class="small text-muted">Notes: {{ $workflow['notes'] ?? '-' }}</div>

                            @if($latestSnapshot !== [])
                                <hr>
                                <h6 class="mb-2">Latest Approved Snapshot</h6>
                                <div class="small text-muted mb-1">Status: <strong>{{ strtoupper((string) ($latestSnapshot['status'] ?? 'draft')) }}</strong></div>
                                <div class="small text-muted mb-1">Family: <strong>{{ $latestSnapshot['family_key'] ?? '-' }}</strong></div>
                                <div class="small text-muted mb-1">Captured At: <strong>{{ $latestSnapshot['captured_at'] ?? '-' }}</strong></div>
                                <div class="small text-muted">Payload Sections: {{ count((array) ($latestSnapshot['payload'] ?? [])) }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-xl-8">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="mb-3">Execution Queue</h6>
                            <form method="POST" action="{{ route('admin.products.manifest-apply-queue.update', $product) }}">
                                @csrf
                                @method('PUT')

                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Queue Status</label>
                                        <select name="status" class="form-select">
                                            <option value="queued" @selected(($queue['status'] ?? 'queued') === 'queued')>Queued</option>
                                            <option value="in_progress" @selected(($queue['status'] ?? '') === 'in_progress')>In Progress</option>
                                            <option value="blocked" @selected(($queue['status'] ?? '') === 'blocked')>Blocked</option>
                                            <option value="done" @selected(($queue['status'] ?? '') === 'done')>Done</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Owner Name</label>
                                        <input type="text" name="owner_name" class="form-control" value="{{ old('owner_name', $queue['owner_name'] ?? '') }}" placeholder="Workspace platform owner">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Owner Contact</label>
                                        <input type="text" name="owner_contact" class="form-control" value="{{ old('owner_contact', $queue['owner_contact'] ?? '') }}" placeholder="email or Slack handle">
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label">Blocking Reason</label>
                                        <textarea name="blocking_reason" rows="3" class="form-control" placeholder="Why is this blocked or what dependency is still missing?">{{ old('blocking_reason', $queue['blocking_reason'] ?? '') }}</textarea>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label">Implementation Notes</label>
                                        <textarea name="implementation_notes" rows="5" class="form-control" placeholder="Config edits, runtime routes, sidebar wiring, and follow-up tasks.">{{ old('implementation_notes', $queue['implementation_notes'] ?? '') }}</textarea>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label">Deployment Notes</label>
                                        <textarea name="deployment_notes" rows="4" class="form-control" placeholder="Post-merge validation, rollout notes, and smoke checks.">{{ old('deployment_notes', $queue['deployment_notes'] ?? '') }}</textarea>
                                    </div>
                                </div>

                                <hr>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-3">
                                        <div class="border rounded p-3 h-100">
                                            <div class="small text-muted mb-1">Queued At</div>
                                            <div class="fw-semibold">{{ $queue['queued_at'] ?? '-' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border rounded p-3 h-100">
                                            <div class="small text-muted mb-1">Started At</div>
                                            <div class="fw-semibold">{{ $queue['started_at'] ?? '-' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border rounded p-3 h-100">
                                            <div class="small text-muted mb-1">Completed At</div>
                                            <div class="fw-semibold">{{ $queue['completed_at'] ?? '-' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border rounded p-3 h-100">
                                            <div class="small text-muted mb-1">Last Updated</div>
                                            <div class="fw-semibold">{{ $queue['updated_at'] ?? '-' }}</div>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">Save Queue State</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
