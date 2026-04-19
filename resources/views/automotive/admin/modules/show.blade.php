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
                <div class="card"><div class="card-header"><h5 class="card-title mb-0">Accounting Events Ledger</h5></div><div class="card-body">@forelse(($moduleData['recent_accounting_events'] ?? collect()) as $event)<div class="border-bottom pb-2 mb-2"><div class="d-flex justify-content-between align-items-start"><div><h6 class="mb-1">{{ data_get($event->payload, 'work_order_number', 'Accounting Event') }}</h6><div class="text-muted small">{{ data_get($event->payload, 'title', $event->event_type) }}</div><div class="text-muted small">{{ data_get($event->payload, 'customer_name', 'No customer') }}{{ data_get($event->payload, 'vehicle') ? ' · '.data_get($event->payload, 'vehicle') : '' }}</div></div><div class="text-end"><div class="fw-semibold">{{ number_format((float) $event->total_amount, 2) }} {{ $event->currency }}</div><div class="text-muted small">Labor {{ number_format((float) $event->labor_amount, 2) }} · Parts {{ number_format((float) $event->parts_amount, 2) }}</div><span class="badge bg-info mt-1">{{ strtoupper($event->status) }}</span></div></div></div>@empty<p class="text-muted mb-0">No local accounting events have been posted yet.</p>@endforelse</div></div>
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
