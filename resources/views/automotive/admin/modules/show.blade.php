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

            @include('automotive.admin.partials.workspace-integrations', [
                'title' => 'Connected Product Integrations',
                'columnClass' => 'col-xl-6',
            ])

            @if(($page ?? '') === 'workshop-operations')
                <div class="row">
                    <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">Customers</div><h4 class="mb-1">{{ ($moduleData['customers'] ?? collect())->count() }}</h4><p class="mb-0 text-muted">Workshop customer records ready for intake.</p></div></div></div>
                    <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">Vehicles</div><h4 class="mb-1">{{ ($moduleData['vehicles'] ?? collect())->count() }}</h4><p class="mb-0 text-muted">Vehicles linked to service history.</p></div></div></div>
                    <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">Open Work Orders</div><h4 class="mb-1">{{ ($moduleData['open_work_orders'] ?? collect())->count() }}</h4><p class="mb-0 text-muted">Jobs currently in the workshop flow.</p></div></div></div>
                    <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">Accounting Handoffs</div><h4 class="mb-1">{{ ($moduleData['recent_accounting_events'] ?? collect())->count() }}</h4><p class="mb-0 text-muted">Completed jobs posted to accounting.</p></div></div></div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body py-3">
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="{{ route('automotive.admin.modules.workshop-customers', $workspaceQuery) }}" class="btn btn-outline-light">Customers Table</a>
                                    <a href="{{ route('automotive.admin.modules.workshop-vehicles', $workspaceQuery) }}" class="btn btn-outline-light">Vehicles Table</a>
                                    <a href="{{ route('automotive.admin.modules.workshop-work-orders', $workspaceQuery) }}" class="btn btn-outline-light">Work Orders Table</a>
                                    <a href="{{ route('automotive.admin.modules.general-ledger', ['workspace_product' => 'accounting']) }}" class="btn btn-outline-light">Accounting Events</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-4 d-flex">
                        <div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">Step 1: Create Customer</h5></div><div class="card-body"><form method="POST" action="{{ route('automotive.admin.modules.workshop-operations.customers.store', $workspaceQuery) }}">@csrf<input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'automotive_service') }}"><div class="mb-3"><label class="form-label">Customer Name</label><input type="text" name="name" class="form-control" value="{{ old('name') }}"></div><div class="mb-3"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="{{ old('phone') }}"></div><div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email') }}"></div><button type="submit" class="btn btn-outline-primary">Create Customer</button></form></div></div>
                    </div>
                    <div class="col-xl-4 d-flex">
                        <div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">Step 2: Register Vehicle</h5></div><div class="card-body">@if(($moduleData['customers'] ?? collect())->isEmpty())<p class="text-muted mb-0">Create a customer first before registering a vehicle.</p>@else<form method="POST" action="{{ route('automotive.admin.modules.workshop-operations.vehicles.store', $workspaceQuery) }}">@csrf<input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'automotive_service') }}"><div class="mb-3"><label class="form-label">Customer</label><select name="customer_id" class="form-select">@foreach(($moduleData['customers'] ?? collect()) as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}</option>@endforeach</select></div><div class="mb-3"><label class="form-label">Make</label><input type="text" name="make" class="form-control" value="{{ old('make') }}"></div><div class="mb-3"><label class="form-label">Model</label><input type="text" name="model" class="form-control" value="{{ old('model') }}"></div><div class="row"><div class="col-md-6 mb-3"><label class="form-label">Year</label><input type="number" name="year" class="form-control" value="{{ old('year') }}"></div><div class="col-md-6 mb-3"><label class="form-label">Plate Number</label><input type="text" name="plate_number" class="form-control" value="{{ old('plate_number') }}"></div></div><div class="mb-3"><label class="form-label">VIN</label><input type="text" name="vin" class="form-control" value="{{ old('vin') }}"></div><button type="submit" class="btn btn-outline-primary">Create Vehicle</button></form>@endif</div></div>
                    </div>
                    <div class="col-xl-4 d-flex">
                        <div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">Step 3: Create Work Order</h5></div><div class="card-body"><form method="POST" action="{{ route('automotive.admin.modules.workshop-operations.work-orders.store', $workspaceQuery) }}">@csrf<input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'automotive_service') }}"><div class="mb-3"><label class="form-label">Branch</label><select name="branch_id" class="form-select">@foreach(($moduleData['active_branches'] ?? collect()) as $branch)<option value="{{ $branch->id }}" {{ (string) old('branch_id') === (string) $branch->id ? 'selected' : '' }}>{{ $branch->name }} ({{ $branch->code }})</option>@endforeach</select></div><div class="mb-3"><label class="form-label">Customer</label><select name="customer_id" class="form-select"><option value="">No customer linked yet</option>@foreach(($moduleData['customers'] ?? collect()) as $customer)<option value="{{ $customer->id }}" {{ (string) old('customer_id') === (string) $customer->id ? 'selected' : '' }}>{{ $customer->name }}{{ $customer->phone ? ' · '.$customer->phone : '' }}</option>@endforeach</select></div><div class="mb-3"><label class="form-label">Vehicle</label><select name="vehicle_id" class="form-select"><option value="">No vehicle linked yet</option>@foreach(($moduleData['vehicles'] ?? collect()) as $vehicle)<option value="{{ $vehicle->id }}" {{ (string) old('vehicle_id') === (string) $vehicle->id ? 'selected' : '' }}>{{ $vehicle->make }} {{ $vehicle->model }}{{ $vehicle->plate_number ? ' · '.$vehicle->plate_number : '' }}{{ $vehicle->customer ? ' · '.$vehicle->customer->name : '' }}</option>@endforeach</select></div><div class="mb-3"><label class="form-label">Work Order Title</label><input type="text" name="title" class="form-control" value="{{ old('title') }}" placeholder="Brake service, engine inspection, etc."></div><div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea></div><button type="submit" class="btn btn-primary">Create Work Order</button></form></div></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-5 d-flex">
                        <div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">Step 4: Consume Spare Parts</h5></div><div class="card-body">@if(empty($moduleData['has_connected_parts_workspace']))<p class="text-muted mb-0">Connect a Spare Parts product to this tenant workspace before workshop operations can consume stock.</p>@elseif(($moduleData['available_stock_items'] ?? collect())->isEmpty())<p class="text-muted mb-0">No available stock items were found yet in the connected Spare Parts workspace.</p>@elseif(($moduleData['open_work_orders'] ?? collect())->isEmpty())<p class="text-muted mb-0">Create a work order first before consuming spare parts in workshop operations.</p>@else<form method="POST" action="{{ route('automotive.admin.modules.workshop-operations.consume-part', $workspaceQuery) }}">@csrf<input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'automotive_service') }}"><div class="mb-3"><label class="form-label">Work Order</label><select name="work_order_id" class="form-select">@foreach(($moduleData['open_work_orders'] ?? collect()) as $workOrder)<option value="{{ $workOrder->id }}" {{ (string) old('work_order_id') === (string) $workOrder->id ? 'selected' : '' }}>{{ $workOrder->work_order_number }} - {{ $workOrder->title }} ({{ $workOrder->branch?->name }})</option>@endforeach</select></div><div class="mb-3"><label class="form-label">Stock Item</label><select name="product_id" class="form-select">@foreach(($moduleData['available_stock_items'] ?? collect()) as $stockItem)<option value="{{ $stockItem->product_id }}" {{ (string) old('product_id') === (string) $stockItem->product_id ? 'selected' : '' }}>{{ $stockItem->product_name }} ({{ $stockItem->product_sku }}) - {{ rtrim(rtrim((string) $stockItem->quantity, '0'), '.') }} {{ $stockItem->product_unit }}</option>@endforeach</select></div><div class="mb-3"><label class="form-label">Branch</label><select name="branch_id" class="form-select">@foreach(($moduleData['available_stock_items'] ?? collect())->unique('branch_id') as $stockItem)<option value="{{ $stockItem->branch_id }}" {{ (string) old('branch_id') === (string) $stockItem->branch_id ? 'selected' : '' }}>{{ $stockItem->branch_name }} ({{ $stockItem->branch_code }})</option>@endforeach</select></div><div class="mb-3"><label class="form-label">Quantity</label><input type="number" step="0.001" min="0.001" name="quantity" class="form-control" value="{{ old('quantity', 1) }}"></div><div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3">{{ old('notes', 'Consumed by workshop operations') }}</textarea></div><button type="submit" class="btn btn-primary">Consume Stock</button></form>@endif</div></div>
                    </div>
                    <div class="col-xl-7 d-flex">
                        <div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">Recent Work Orders</h5></div><div class="card-body">@forelse(($moduleData['recent_work_orders'] ?? collect()) as $workOrder)<div class="border-bottom pb-2 mb-2"><div class="d-flex justify-content-between align-items-start"><div><h6 class="mb-1">{{ $workOrder->work_order_number }}</h6><div class="text-muted small">{{ $workOrder->title }}</div><div class="text-muted small">{{ $workOrder->branch?->name ?? '—' }} · {{ $workOrder->creator?->name ?? 'System user' }}</div>@if($workOrder->customer || $workOrder->vehicle)<div class="text-muted small">{{ $workOrder->customer?->name ?: 'No customer' }}@if($workOrder->vehicle) · {{ $workOrder->vehicle->make }} {{ $workOrder->vehicle->model }}{{ $workOrder->vehicle->plate_number ? ' · '.$workOrder->vehicle->plate_number : '' }}@endif</div>@endif</div><div class="text-end"><span class="badge {{ in_array($workOrder->status, ['open', 'in_progress'], true) ? 'bg-success' : 'bg-secondary' }}">{{ strtoupper(str_replace('_', ' ', $workOrder->status)) }}</span><div class="mt-2"><a href="{{ route('automotive.admin.modules.workshop-operations.work-orders.show', ['workOrder' => $workOrder->id] + $workspaceQuery) }}" class="btn btn-sm btn-outline-light">Open Record</a></div></div></div></div>@empty<p class="text-muted mb-0">No work orders have been created yet.</p>@endforelse</div></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-6 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">Available Spare Parts Stock</h5></div><div class="card-body">@forelse(($moduleData['available_stock_items'] ?? collect()) as $stockItem)<div class="border-bottom pb-2 mb-2"><div class="d-flex justify-content-between align-items-start"><div><h6 class="mb-1">{{ $stockItem->product_name }}</h6><div class="text-muted small">{{ $stockItem->product_sku }} · {{ $stockItem->branch_name }} ({{ $stockItem->branch_code }})</div></div><span class="badge bg-success">{{ rtrim(rtrim((string) $stockItem->quantity, '0'), '.') }} {{ $stockItem->product_unit }}</span></div></div>@empty<p class="text-muted mb-0">No stock snapshot is available yet.</p>@endforelse</div></div></div>
                    <div class="col-xl-6 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">Recent Accounting Handoffs</h5></div><div class="card-body">@forelse(($moduleData['recent_accounting_events'] ?? collect()) as $event)<div class="border-bottom pb-2 mb-2"><div class="d-flex justify-content-between align-items-start"><div><h6 class="mb-1">{{ data_get($event->payload, 'work_order_number', 'Work Order') }}</h6><div class="text-muted small">{{ data_get($event->payload, 'title', 'Completed workshop job') }}</div><div class="text-muted small">{{ $event->creator_name ?: 'System user' }}</div></div><div class="text-end"><span class="badge bg-info">{{ strtoupper($event->status) }}</span><div class="text-muted small mt-1">{{ number_format((float) $event->total_amount, 2) }} {{ $event->currency }}</div></div></div></div>@empty<p class="text-muted mb-0">No completed work orders have been handed off to accounting yet.</p>@endforelse</div></div></div>
                </div>

                <div class="row">
                    <div class="col-xl-12 d-flex"><div class="card flex-fill"><div class="card-header"><h5 class="card-title mb-0">Recent Workshop Consumptions</h5></div><div class="card-body">@forelse(($moduleData['recent_workshop_consumptions'] ?? collect()) as $movement)<div class="border-bottom pb-2 mb-2"><div class="d-flex justify-content-between align-items-start"><div><h6 class="mb-1">{{ $movement->product_name }}</h6><div class="text-muted small">{{ $movement->product_sku }} · {{ $movement->branch_name }}</div><div class="text-muted small">{{ $movement->work_order_number ?: 'No work order' }}@if(!empty($movement->work_order_title)) · {{ $movement->work_order_title }}@endif</div><div class="text-muted small">{{ $movement->creator_name ?: 'System user' }} · {{ optional($movement->movement_date)->format('Y-m-d H:i') }}</div></div><div class="text-end"><span class="badge bg-warning text-dark">{{ rtrim(rtrim((string) $movement->quantity, '0'), '.') }}</span><div class="text-muted small mt-1">{{ $movement->notes ?: 'Workshop consumption' }}</div></div></div></div>@empty<p class="text-muted mb-0">No workshop stock consumption has been recorded yet.</p>@endforelse</div></div></div>
                </div>
            @elseif(($page ?? '') === 'workshop-customers')
                <div class="card"><div class="card-header"><h5 class="card-title mb-0">Customers Table</h5></div><div class="card-body">@forelse(($moduleData['customers'] ?? collect()) as $customer)<div class="border-bottom pb-2 mb-2"><h6 class="mb-1">{{ $customer->name }}</h6><div class="text-muted small">{{ $customer->phone ?: 'No phone' }}{{ $customer->email ? ' · '.$customer->email : '' }}</div></div>@empty<p class="text-muted mb-0">No workshop customers have been created yet.</p>@endforelse</div></div>
            @elseif(($page ?? '') === 'workshop-vehicles')
                <div class="card"><div class="card-header"><h5 class="card-title mb-0">Vehicles Table</h5></div><div class="card-body">@forelse(($moduleData['vehicles'] ?? collect()) as $vehicle)<div class="border-bottom pb-2 mb-2"><h6 class="mb-1">{{ $vehicle->make }} {{ $vehicle->model }}</h6><div class="text-muted small">{{ $vehicle->plate_number ?: 'No plate' }}{{ $vehicle->customer ? ' · '.$vehicle->customer->name : '' }}</div></div>@empty<p class="text-muted mb-0">No workshop vehicles have been registered yet.</p>@endforelse</div></div>
            @elseif(($page ?? '') === 'workshop-work-orders')
                <div class="card"><div class="card-header"><h5 class="card-title mb-0">Work Orders Table</h5></div><div class="card-body">@forelse(($moduleData['recent_work_orders'] ?? collect()) as $workOrder)<div class="border-bottom pb-2 mb-2"><div class="d-flex justify-content-between align-items-start"><div><h6 class="mb-1">{{ $workOrder->work_order_number }}</h6><div class="text-muted small">{{ $workOrder->title }}</div><div class="text-muted small">{{ $workOrder->customer?->name ?: 'No customer' }}{{ $workOrder->vehicle ? ' · '.$workOrder->vehicle->make.' '.$workOrder->vehicle->model : '' }}</div></div><div class="text-end"><span class="badge {{ in_array($workOrder->status, ['open', 'in_progress'], true) ? 'bg-success' : 'bg-secondary' }}">{{ strtoupper(str_replace('_', ' ', $workOrder->status)) }}</span><div class="mt-2"><a href="{{ route('automotive.admin.modules.workshop-operations.work-orders.show', ['workOrder' => $workOrder->id] + $workspaceQuery) }}" class="btn btn-sm btn-outline-light">Open Record</a></div></div></div></div>@empty<p class="text-muted mb-0">No work orders have been created yet.</p>@endforelse</div></div>
            @elseif(($page ?? '') === 'general-ledger')
                @php($journalFilters = $moduleData['journal_filters'] ?? [])
                <div class="row">
                    <div class="col-xl-4 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">Posting Groups</div><h4 class="mb-1">{{ ($moduleData['posting_groups'] ?? collect())->count() }}</h4><p class="mb-0 text-muted">Account mapping rules available for journal posting.</p></div></div></div>
                    <div class="col-xl-4 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">Events To Review</div><h4 class="mb-1">{{ ($moduleData['reviewable_accounting_events'] ?? collect())->count() }}</h4><p class="mb-0 text-muted">Accounting events not yet posted to journal.</p></div></div></div>
                    <div class="col-xl-4 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><div class="text-muted small mb-1">Journal Entries</div><h4 class="mb-1">{{ ($moduleData['recent_journal_entries'] ?? collect())->count() }}</h4><p class="mb-0 text-muted">Posted accounting entries in this workspace.</p></div></div></div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Journal Filters</h5></div>
                    <div class="card-body">
                        <form method="GET" action="{{ route('automotive.admin.modules.general-ledger') }}">
                            <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                            <div class="row align-items-end">
                                <div class="col-md-3 mb-3"><label class="form-label">Search</label><input type="text" name="search" class="form-control" value="{{ $journalFilters['search'] ?? '' }}" placeholder="Journal, memo, account"></div>
                                <div class="col-md-2 mb-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">Any</option><option value="posted" @selected(($journalFilters['status'] ?? '') === 'posted')>Posted</option><option value="reversed" @selected(($journalFilters['status'] ?? '') === 'reversed')>Reversed</option></select></div>
                                <div class="col-md-2 mb-3"><label class="form-label">From</label><input type="date" name="date_from" class="form-control" value="{{ $journalFilters['date_from'] ?? '' }}"></div>
                                <div class="col-md-2 mb-3"><label class="form-label">To</label><input type="date" name="date_to" class="form-control" value="{{ $journalFilters['date_to'] ?? '' }}"></div>
                                <div class="col-md-3 mb-3 d-flex gap-2"><button type="submit" class="btn btn-primary">Apply Filters</button><a href="{{ route('automotive.admin.modules.general-ledger', $workspaceQuery) }}" class="btn btn-outline-light">Reset</a></div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-5 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header"><h5 class="card-title mb-0">Integration Contracts</h5></div>
                            <div class="card-body">
                                @forelse(($moduleData['integration_contracts'] ?? collect()) as $contract)
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="d-flex justify-content-between gap-3">
                                            <div>
                                                <h6 class="mb-1">{{ $contract['title'] }}</h6>
                                                <div class="text-muted small">{{ $contract['source_family'] }} → {{ $contract['target_family'] }}</div>
                                                <div class="text-muted small">{{ implode(', ', $contract['events']) ?: 'No events declared' }}</div>
                                            </div>
                                            <span class="badge bg-primary-subtle text-primary border">{{ $contract['key'] }}</span>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">No integration contracts are declared yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-7 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header"><h5 class="card-title mb-0">Integration Handoff Diagnostics</h5></div>
                            <div class="card-body">
                                @forelse(($moduleData['recent_integration_handoffs'] ?? collect()) as $handoff)
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="d-flex justify-content-between align-items-start gap-3">
                                            <div>
                                                <h6 class="mb-1">{{ $handoff->event_name }}</h6>
                                                <div class="text-muted small">{{ $handoff->source_product }} → {{ $handoff->target_product ?: 'No target' }}</div>
                                                <div class="text-muted small">{{ $handoff->integration_key }} · Attempts {{ $handoff->attempts }}</div>
                                                @if($handoff->error_message)
                                                    <div class="text-danger small">{{ $handoff->error_message }}</div>
                                                @endif
                                            </div>
                                            <div class="text-end">
                                                <span class="badge {{ $handoff->status === 'posted' ? 'bg-success' : ($handoff->status === 'failed' ? 'bg-danger' : ($handoff->status === 'skipped' ? 'bg-warning text-dark' : 'bg-info')) }}">{{ strtoupper($handoff->status) }}</span>
                                                <div class="text-muted small mt-1">{{ optional($handoff->last_attempted_at)->format('Y-m-d H:i') ?: '-' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">No integration handoffs have been recorded yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-5 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header"><h5 class="card-title mb-0">Create Posting Group</h5></div>
                            <div class="card-body">
                                <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.posting-groups.store', $workspaceQuery) }}">
                                    @csrf
                                    <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                                    <div class="row">
                                        <div class="col-md-5 mb-3"><label class="form-label">Code</label><input type="text" name="code" class="form-control" value="{{ old('code', 'workshop_revenue') }}"></div>
                                        <div class="col-md-7 mb-3"><label class="form-label">Name</label><input type="text" name="name" class="form-control" value="{{ old('name', 'Workshop Revenue') }}"></div>
                                    </div>
                                    <div class="mb-3"><label class="form-label">Receivable Account</label><input type="text" name="receivable_account" class="form-control" value="{{ old('receivable_account', '1100 Accounts Receivable') }}"></div>
                                    <div class="mb-3"><label class="form-label">Labor Revenue Account</label><input type="text" name="labor_revenue_account" class="form-control" value="{{ old('labor_revenue_account', '4100 Service Labor Revenue') }}"></div>
                                    <div class="mb-3"><label class="form-label">Parts Revenue Account</label><input type="text" name="parts_revenue_account" class="form-control" value="{{ old('parts_revenue_account', '4200 Parts Revenue') }}"></div>
                                    <div class="row">
                                        <div class="col-md-4 mb-3"><label class="form-label">Currency</label><input type="text" name="currency" maxlength="3" class="form-control" value="{{ old('currency', 'USD') }}"></div>
                                        <div class="col-md-8 mb-3 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="posting_group_default" name="is_default" value="1" {{ old('is_default', '1') ? 'checked' : '' }}><label class="form-check-label" for="posting_group_default">Default group</label></div></div>
                                    </div>
                                    <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea></div>
                                    <button type="submit" class="btn btn-primary">Create Posting Group</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-7 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header"><h5 class="card-title mb-0">Posting Groups</h5></div>
                            <div class="card-body">
                                @forelse(($moduleData['posting_groups'] ?? collect()) as $group)
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="d-flex justify-content-between align-items-start gap-3">
                                            <div>
                                                <h6 class="mb-1">{{ $group->name }}</h6>
                                                <div class="text-muted small">{{ $group->code }} · {{ $group->currency }}</div>
                                                <div class="text-muted small">{{ $group->receivable_account }} / {{ $group->labor_revenue_account }} / {{ $group->parts_revenue_account }}</div>
                                            </div>
                                            <div class="text-end">
                                                @if($group->is_default)<span class="badge bg-primary">DEFAULT</span>@endif
                                                <span class="badge {{ $group->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $group->is_active ? 'ACTIVE' : 'INACTIVE' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">No posting groups have been configured yet. Posting an event can also create a default group automatically.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Accounting Event Review</h5></div>
                    <div class="card-body">
                        @forelse(($moduleData['reviewable_accounting_events'] ?? collect()) as $event)
                            <div class="border-bottom pb-3 mb-3">
                                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                    <div>
                                        <h6 class="mb-1">{{ data_get($event->payload, 'work_order_number', 'Accounting Event') }}</h6>
                                        <div class="text-muted small">{{ data_get($event->payload, 'title', $event->event_type) }}</div>
                                        <div class="text-muted small">{{ data_get($event->payload, 'customer_name', 'No customer') }}{{ data_get($event->payload, 'vehicle') ? ' · '.data_get($event->payload, 'vehicle') : '' }}</div>
                                        <div class="text-muted small">Labor {{ number_format((float) $event->labor_amount, 2) }} · Parts {{ number_format((float) $event->parts_amount, 2) }}</div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-semibold mb-2">{{ number_format((float) $event->total_amount, 2) }} {{ $event->currency }}</div>
                                        <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.accounting-events.post', ['accountingEvent' => $event->id] + $workspaceQuery) }}">
                                            @csrf
                                            <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                                            @if(($moduleData['posting_groups'] ?? collect())->isNotEmpty())
                                                <select name="posting_group_id" class="form-select form-select-sm mb-2">
                                                    @foreach(($moduleData['posting_groups'] ?? collect()) as $group)
                                                        <option value="{{ $group->id }}" @selected($group->is_default)>{{ $group->name }}</option>
                                                    @endforeach
                                                </select>
                                            @endif
                                            <button type="submit" class="btn btn-sm btn-primary">Post To Journal</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">No accounting events are waiting for journal posting.</p>
                        @endforelse
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Inventory Valuation Review</h5></div>
                    <div class="card-body">
                        @forelse(($moduleData['reviewable_inventory_movements'] ?? collect()) as $movement)
                            @php($movementValue = round((float) $movement->quantity * (float) ($movement->product?->cost_price ?? 0), 2))
                            <div class="border-bottom pb-3 mb-3">
                                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                    <div>
                                        <h6 class="mb-1">{{ $movement->product?->name ?: 'Stock Item' }}</h6>
                                        <div class="text-muted small">{{ strtoupper($movement->type) }} · {{ $movement->branch?->name ?: 'Branch' }}</div>
                                        <div class="text-muted small">Qty {{ rtrim(rtrim((string) $movement->quantity, '0'), '.') }} × Cost {{ number_format((float) ($movement->product?->cost_price ?? 0), 2) }}</div>
                                        <div class="text-muted small">{{ $movement->notes ?: 'Inventory movement' }}</div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-semibold mb-2">{{ number_format($movementValue, 2) }} USD</div>
                                        <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.inventory-movements.post', ['stockMovement' => $movement->id] + $workspaceQuery) }}">
                                            @csrf
                                            <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                                            <button type="submit" class="btn btn-sm btn-primary">Post Inventory Valuation</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">No valued inventory movements are waiting for accounting posting.</p>
                        @endforelse
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Create Manual Journal Entry</h5></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.manual-journal-entries.store', $workspaceQuery) }}">
                            @csrf
                            <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                            <div class="row">
                                <div class="col-md-3 mb-3"><label class="form-label">Entry Date</label><input type="date" name="entry_date" class="form-control" value="{{ old('entry_date', now()->toDateString()) }}"></div>
                                <div class="col-md-2 mb-3"><label class="form-label">Currency</label><input type="text" name="currency" maxlength="3" class="form-control" value="{{ old('currency', 'USD') }}"></div>
                                <div class="col-md-7 mb-3"><label class="form-label">Memo</label><input type="text" name="memo" class="form-control" value="{{ old('memo') }}" placeholder="Adjustment, accrual, correction"></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead><tr><th>Account Code</th><th>Account Name</th><th>Line Memo</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead>
                                    <tbody>
                                    @for($lineIndex = 0; $lineIndex < 4; $lineIndex++)
                                        <tr>
                                            <td><input type="text" name="lines[{{ $lineIndex }}][account_code]" class="form-control form-control-sm" value="{{ old("lines.$lineIndex.account_code") }}"></td>
                                            <td><input type="text" name="lines[{{ $lineIndex }}][account_name]" class="form-control form-control-sm" value="{{ old("lines.$lineIndex.account_name") }}"></td>
                                            <td><input type="text" name="lines[{{ $lineIndex }}][memo]" class="form-control form-control-sm" value="{{ old("lines.$lineIndex.memo") }}"></td>
                                            <td><input type="number" step="0.01" min="0" name="lines[{{ $lineIndex }}][debit]" class="form-control form-control-sm text-end" value="{{ old("lines.$lineIndex.debit") }}"></td>
                                            <td><input type="number" step="0.01" min="0" name="lines[{{ $lineIndex }}][credit]" class="form-control form-control-sm text-end" value="{{ old("lines.$lineIndex.credit") }}"></td>
                                        </tr>
                                    @endfor
                                    </tbody>
                                </table>
                            </div>
                            <button type="submit" class="btn btn-primary">Post Manual Journal</button>
                        </form>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-7 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header"><h5 class="card-title mb-0">Trial Balance</h5></div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead><tr><th>Account</th><th>Name</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr></thead>
                                        <tbody>
                                        @forelse(($moduleData['trial_balance'] ?? collect()) as $row)
                                            <tr>
                                                <td>{{ $row->account_code }}</td>
                                                <td>{{ $row->account_name ?: '-' }}</td>
                                                <td class="text-end">{{ number_format((float) $row->debit_total, 2) }}</td>
                                                <td class="text-end">{{ number_format((float) $row->credit_total, 2) }}</td>
                                                <td class="text-end">{{ number_format((float) $row->balance, 2) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5" class="text-muted">No posted journal lines are available yet.</td></tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-5 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header"><h5 class="card-title mb-0">Revenue Summary</h5></div>
                            <div class="card-body">
                                @forelse(($moduleData['revenue_summary'] ?? collect()) as $row)
                                    <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                                        <div><div class="fw-semibold">{{ $row->account_code }}</div><div class="text-muted small">{{ $row->account_name ?: 'Revenue account' }}</div></div>
                                        <div class="fw-semibold">{{ number_format((float) $row->revenue_total, 2) }}</div>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">No revenue lines have been posted yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Recent Journal Entries</h5></div>
                    <div class="card-body">
                        @forelse(($moduleData['recent_journal_entries'] ?? collect()) as $entry)
                            <div class="border-bottom pb-3 mb-3">
                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <div>
                                        <h6 class="mb-1">{{ $entry->journal_number }}</h6>
                                        <div class="text-muted small">{{ $entry->memo ?: 'Journal entry' }}</div>
                                        <div class="text-muted small">{{ optional($entry->entry_date)->format('Y-m-d') }} · {{ $entry->postingGroup?->name ?: 'Default posting' }}</div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-success">{{ strtoupper($entry->status) }}</span>
                                        <div class="fw-semibold mt-1">{{ number_format((float) $entry->debit_total, 2) }} {{ $entry->currency }}</div>
                                        <div class="mt-2">
                                            <a href="{{ route('automotive.admin.modules.general-ledger.journal-entries.show', ['journalEntry' => $entry->id] + $workspaceQuery) }}" class="btn btn-sm btn-outline-light">Open Detail</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="table-responsive mt-2">
                                    <table class="table table-sm mb-0">
                                        <thead><tr><th>Account</th><th>Memo</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead>
                                        <tbody>
                                        @foreach($entry->lines as $line)
                                            <tr>
                                                <td>{{ $line->account_code }}</td>
                                                <td>{{ $line->memo ?: '-' }}</td>
                                                <td class="text-end">{{ number_format((float) $line->debit, 2) }}</td>
                                                <td class="text-end">{{ number_format((float) $line->credit, 2) }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">No journal entries have been posted yet.</p>
                        @endforelse
                    </div>
                </div>

                <div class="card"><div class="card-header"><h5 class="card-title mb-0">Accounting Events Ledger</h5></div><div class="card-body">@forelse(($moduleData['recent_accounting_events'] ?? collect()) as $event)<div class="border-bottom pb-2 mb-2"><div class="d-flex justify-content-between align-items-start"><div><h6 class="mb-1">{{ data_get($event->payload, 'work_order_number', 'Accounting Event') }}</h6><div class="text-muted small">{{ data_get($event->payload, 'title', $event->event_type) }}</div><div class="text-muted small">{{ data_get($event->payload, 'customer_name', 'No customer') }}{{ data_get($event->payload, 'vehicle') ? ' · '.data_get($event->payload, 'vehicle') : '' }}</div></div><div class="text-end"><div class="fw-semibold">{{ number_format((float) $event->total_amount, 2) }} {{ $event->currency }}</div><div class="text-muted small">Labor {{ number_format((float) $event->labor_amount, 2) }} · Parts {{ number_format((float) $event->parts_amount, 2) }}</div><span class="badge {{ $event->status === 'journal_posted' ? 'bg-success' : 'bg-info' }} mt-1">{{ strtoupper(str_replace('_', ' ', $event->status)) }}</span></div></div></div>@empty<p class="text-muted mb-0">No local accounting events have been posted yet.</p>@endforelse</div></div>
            @elseif(($page ?? '') === 'supplier-catalog')
                <div class="row">
                    <div class="col-xl-4 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body">
                                <div class="text-muted small mb-1">Suppliers</div>
                                <h4 class="mb-1">{{ ($moduleData['suppliers'] ?? collect())->count() }}</h4>
                                <p class="mb-0 text-muted">Recent vendor records visible in this spare-parts workspace.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body">
                                <div class="text-muted small mb-1">Active Suppliers</div>
                                <h4 class="mb-1">{{ $moduleData['active_suppliers_count'] ?? 0 }}</h4>
                                <p class="mb-0 text-muted">Suppliers currently enabled for purchasing and stock sourcing.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body">
                                <div class="text-muted small mb-1">Product Focus</div>
                                <h4 class="mb-1">{{ $focusedWorkspaceProduct['product_name'] ?? 'Spare Parts' }}</h4>
                                <p class="mb-0 text-muted">Supplier records now live under the Spare Parts runtime instead of automotive service.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-5 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header"><h5 class="card-title mb-0">Create Supplier</h5></div>
                            <div class="card-body">
                                <form method="POST" action="{{ route('automotive.admin.modules.supplier-catalog.store', $workspaceQuery) }}">
                                    @csrf
                                    <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'parts_inventory') }}">
                                    <div class="mb-3"><label class="form-label">Supplier Name</label><input type="text" name="name" class="form-control" value="{{ old('name') }}"></div>
                                    <div class="mb-3"><label class="form-label">Contact Name</label><input type="text" name="contact_name" class="form-control" value="{{ old('contact_name') }}"></div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="{{ old('phone') }}"></div>
                                        <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email') }}"></div>
                                    </div>
                                    <div class="mb-3"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2">{{ old('address') }}</textarea></div>
                                    <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea></div>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" role="switch" id="supplier_is_active" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="supplier_is_active">Active supplier</label>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Create Supplier</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-7 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header"><h5 class="card-title mb-0">Supplier Table</h5></div>
                            <div class="card-body">
                                @forelse(($moduleData['suppliers'] ?? collect()) as $supplier)
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">{{ $supplier->name }}</h6>
                                                <div class="text-muted small">{{ $supplier->contact_name ?: 'No contact name' }}</div>
                                                <div class="text-muted small">{{ $supplier->phone ?: 'No phone' }}{{ $supplier->email ? ' · '.$supplier->email : '' }}</div>
                                                @if(!empty($supplier->address))
                                                    <div class="text-muted small">{{ $supplier->address }}</div>
                                                @endif
                                            </div>
                                            <span class="badge {{ $supplier->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $supplier->is_active ? 'ACTIVE' : 'INACTIVE' }}</span>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">No suppliers have been created yet.</p>
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
