@php($page = 'work-order-show')
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
                            <h5 class="card-title mb-0">Financial Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Labor Subtotal</span>
                                <strong>{{ number_format($summary['labor_subtotal'] ?? 0, 2) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Parts Subtotal</span>
                                <strong>{{ number_format($summary['parts_subtotal'] ?? 0, 2) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Lines Count</span>
                                <strong>{{ $summary['lines_count'] ?? 0 }}</strong>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <span>Grand Total</span>
                                <strong>{{ number_format($summary['grand_total'] ?? 0, 2) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-12 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Accounting Handoff</h5>
                        </div>
                        <div class="card-body">
                            @if($accountingEvent)
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">{{ $accountingEvent->event_type }}</h6>
                                        <div class="text-muted small">{{ optional($accountingEvent->event_date)->format('Y-m-d H:i') ?: '—' }}</div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-info">{{ strtoupper($accountingEvent->status) }}</span>
                                        <div class="text-muted small mt-1">{{ number_format((float) $accountingEvent->total_amount, 2) }} {{ $accountingEvent->currency }}</div>
                                    </div>
                                </div>
                            @else
                                <p class="text-muted mb-0">No local accounting event has been posted for this work order yet.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
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

                <div class="col-xl-8 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Add Labor / Service Line</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('automotive.admin.modules.workshop-operations.work-orders.labor-lines.store', ['workOrder' => $workOrder->id] + $workspaceQuery) }}">
                                @csrf
                                <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? 'automotive_service' }}">

                                <div class="row">
                                    <div class="col-md-5 mb-3">
                                        <label class="form-label">Description</label>
                                        <input type="text" name="description" class="form-control" value="{{ old('description') }}" placeholder="Brake inspection, oil change labor, etc.">
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <label class="form-label">Qty</label>
                                        <input type="number" step="0.001" min="0.001" name="quantity" class="form-control" value="{{ old('quantity', 1) }}">
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <label class="form-label">Unit Price</label>
                                        <input type="number" step="0.01" min="0" name="unit_price" class="form-control" value="{{ old('unit_price', 0) }}">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Notes</label>
                                        <input type="text" name="notes" class="form-control" value="{{ old('notes') }}">
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-outline-primary">Add Labor Line</button>
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
                            <h5 class="card-title mb-0">Work Order Lines</h5>
                        </div>
                        <div class="card-body">
                            @forelse($lines as $line)
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">{{ $line->description }}</h6>
                                            <div class="text-muted small">
                                                {{ strtoupper($line->line_type) }}
                                                @if(!empty($line->product_sku))
                                                    · {{ $line->product_sku }}
                                                @endif
                                                · {{ $line->creator_name ?? 'System user' }}
                                            </div>
                                            @if($line->notes)
                                                <div class="text-muted small">{{ $line->notes }}</div>
                                            @endif
                                        </div>
                                        <div class="text-end">
                                            <div class="text-muted small">{{ rtrim(rtrim((string) $line->quantity, '0'), '.') }} × {{ number_format((float) $line->unit_price, 2) }}</div>
                                            <strong>{{ number_format((float) $line->total_price, 2) }}</strong>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted mb-0">No work-order lines have been added yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

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
