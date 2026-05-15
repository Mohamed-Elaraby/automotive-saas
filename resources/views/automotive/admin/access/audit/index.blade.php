<?php $page = 'access-control'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex d-block align-items-center justify-content-between flex-wrap gap-3 mb-3">
                <div>
                    <h4 class="mb-1">Access Audit Logs</h4>
                    <p class="mb-0 text-muted">Trace access-control changes, permission denials, and owner sync activity.</p>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                    <a href="{{ route('automotive.admin.access.diagnostics.index') }}" class="btn btn-outline-white d-inline-flex align-items-center">
                        <i class="isax isax-search-status me-1"></i>Diagnostics
                    </a>
                    <a href="{{ route('automotive.admin.access.index') }}" class="btn btn-primary d-flex align-items-center">
                        <i class="isax isax-arrow-left me-1"></i>Back to Access
                    </a>
                </div>
            </div>

            <div class="row g-3 mb-3">
                @foreach([
                    ['label' => 'Total Events', 'value' => $summary['total'] ?? 0, 'icon' => 'isax-document-text', 'class' => 'bg-primary-transparent text-primary'],
                    ['label' => 'Forbidden', 'value' => $summary['forbidden'] ?? 0, 'icon' => 'isax-shield-cross', 'class' => 'bg-danger-transparent text-danger'],
                    ['label' => 'Role Changes', 'value' => $summary['role_changes'] ?? 0, 'icon' => 'isax-shield-tick', 'class' => 'bg-warning-transparent text-warning'],
                    ['label' => 'Product Access', 'value' => $summary['product_access_changes'] ?? 0, 'icon' => 'isax-user-tick', 'class' => 'bg-success-transparent text-success'],
                ] as $metric)
                    <div class="col-xl-3 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div>
                                    <p class="text-muted mb-1">{{ $metric['label'] }}</p>
                                    <h4 class="mb-0">{{ $metric['value'] }}</h4>
                                </div>
                                <span class="avatar avatar-md rounded-circle {{ $metric['class'] }}">
                                    <i class="isax {{ $metric['icon'] }}"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" action="{{ route('automotive.admin.access.audit.index') }}" class="row g-3 align-items-end">
                        <div class="col-xl-2 col-md-6">
                            <label class="form-label">Actor</label>
                            <select name="actor_user_id" class="form-select">
                                <option value="">Any actor</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" @selected(($filters['actor_user_id'] ?? '') == $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-6">
                            <label class="form-label">Target</label>
                            <select name="target_user_id" class="form-select">
                                <option value="">Any target</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" @selected(($filters['target_user_id'] ?? '') == $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-6">
                            <label class="form-label">Product</label>
                            <select name="product_key" class="form-select">
                                <option value="">Any product</option>
                                @foreach($products as $product)
                                    <option value="{{ $product }}" @selected(($filters['product_key'] ?? '') === $product)>{{ $product }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-6">
                            <label class="form-label">Branch</label>
                            <select name="branch_id" class="form-select">
                                <option value="">Any branch</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? '') == $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-2 col-md-6">
                            <label class="form-label">Event</label>
                            <select name="event_key" class="form-select">
                                <option value="">Any event</option>
                                @foreach($eventOptions as $event)
                                    <option value="{{ $event }}" @selected(($filters['event_key'] ?? '') === $event)>{{ $event }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-1 col-md-6">
                            <label class="form-label">From</label>
                            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
                        </div>
                        <div class="col-xl-1 col-md-6">
                            <label class="form-label">To</label>
                            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary d-inline-flex align-items-center">
                                <i class="isax isax-filter-search me-1"></i>Filter
                            </button>
                            <a href="{{ route('automotive.admin.access.audit.index') }}" class="btn btn-outline-white">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive table-nowrap">
                        <table class="table border mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Action</th>
                                    <th>Actor</th>
                                    <th>Target</th>
                                    <th>Product</th>
                                    <th>Branch</th>
                                    <th>Metadata</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $log)
                                    <tr>
                                        <td>
                                            <span class="badge {{ $log->event_key === 'forbidden_action.blocked' ? 'bg-danger' : 'bg-light text-dark' }}">{{ $log->event_key }}</span>
                                        </td>
                                        <td>{{ $log->actor?->name ?? 'System' }}</td>
                                        <td>{{ $log->targetUser?->name ?? '-' }}</td>
                                        <td>{{ $log->product_key ?? '-' }}</td>
                                        <td>{{ $log->branch?->name ?? '-' }}</td>
                                        <td class="text-muted small">
                                            @if($log->metadata)
                                                <code>{{ \Illuminate\Support\Str::limit(json_encode($log->metadata, JSON_UNESCAPED_SLASHES), 90) }}</code>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">No audit logs found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($logs->hasPages())
                    <div class="card-footer">
                        {{ $logs->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
