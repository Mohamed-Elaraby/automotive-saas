@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="mb-1">{{ $workOrder->work_order_number }}</h4>
                    <p class="mb-0 text-muted">{{ $workOrder->title }}</p>
                </div>

                <div class="d-flex gap-2">
                    <a href="{{ route('automotive.admin.modules.workshop-operations', $workspaceQuery) }}" class="btn btn-outline-light">
                        Back To Workshop Operations
                    </a>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-8 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Work Order Overview</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="text-muted small mb-1">Focused Product</div>
                                    <div>{{ $focusedWorkspaceProduct['product_name'] ?? 'Workspace Product' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small mb-1">Status</div>
                                    <span class="badge {{ in_array($workOrder->status, ['open', 'in_progress'], true) ? 'bg-success' : 'bg-secondary' }}">
                                        {{ strtoupper(str_replace('_', ' ', $workOrder->status)) }}
                                    </span>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small mb-1">Branch</div>
                                    <div>{{ $workOrder->branch?->name ?? '—' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small mb-1">Created By</div>
                                    <div>{{ $workOrder->creator?->name ?? 'System user' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small mb-1">Customer</div>
                                    <div>{{ $workOrder->customer?->name ?? '—' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small mb-1">Vehicle</div>
                                    <div>
                                        @if($workOrder->vehicle)
                                            {{ $workOrder->vehicle->make }} {{ $workOrder->vehicle->model }}{{ $workOrder->vehicle->plate_number ? ' · '.$workOrder->vehicle->plate_number : '' }}
                                        @else
                                            —
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small mb-1">Opened At</div>
                                    <div>{{ optional($workOrder->opened_at)->format('Y-m-d H:i') ?: '—' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small mb-1">Closed At</div>
                                    <div>{{ optional($workOrder->closed_at)->format('Y-m-d H:i') ?: '—' }}</div>
                                </div>
                                <div class="col-12">
                                    <div class="text-muted small mb-1">Notes</div>
                                    <div>{{ $workOrder->notes ?: 'No notes provided.' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Update Status</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('automotive.admin.modules.workshop-operations.work-orders.status', ['workOrder' => $workOrder->id] + $workspaceQuery) }}">
                                @csrf
                                <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? 'automotive_service' }}">

                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        @foreach(['open' => 'Open', 'in_progress' => 'In Progress', 'completed' => 'Completed'] as $value => $label)
                                            <option value="{{ $value }}" {{ old('status', $workOrder->status) === $value ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-primary">Save Status</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            @if(!empty($workspaceIntegrations))
                <div class="row">
                    <div class="col-xl-12 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Connected Product Integrations</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    @foreach($workspaceIntegrations as $integration)
                                        <div class="col-xl-6 d-flex">
                                            <div class="border rounded p-3 flex-fill mb-3">
                                                <h6 class="mb-2">{{ $integration['title'] }}</h6>
                                                <p class="text-muted mb-3">{{ $integration['description'] }}</p>
                                                <a href="{{ route($integration['target_route'], $integration['target_params']) }}" class="btn btn-outline-light">
                                                    {{ $integration['target_label'] }}
                                                </a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="row">
                <div class="col-xl-12 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Consumed Spare Parts</h5>
                        </div>
                        <div class="card-body">
                            @forelse($consumptions as $movement)
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">{{ $movement->product_name }}</h6>
                                            <div class="text-muted small">{{ $movement->product_sku }} · {{ $movement->branch_name }}</div>
                                            <div class="text-muted small">{{ $movement->creator_name ?: 'System user' }} · {{ optional($movement->movement_date)->format('Y-m-d H:i') }}</div>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-warning text-dark">{{ rtrim(rtrim((string) $movement->quantity, '0'), '.') }}</span>
                                            <div class="text-muted small mt-1">{{ $movement->notes ?: 'Workshop consumption' }}</div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted mb-0">No spare parts have been consumed for this work order yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            @include('automotive.admin.components.page-footer')
        </div>
    </div>
@endsection
