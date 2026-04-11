<?php $page = $page ?? 'module'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="mb-1">{{ $title }}</h4>
                    <p class="mb-0 text-muted">{{ $description }}</p>
                </div>

                @if(($workspaceProducts ?? collect())->count() > 1)
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($workspaceProducts as $workspaceProduct)
                            <a
                                href="{{ route('automotive.admin.dashboard', ['workspace_product' => $workspaceProduct['product_code']]) }}"
                                class="btn {{ ($focusedWorkspaceProduct['product_code'] ?? null) === $workspaceProduct['product_code'] ? 'btn-primary' : 'btn-outline-white' }}"
                            >
                                {{ $workspaceProduct['product_name'] }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="row">
                <div class="col-xl-8 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Module Overview</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="text-muted small mb-1">Focused Product</div>
                                <h5 class="mb-1">{{ $focusedWorkspaceProduct['product_name'] ?? 'Workspace Product' }}</h5>
                                <p class="mb-0 text-muted">
                                    {{ $focusedWorkspaceProduct['plan_name'] ?? 'No plan mapped yet' }} ·
                                    {{ !empty($focusedWorkspaceProduct['is_accessible']) ? 'Connected' : ($focusedWorkspaceProduct['status_label'] ?? 'Unavailable') }}
                                </p>
                            </div>

                            @if(!empty($focusedWorkspaceProduct['capabilities']))
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach($focusedWorkspaceProduct['capabilities'] as $capabilityName)
                                        <span class="badge bg-primary-subtle text-primary border">{{ $capabilityName }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Quick Links</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                @foreach($links as $link)
                                    <a href="{{ route($link['route'], $workspaceQuery) }}" class="btn btn-outline-light text-start">
                                        <i class="isax {{ $link['icon'] }} me-2"></i>{{ $link['label'] }}
                                    </a>
                                @endforeach
                            </div>
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

            @if(($page ?? '') === 'workshop-operations')
                <div class="row">
                    <div class="col-xl-5 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Create Work Order</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="{{ route('automotive.admin.modules.workshop-operations.work-orders.store', $workspaceQuery) }}">
                                    @csrf
                                    <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'automotive_service') }}">

                                    <div class="mb-3">
                                        <label class="form-label">Branch</label>
                                        <select name="branch_id" class="form-select">
                                            @foreach(($moduleData['active_branches'] ?? collect()) as $branch)
                                                <option value="{{ $branch->id }}" {{ (string) old('branch_id') === (string) $branch->id ? 'selected' : '' }}>
                                                    {{ $branch->name }} ({{ $branch->code }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Work Order Title</label>
                                        <input type="text" name="title" class="form-control" value="{{ old('title') }}" placeholder="Brake service, engine inspection, etc.">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Notes</label>
                                        <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
                                    </div>

                                    <button type="submit" class="btn btn-primary">Create Work Order</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-5 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Consume Spare Part In Workshop</h5>
                            </div>
                            <div class="card-body">
                                @if(empty($moduleData['has_connected_parts_workspace']))
                                    <p class="text-muted mb-0">Connect a Spare Parts product to this tenant workspace before workshop operations can consume stock.</p>
                                @elseif(($moduleData['available_stock_items'] ?? collect())->isEmpty())
                                    <p class="text-muted mb-0">No available stock items were found yet in the connected Spare Parts workspace.</p>
                                @elseif(($moduleData['open_work_orders'] ?? collect())->isEmpty())
                                    <p class="text-muted mb-0">Create a work order first before consuming spare parts in workshop operations.</p>
                                @else
                                    <form method="POST" action="{{ route('automotive.admin.modules.workshop-operations.consume-part', $workspaceQuery) }}">
                                        @csrf
                                        <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'automotive_service') }}">

                                        <div class="mb-3">
                                            <label class="form-label">Work Order</label>
                                            <select name="work_order_id" class="form-select">
                                                @foreach(($moduleData['open_work_orders'] ?? collect()) as $workOrder)
                                                    <option value="{{ $workOrder->id }}" {{ (string) old('work_order_id') === (string) $workOrder->id ? 'selected' : '' }}>
                                                        {{ $workOrder->work_order_number }} - {{ $workOrder->title }} ({{ $workOrder->branch?->name }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Stock Item</label>
                                            <select name="product_id" class="form-select">
                                                @foreach(($moduleData['available_stock_items'] ?? collect()) as $stockItem)
                                                    <option value="{{ $stockItem->product_id }}" {{ (string) old('product_id') === (string) $stockItem->product_id ? 'selected' : '' }}>
                                                        {{ $stockItem->product_name }} ({{ $stockItem->product_sku }}) - {{ rtrim(rtrim((string) $stockItem->quantity, '0'), '.') }} {{ $stockItem->product_unit }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Branch</label>
                                            <select name="branch_id" class="form-select">
                                                @foreach(($moduleData['available_stock_items'] ?? collect())->unique('branch_id') as $stockItem)
                                                    <option value="{{ $stockItem->branch_id }}" {{ (string) old('branch_id') === (string) $stockItem->branch_id ? 'selected' : '' }}>
                                                        {{ $stockItem->branch_name }} ({{ $stockItem->branch_code }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Quantity</label>
                                            <input type="number" step="0.001" min="0.001" name="quantity" class="form-control" value="{{ old('quantity', 1) }}">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Notes</label>
                                            <textarea name="notes" class="form-control" rows="3">{{ old('notes', 'Consumed by workshop operations') }}</textarea>
                                        </div>

                                        <button type="submit" class="btn btn-primary">Consume Stock</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-7 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Recent Work Orders</h5>
                            </div>
                            <div class="card-body">
                                @forelse(($moduleData['recent_work_orders'] ?? collect()) as $workOrder)
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">{{ $workOrder->work_order_number }}</h6>
                                                <div class="text-muted small">{{ $workOrder->title }}</div>
                                                <div class="text-muted small">{{ $workOrder->branch?->name ?? '—' }} · {{ $workOrder->creator?->name ?? 'System user' }}</div>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge {{ in_array($workOrder->status, ['open', 'in_progress'], true) ? 'bg-success' : 'bg-secondary' }}">
                                                    {{ strtoupper(str_replace('_', ' ', $workOrder->status)) }}
                                                </span>
                                                <div class="mt-2">
                                                    <a href="{{ route('automotive.admin.modules.workshop-operations.work-orders.show', ['workOrder' => $workOrder->id] + $workspaceQuery) }}" class="btn btn-sm btn-outline-light">
                                                        Open Record
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">No work orders have been created yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-12 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Available Spare Parts Stock</h5>
                            </div>
                            <div class="card-body">
                                @forelse(($moduleData['available_stock_items'] ?? collect()) as $stockItem)
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">{{ $stockItem->product_name }}</h6>
                                                <div class="text-muted small">{{ $stockItem->product_sku }} · {{ $stockItem->branch_name }} ({{ $stockItem->branch_code }})</div>
                                            </div>
                                            <span class="badge bg-success">{{ rtrim(rtrim((string) $stockItem->quantity, '0'), '.') }} {{ $stockItem->product_unit }}</span>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">No stock snapshot is available yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-12 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Recent Workshop Consumptions</h5>
                            </div>
                            <div class="card-body">
                                @forelse(($moduleData['recent_workshop_consumptions'] ?? collect()) as $movement)
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">{{ $movement->product_name }}</h6>
                                                <div class="text-muted small">{{ $movement->product_sku }} · {{ $movement->branch_name }}</div>
                                                <div class="text-muted small">
                                                    {{ $movement->work_order_number ?: 'No work order' }}
                                                    @if(!empty($movement->work_order_title))
                                                        · {{ $movement->work_order_title }}
                                                    @endif
                                                </div>
                                                <div class="text-muted small">{{ $movement->creator_name ?: 'System user' }} · {{ optional($movement->movement_date)->format('Y-m-d H:i') }}</div>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-warning text-dark">{{ rtrim(rtrim((string) $movement->quantity, '0'), '.') }}</span>
                                                <div class="text-muted small mt-1">{{ $movement->notes ?: 'Workshop consumption' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">No workshop stock consumption has been recorded yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @include('automotive.admin.components.page-footer')
        </div>
    </div>
@endsection
