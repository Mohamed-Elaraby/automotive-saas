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
                                <div class="col-md-2 mb-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">Any</option><option value="posted" @selected(($journalFilters['status'] ?? '') === 'posted')>Posted</option><option value="reversed" @selected(($journalFilters['status'] ?? '') === 'reversed')>Reversed</option><option value="void" @selected(($journalFilters['status'] ?? '') === 'void')>Void</option></select></div>
                                <div class="col-md-2 mb-3"><label class="form-label">Reconciliation</label><select name="reconciliation_status" class="form-select"><option value="">Any</option><option value="pending" @selected(($journalFilters['reconciliation_status'] ?? '') === 'pending')>Pending</option><option value="deposited" @selected(($journalFilters['reconciliation_status'] ?? '') === 'deposited')>Deposited</option><option value="reconciled" @selected(($journalFilters['reconciliation_status'] ?? '') === 'reconciled')>Reconciled</option></select></div>
                                <div class="col-md-2 mb-3"><label class="form-label">Vendor Bills</label><select name="vendor_bill_status" class="form-select"><option value="">Any</option><option value="draft" @selected(($journalFilters['vendor_bill_status'] ?? '') === 'draft')>Draft</option><option value="posted" @selected(($journalFilters['vendor_bill_status'] ?? '') === 'posted')>Posted</option><option value="partial" @selected(($journalFilters['vendor_bill_status'] ?? '') === 'partial')>Partial</option><option value="paid" @selected(($journalFilters['vendor_bill_status'] ?? '') === 'paid')>Paid</option></select></div>
                                <div class="col-md-2 mb-3"><label class="form-label">From</label><input type="date" name="date_from" class="form-control" value="{{ $journalFilters['date_from'] ?? '' }}"></div>
                                <div class="col-md-2 mb-3"><label class="form-label">To</label><input type="date" name="date_to" class="form-control" value="{{ $journalFilters['date_to'] ?? '' }}"></div>
                                <div class="col-md-2 mb-3 d-flex gap-2"><button type="submit" class="btn btn-primary">Apply</button><a href="{{ route('automotive.admin.modules.general-ledger', $workspaceQuery) }}" class="btn btn-outline-light">Reset</a></div>
                            </div>
                        </form>
                        <div class="d-flex gap-2 flex-wrap">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('automotive.admin.modules.general-ledger.exports', ['report' => 'journal-entries'] + $workspaceQuery + $journalFilters) }}">Export Journal CSV</a>
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('automotive.admin.modules.general-ledger.exports', ['report' => 'trial-balance'] + $workspaceQuery + $journalFilters) }}">Export Trial Balance CSV</a>
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('automotive.admin.modules.general-ledger.exports', ['report' => 'revenue-summary'] + $workspaceQuery + $journalFilters) }}">Export Revenue CSV</a>
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('automotive.admin.modules.general-ledger.exports', ['report' => 'profit-and-loss'] + $workspaceQuery + $journalFilters) }}">Export P&amp;L CSV</a>
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('automotive.admin.modules.general-ledger.exports', ['report' => 'balance-sheet'] + $workspaceQuery + $journalFilters) }}">Export Balance Sheet CSV</a>
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('automotive.admin.modules.general-ledger.exports', ['report' => 'tax-summary'] + $workspaceQuery + $journalFilters) }}">Export Tax Summary CSV</a>
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('automotive.admin.modules.general-ledger.exports', ['report' => 'payments'] + $workspaceQuery + $journalFilters) }}">Export Payments CSV</a>
                            <a class="btn btn-sm btn-outline-light" href="{{ route('automotive.admin.modules.general-ledger.exports', ['report' => 'journal-entries', 'format' => 'print'] + $workspaceQuery + $journalFilters) }}" target="_blank">Print Journal</a>
                            <a class="btn btn-sm btn-outline-light" href="{{ route('automotive.admin.modules.general-ledger.exports', ['report' => 'profit-and-loss', 'format' => 'print'] + $workspaceQuery + $journalFilters) }}" target="_blank">Print P&amp;L</a>
                            <a class="btn btn-sm btn-outline-light" href="{{ route('automotive.admin.modules.general-ledger.exports', ['report' => 'balance-sheet', 'format' => 'print'] + $workspaceQuery + $journalFilters) }}" target="_blank">Print Balance Sheet</a>
                            <a class="btn btn-sm btn-outline-light" href="{{ route('automotive.admin.modules.general-ledger.exports', ['report' => 'payments', 'format' => 'print'] + $workspaceQuery + $journalFilters) }}" target="_blank">Print Payments</a>
                            <a class="btn btn-sm btn-outline-light" href="{{ route('automotive.admin.modules.general-ledger.exports', ['report' => 'tax-summary', 'format' => 'print'] + $workspaceQuery + $journalFilters) }}" target="_blank">Print Tax Summary</a>
                            <a class="btn btn-sm btn-outline-light" href="{{ route('automotive.admin.modules.general-ledger.exports', ['report' => 'bank-reconciliation', 'format' => 'print'] + $workspaceQuery + $journalFilters) }}" target="_blank">Print Bank Reconciliation</a>
                        </div>
                    </div>
                </div>

                @php($aging = $moduleData['receivables_aging'] ?? ['buckets' => [], 'total_open' => 0, 'overdue_total' => 0])
                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Receivables Aging</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2 mb-3"><div class="text-muted small">Total Open</div><h5 class="mb-0">{{ number_format((float) ($aging['total_open'] ?? 0), 2) }}</h5></div>
                            <div class="col-md-2 mb-3"><div class="text-muted small">Overdue</div><h5 class="mb-0">{{ number_format((float) ($aging['overdue_total'] ?? 0), 2) }}</h5></div>
                            @foreach(($aging['buckets'] ?? []) as $bucket)
                                <div class="col-md-2 mb-3">
                                    <div class="text-muted small">{{ $bucket['label'] }}</div>
                                    <h5 class="mb-0">{{ number_format((float) $bucket['amount'], 2) }}</h5>
                                    <div class="text-muted small">{{ $bucket['count'] }} open</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Customer Statements</h5></div>
                    <div class="card-body">
                        @if(($moduleData['statement_customers'] ?? collect())->isEmpty())
                            <p class="text-muted mb-0">No customers are ready for statement printing yet.</p>
                        @else
                            <div class="row">
                                @foreach(($moduleData['statement_customers'] ?? collect())->take(6) as $statementCustomer)
                                    <div class="col-md-4 mb-2">
                                        <a class="btn btn-outline-light w-100 text-start" target="_blank" href="{{ route('automotive.admin.modules.general-ledger.customer-statement', ['customer' => $statementCustomer] + $workspaceQuery) }}">
                                            {{ $statementCustomer }}
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                @php($accountingPermissions = $moduleData['accounting_permissions'] ?? [])
                <div class="row">
                    <div class="col-xl-5 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header"><h5 class="card-title mb-0">Create Customer Invoice</h5></div>
                            <div class="card-body">
                                @if($accountingPermissions['ar_invoices_manage'] ?? true)
                                    <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.invoices.store', $workspaceQuery) }}">
                                        @csrf
                                        <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                                        <div class="row">
                                            <div class="col-md-6 mb-3"><label class="form-label">Customer</label><input type="text" name="customer_name" class="form-control" value="{{ old('customer_name') }}"></div>
                                            <div class="col-md-3 mb-3"><label class="form-label">Issue Date</label><input type="date" name="issue_date" class="form-control" value="{{ old('issue_date', now()->toDateString()) }}"></div>
                                            <div class="col-md-3 mb-3"><label class="form-label">Due Date</label><input type="date" name="due_date" class="form-control" value="{{ old('due_date') }}"></div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-7 mb-3"><label class="form-label">Line Description</label><input type="text" name="lines[0][description]" class="form-control" value="{{ old('lines.0.description', 'Service invoice') }}"></div>
                                            <div class="col-md-5 mb-3"><label class="form-label">Revenue Account</label><input type="text" name="lines[0][account_code]" list="account-catalog-options" class="form-control" value="{{ old('lines.0.account_code', '4100 Service Labor Revenue') }}"></div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-3 mb-3"><label class="form-label">Qty</label><input type="number" step="0.001" min="0.001" name="lines[0][quantity]" class="form-control" value="{{ old('lines.0.quantity', 1) }}"></div>
                                            <div class="col-md-3 mb-3"><label class="form-label">Unit Price</label><input type="number" step="0.01" min="0" name="lines[0][unit_price]" class="form-control" value="{{ old('lines.0.unit_price') }}"></div>
                                            <div class="col-md-3 mb-3"><label class="form-label">Tax</label><input type="number" step="0.01" min="0" name="tax_amount" class="form-control" value="{{ old('tax_amount', 0) }}"></div>
                                            <div class="col-md-3 mb-3"><label class="form-label">Reference</label><input type="text" name="reference" class="form-control" value="{{ old('reference') }}"></div>
                                        </div>
                                        <input type="hidden" name="currency" value="USD">
                                        <input type="hidden" name="receivable_account" value="1100 Accounts Receivable">
                                        <input type="hidden" name="tax_account" value="2100 VAT Output Payable">
                                        <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea></div>
                                        <button type="submit" class="btn btn-primary">Create Invoice</button>
                                    </form>
                                @else
                                    <p class="text-muted mb-0">You do not have permission to create invoices.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-7 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header"><h5 class="card-title mb-0">Customer Invoices</h5></div>
                            <div class="card-body">
                                @forelse(($moduleData['accounting_invoices'] ?? collect()) as $invoice)
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="d-flex justify-content-between gap-3">
                                            <div>
                                                <h6 class="mb-1">{{ $invoice->invoice_number }}</h6>
                                                <div class="text-muted small">{{ $invoice->customer_name }} · {{ optional($invoice->issue_date)->format('Y-m-d') }}{{ $invoice->due_date ? ' · Due '.optional($invoice->due_date)->format('Y-m-d') : '' }}</div>
                                                <div class="text-muted small">{{ $invoice->reference ?: 'No reference' }} · Paid {{ number_format((float) $invoice->getAttribute('paid_amount'), 2) }} · Open {{ number_format((float) $invoice->getAttribute('open_amount'), 2) }}</div>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge {{ $invoice->status === 'draft' ? 'bg-warning text-dark' : ($invoice->status === 'paid' ? 'bg-primary' : 'bg-success') }}">{{ strtoupper($invoice->status) }}</span>
                                                <div class="fw-semibold">{{ number_format((float) $invoice->total_amount, 2) }} {{ $invoice->currency }}</div>
                                                @if($invoice->status === 'draft' && ($accountingPermissions['ar_invoices_post'] ?? true))
                                                    <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.invoices.post', ['invoice' => $invoice->id] + $workspaceQuery) }}" class="mt-2">
                                                        @csrf
                                                        <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                                                        <button type="submit" class="btn btn-sm btn-outline-primary">Post Invoice</button>
                                                    </form>
                                                @elseif($invoice->accounting_event_id)
                                                    <a class="btn btn-sm btn-outline-light mt-2" target="_blank" href="{{ route('automotive.admin.modules.general-ledger.accounting-events.invoice', ['accountingEvent' => $invoice->accounting_event_id] + $workspaceQuery) }}">Print Invoice</a>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">No customer invoices have been created yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                @php($reconciliationSummary = $moduleData['payment_reconciliation_summary'] ?? ['pending_count' => 0, 'pending_amount' => 0, 'deposited_count' => 0, 'deposited_amount' => 0, 'vendor_payment_count' => 0, 'vendor_payment_amount' => 0, 'reconciled_period_amount' => 0])
                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Payment Reconciliation</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2 mb-3"><div class="text-muted small">Unreconciled Receipts</div><h5 class="mb-0">{{ $reconciliationSummary['pending_count'] }}</h5><div class="text-muted small">{{ number_format((float) $reconciliationSummary['pending_amount'], 2) }}</div></div>
                            <div class="col-md-2 mb-3"><div class="text-muted small">Unreconciled Deposits</div><h5 class="mb-0">{{ $reconciliationSummary['deposited_count'] }}</h5><div class="text-muted small">{{ number_format((float) $reconciliationSummary['deposited_amount'], 2) }}</div></div>
                            <div class="col-md-2 mb-3"><div class="text-muted small">Unreconciled Vendor Payments</div><h5 class="mb-0">{{ $reconciliationSummary['vendor_payment_count'] }}</h5><div class="text-muted small">{{ number_format((float) $reconciliationSummary['vendor_payment_amount'], 2) }}</div></div>
                            <div class="col-md-2 mb-3"><div class="text-muted small">Reconciled This Period</div><h5 class="mb-0">{{ number_format((float) $reconciliationSummary['reconciled_period_amount'], 2) }}</h5><div class="text-muted small">{{ $reconciliationSummary['period_start'] ?? '' }} - {{ $reconciliationSummary['period_end'] ?? '' }}</div></div>
                            <div class="col-md-4 mb-3">
                                <div class="text-muted small">Recent Deposit Batches</div>
                                @forelse(($moduleData['recent_deposit_batches'] ?? collect())->take(3) as $batch)
                                    <div class="d-flex justify-content-between border-bottom pb-1 mb-1">
                                        <a href="{{ route('automotive.admin.modules.general-ledger.deposit-batches.show', ['depositBatch' => $batch->id] + $workspaceQuery) }}">{{ $batch->deposit_number }} · {{ optional($batch->deposit_date)->format('Y-m-d') }}{{ $batch->reference ? ' · '.$batch->reference : '' }} · {{ strtoupper($batch->reconciliation_status ?: 'pending') }}</a>
                                        <span>{{ number_format((float) $batch->total_amount, 2) }} {{ $batch->currency }}</span>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">No deposit batches have been posted yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                @php($payablesSummary = $moduleData['payables_summary'] ?? ['draft_count' => 0, 'draft_amount' => 0, 'open_count' => 0, 'open_amount' => 0, 'paid_count' => 0, 'paid_amount' => 0])
                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Payables Summary</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3"><div class="text-muted small">Draft Bills</div><h5 class="mb-0">{{ $payablesSummary['draft_count'] }}</h5><div class="text-muted small">{{ number_format((float) $payablesSummary['draft_amount'], 2) }}</div></div>
                            <div class="col-md-3 mb-3"><div class="text-muted small">Open Payables</div><h5 class="mb-0">{{ $payablesSummary['open_count'] }}</h5><div class="text-muted small">{{ number_format((float) $payablesSummary['open_amount'], 2) }}</div></div>
                            <div class="col-md-3 mb-3"><div class="text-muted small">Paid Bills</div><h5 class="mb-0">{{ $payablesSummary['paid_count'] }}</h5><div class="text-muted small">{{ number_format((float) $payablesSummary['paid_amount'], 2) }}</div></div>
                            <div class="col-md-3 mb-3"><div class="text-muted small">Posting Rule</div><p class="mb-0">Bills credit Accounts Payable; payments debit Accounts Payable.</p></div>
                        </div>
                    </div>
                </div>

                @php($payablesAging = $moduleData['payables_aging'] ?? ['buckets' => [], 'total_open' => 0, 'overdue_total' => 0])
                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Payables Aging</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2 mb-3"><div class="text-muted small">Total Open</div><h5 class="mb-0">{{ number_format((float) ($payablesAging['total_open'] ?? 0), 2) }}</h5></div>
                            <div class="col-md-2 mb-3"><div class="text-muted small">Overdue</div><h5 class="mb-0">{{ number_format((float) ($payablesAging['overdue_total'] ?? 0), 2) }}</h5></div>
                            @foreach(($payablesAging['buckets'] ?? []) as $bucket)
                                <div class="col-md-2 mb-3">
                                    <div class="text-muted small">{{ $bucket['label'] }}</div>
                                    <h5 class="mb-0">{{ number_format((float) $bucket['amount'], 2) }}</h5>
                                    <div class="text-muted small">{{ $bucket['count'] }} open</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Tax And VAT Settings</h5></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.tax-rates.store', $workspaceQuery) }}">
                            @csrf
                            <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                            <div class="row">
                                <div class="col-md-2 mb-3"><label class="form-label">Code</label><input type="text" name="code" class="form-control" value="{{ old('code', 'vat_5') }}"></div>
                                <div class="col-md-3 mb-3"><label class="form-label">Name</label><input type="text" name="name" class="form-control" value="{{ old('name', 'VAT 5%') }}"></div>
                                <div class="col-md-2 mb-3"><label class="form-label">Rate %</label><input type="number" step="0.0001" min="0" max="100" name="rate" class="form-control" value="{{ old('rate', '5') }}"></div>
                                <div class="col-md-2 mb-3"><label class="form-label">Input Account</label><input type="text" name="input_tax_account" list="account-catalog-options" class="form-control" value="{{ old('input_tax_account', '1410 VAT Input Receivable') }}"></div>
                                <div class="col-md-3 mb-3"><label class="form-label">Output Account</label><input type="text" name="output_tax_account" list="account-catalog-options" class="form-control" value="{{ old('output_tax_account', '2100 VAT Output Payable') }}"></div>
                            </div>
                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="tax_rate_default" name="is_default" value="1" checked><label class="form-check-label" for="tax_rate_default">Default tax rate</label></div>
                                <button type="submit" class="btn btn-primary">Save Tax Rate</button>
                            </div>
                        </form>
                        <hr>
                        <div class="row">
                            @forelse(($moduleData['accounting_tax_rates'] ?? collect()) as $taxRate)
                                <div class="col-md-4 mb-2">
                                    <div class="border rounded p-2 h-100">
                                        <div class="fw-semibold">{{ $taxRate->name }} · {{ rtrim(rtrim(number_format((float) $taxRate->rate, 4), '0'), '.') }}%</div>
                                        <div class="text-muted small">{{ $taxRate->input_tax_account }}</div>
                                        <div class="text-muted small">{{ $taxRate->output_tax_account }}</div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted mb-0">No tax rates are configured yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                @php($periodLockSummary = $moduleData['accounting_period_lock_summary'] ?? [])
                @php($currentPeriodLock = $periodLockSummary['current_lock'] ?? null)
                @php($activeClosePeriod = $periodLockSummary['active_close'] ?? null)
                @php($latestPeriodLock = $periodLockSummary['latest_lock'] ?? null)
                @php($closeChecklist = $moduleData['accounting_close_checklist'] ?? [])
                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Posting Controls</h5></div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-3 mb-3">
                                <div class="text-muted small">Current Period</div>
                                <h5 class="mb-0">
                                    <span class="badge {{ ($periodLockSummary['current_status'] ?? 'open') === 'locked' ? 'bg-danger' : 'bg-success' }}">{{ strtoupper($periodLockSummary['current_status'] ?? 'open') }}</span>
                                </h5>
                                <div class="text-muted small">As of {{ $periodLockSummary['as_of_date'] ?? now()->toDateString() }}</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="text-muted small">Current Lock</div>
                                @if($currentPeriodLock)
                                    <h6 class="mb-0">{{ optional($currentPeriodLock->period_start)->format('Y-m-d') }} - {{ optional($currentPeriodLock->period_end)->format('Y-m-d') }}</h6>
                                @elseif($activeClosePeriod)
                                    <h6 class="mb-0">{{ optional($activeClosePeriod->period_start)->format('Y-m-d') }} - {{ optional($activeClosePeriod->period_end)->format('Y-m-d') }}</h6>
                                @else
                                    <h6 class="mb-0">No active lock today</h6>
                                @endif
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="text-muted small">Latest Lock</div>
                                @if($latestPeriodLock)
                                    <h6 class="mb-0">{{ optional($latestPeriodLock->period_start)->format('Y-m-d') }} - {{ optional($latestPeriodLock->period_end)->format('Y-m-d') }}</h6>
                                @else
                                    <h6 class="mb-0">No locked periods</h6>
                                @endif
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="text-muted small">Locked Periods</div>
                                <h5 class="mb-0">{{ (int) ($periodLockSummary['locked_periods_count'] ?? 0) }}</h5>
                            </div>
                        </div>
                        <div class="text-muted small">{{ $periodLockSummary['posting_policy'] ?? 'Journals are the accounting source of truth.' }}</div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                            <div>
                                <h6 class="mb-0">Close Readiness</h6>
                                <div class="text-muted small">{{ $closeChecklist['period_start'] ?? now()->startOfMonth()->toDateString() }} - {{ $closeChecklist['period_end'] ?? now()->endOfMonth()->toDateString() }}</div>
                            </div>
                            <span class="badge {{ ($closeChecklist['ready'] ?? false) ? 'bg-success' : 'bg-warning text-dark' }}">{{ ($closeChecklist['ready'] ?? false) ? 'READY' : 'BLOCKED' }}</span>
                        </div>
                        <div class="row">
                            @foreach(($closeChecklist['items'] ?? []) as $item)
                                <div class="col-md-4 mb-2">
                                    <div class="d-flex justify-content-between border rounded p-2">
                                        <span class="small">{{ $item['label'] ?? 'Checklist item' }}</span>
                                        <span class="badge {{ ($item['blocking'] ?? false) ? 'bg-warning text-dark' : 'bg-success' }}">{{ (int) ($item['count'] ?? 0) }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-5 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header"><h5 class="card-title mb-0">Bank & Cash Accounts</h5></div>
                            <div class="card-body">
                                <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.bank-accounts.store', $workspaceQuery) }}">
                                    @csrf
                                    <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                                    <div class="row">
                                        <div class="col-md-7 mb-2"><label class="form-label">Name</label><input type="text" name="name" class="form-control" value="{{ old('name', 'Operating Bank Account') }}"></div>
                                        <div class="col-md-5 mb-2"><label class="form-label">Type</label><select name="type" class="form-select"><option value="bank">Bank</option><option value="cash">Cash</option><option value="wallet">Wallet</option><option value="card_processor">Card Processor</option></select></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-7 mb-2"><label class="form-label">Ledger Account</label><input type="text" name="account_code" list="account-catalog-options" class="form-control" value="{{ old('account_code', '1010 Bank Account') }}"></div>
                                        <div class="col-md-5 mb-2"><label class="form-label">Reference</label><input type="text" name="reference" class="form-control" value="{{ old('reference') }}"></div>
                                    </div>
                                    <input type="hidden" name="currency" value="USD">
                                    <div class="d-flex flex-wrap gap-3 mb-2">
                                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="bank_default_receipt" name="is_default_receipt" value="1" {{ old('is_default_receipt') ? 'checked' : '' }}><label class="form-check-label" for="bank_default_receipt">Default receipt</label></div>
                                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="bank_default_payment" name="is_default_payment" value="1" {{ old('is_default_payment', '1') ? 'checked' : '' }}><label class="form-check-label" for="bank_default_payment">Default payment</label></div>
                                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="bank_is_active" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}><label class="form-check-label" for="bank_is_active">Active</label></div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Save Bank Account</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-7 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header"><h5 class="card-title mb-0">Cash Balances</h5></div>
                            <div class="card-body">
                                @forelse(($moduleData['accounting_bank_accounts'] ?? collect()) as $bankAccount)
                                    <div class="d-flex justify-content-between border-bottom pb-2 mb-2 gap-3">
                                        <div>
                                            <div class="fw-semibold">{{ $bankAccount->name }}</div>
                                            <div class="text-muted small">{{ $bankAccount->account_code }} · {{ strtoupper(str_replace('_', ' ', $bankAccount->type)) }}</div>
                                            <div class="text-muted small">{{ $bankAccount->reference ?: 'No reference' }}</div>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge {{ $bankAccount->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $bankAccount->is_active ? 'ACTIVE' : 'INACTIVE' }}</span>
                                            @if($bankAccount->is_default_receipt)<span class="badge bg-primary">RECEIPTS</span>@endif
                                            @if($bankAccount->is_default_payment)<span class="badge bg-info">PAYMENTS</span>@endif
                                            <div class="fw-semibold mt-1">{{ number_format((float) $bankAccount->getAttribute('journal_balance'), 2) }} {{ $bankAccount->currency }}</div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">No bank or cash accounts have been configured yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-4 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header"><h5 class="card-title mb-0">Account Catalog</h5></div>
                            <div class="card-body">
                                <form method="GET" action="{{ route('automotive.admin.modules.general-ledger') }}" class="mb-3">
                                    <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                                    <div class="row g-2">
                                        <div class="col-12"><input type="text" name="account_search" class="form-control form-control-sm" value="{{ request('account_search') }}" placeholder="Search accounts"></div>
                                        <div class="col-6"><select name="account_type" class="form-select form-select-sm"><option value="">All types</option><option value="asset" @selected(request('account_type') === 'asset')>Asset</option><option value="liability" @selected(request('account_type') === 'liability')>Liability</option><option value="equity" @selected(request('account_type') === 'equity')>Equity</option><option value="revenue" @selected(request('account_type') === 'revenue')>Revenue</option><option value="expense" @selected(request('account_type') === 'expense')>Expense</option></select></div>
                                        <div class="col-6"><select name="account_status" class="form-select form-select-sm"><option value="">All statuses</option><option value="active" @selected(request('account_status') === 'active')>Active</option><option value="inactive" @selected(request('account_status') === 'inactive')>Inactive</option></select></div>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-outline-light mt-2">Filter Accounts</button>
                                </form>
                                <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.accounts.store', $workspaceQuery) }}">
                                    @csrf
                                    <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                                    <div class="mb-2"><label class="form-label">Code</label><input type="text" name="code" class="form-control" value="{{ old('code', '1150 Clearing Account') }}"></div>
                                    <div class="mb-2"><label class="form-label">Name</label><input type="text" name="name" class="form-control" value="{{ old('name', 'Clearing Account') }}"></div>
                                    <div class="row">
                                        <div class="col-6 mb-2"><label class="form-label">Type</label><select name="type" class="form-select"><option value="asset" @selected(old('type') === 'asset')>Asset</option><option value="liability" @selected(old('type') === 'liability')>Liability</option><option value="equity" @selected(old('type') === 'equity')>Equity</option><option value="revenue" @selected(old('type') === 'revenue')>Revenue</option><option value="expense" @selected(old('type') === 'expense')>Expense</option></select></div>
                                        <div class="col-6 mb-2"><label class="form-label">Normal</label><select name="normal_balance" class="form-select"><option value="debit" @selected(old('normal_balance') === 'debit')>Debit</option><option value="credit" @selected(old('normal_balance') === 'credit')>Credit</option></select></div>
                                    </div>
                                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" id="account_is_active" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}><label class="form-check-label" for="account_is_active">Active</label></div>
                                    <button type="submit" class="btn btn-primary">Save Account</button>
                                </form>
                                <hr>
                                @forelse(($moduleData['accounting_accounts'] ?? collect())->take(12) as $account)
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="d-flex justify-content-between gap-2">
                                            <div><div class="fw-semibold">{{ $account->code }}</div><div class="text-muted small">{{ $account->name }}</div><div class="text-muted small">{{ ucfirst($account->normal_balance) }} normal balance</div></div>
                                            <div class="text-end">
                                                <span class="badge bg-light text-dark">{{ strtoupper($account->type) }}</span>
                                                <span class="badge {{ $account->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $account->is_active ? 'ACTIVE' : 'INACTIVE' }}</span>
                                            </div>
                                        </div>
                                        @if($account->is_active)
                                            <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.accounts.deactivate', ['account' => $account->id] + $workspaceQuery) }}" class="mt-2">
                                                @csrf
                                                <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                                                <button type="submit" class="btn btn-sm btn-outline-warning">Deactivate</button>
                                            </form>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">No accounting accounts are configured yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header"><h5 class="card-title mb-0">Period Locks</h5></div>
                            <div class="card-body">
                                <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.period-locks.closing', $workspaceQuery) }}" class="mb-3">
                                    @csrf
                                    <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                                    <div class="row">
                                        <div class="col-6 mb-2"><label class="form-label">Close Start</label><input type="date" name="period_start" class="form-control" value="{{ old('period_start', now()->startOfMonth()->toDateString()) }}"></div>
                                        <div class="col-6 mb-2"><label class="form-label">Close End</label><input type="date" name="period_end" class="form-control" value="{{ old('period_end', now()->endOfMonth()->toDateString()) }}"></div>
                                    </div>
                                    <div class="mb-2"><label class="form-label">Close Notes</label><input type="text" name="notes" class="form-control" value="{{ old('notes') }}"></div>
                                    <button type="submit" class="btn btn-outline-primary">Start Close Review</button>
                                </form>
                                <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.period-locks.store', $workspaceQuery) }}">
                                    @csrf
                                    <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                                    <div class="row">
                                        <div class="col-6 mb-2"><label class="form-label">Start</label><input type="date" name="period_start" class="form-control" value="{{ old('period_start', now()->startOfMonth()->toDateString()) }}"></div>
                                        <div class="col-6 mb-2"><label class="form-label">End</label><input type="date" name="period_end" class="form-control" value="{{ old('period_end', now()->endOfMonth()->toDateString()) }}"></div>
                                    </div>
                                    <div class="mb-2"><label class="form-label">Notes</label><input type="text" name="notes" class="form-control" value="{{ old('notes') }}"></div>
                                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" id="period_lock_override" name="allow_lock_override" value="1" {{ old('allow_lock_override') ? 'checked' : '' }}><label class="form-check-label" for="period_lock_override">Controlled override</label></div>
                                    <div class="mb-2"><label class="form-label">Override Reason</label><input type="text" name="lock_override_reason" class="form-control" value="{{ old('lock_override_reason') }}"></div>
                                    <button type="submit" class="btn btn-primary">Lock Period</button>
                                </form>
                                <hr>
                                @forelse(($moduleData['accounting_period_locks'] ?? collect()) as $lock)
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="fw-semibold">{{ optional($lock->period_start)->format('Y-m-d') }} → {{ optional($lock->period_end)->format('Y-m-d') }}</div>
                                        <div class="text-muted small">{{ strtoupper($lock->status) }} · {{ optional($lock->locked_at ?: $lock->closing_started_at ?: $lock->archived_at)->format('Y-m-d H:i') ?: '-' }}</div>
                                        @if($lock->lock_override)
                                            <div class="text-warning small">Override: {{ $lock->lock_override_reason ?: 'No reason recorded' }}</div>
                                        @endif
                                        @if($lock->status === 'locked')
                                            <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.period-locks.archive', ['period' => $lock->id] + $workspaceQuery) }}" class="mt-2">
                                                @csrf
                                                <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">Archive</button>
                                            </form>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">No locked accounting periods yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header"><h5 class="card-title mb-0">Inventory Accounting Policy</h5></div>
                            <div class="card-body">
                                <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.policies.store', $workspaceQuery) }}">
                                    @csrf
                                    <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                                    <div class="row">
                                        <div class="col-5 mb-2"><label class="form-label">Code</label><input type="text" name="code" class="form-control" value="{{ old('code', 'default_inventory_policy') }}"></div>
                                        <div class="col-7 mb-2"><label class="form-label">Name</label><input type="text" name="name" class="form-control" value="{{ old('name', 'Default Inventory Policy') }}"></div>
                                    </div>
                                    <div class="mb-2"><label class="form-label">Inventory Asset</label><input type="text" name="inventory_asset_account" class="form-control" value="{{ old('inventory_asset_account', '1300 Inventory Asset') }}"></div>
                                    <div class="mb-2"><label class="form-label">Adjustment Offset</label><input type="text" name="inventory_adjustment_offset_account" class="form-control" value="{{ old('inventory_adjustment_offset_account', '3900 Inventory Adjustment Offset') }}"></div>
                                    <div class="mb-2"><label class="form-label">Adjustment Expense</label><input type="text" name="inventory_adjustment_expense_account" class="form-control" value="{{ old('inventory_adjustment_expense_account', '5100 Inventory Adjustment Expense') }}"></div>
                                    <div class="mb-2"><label class="form-label">COGS</label><input type="text" name="cogs_account" class="form-control" value="{{ old('cogs_account', '5000 Cost Of Goods Sold') }}"></div>
                                    <input type="hidden" name="currency" value="USD">
                                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" id="policy_default" name="is_default" value="1" checked><label class="form-check-label" for="policy_default">Default policy</label></div>
                                    <button type="submit" class="btn btn-primary">Save Policy</button>
                                </form>
                                <hr>
                                @forelse(($moduleData['accounting_policies'] ?? collect()) as $policy)
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="fw-semibold">{{ $policy->name }}</div>
                                        <div class="text-muted small">{{ $policy->inventory_asset_account }} / {{ $policy->cogs_account }}</div>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">No accounting policies are configured yet.</p>
                                @endforelse
                            </div>
                        </div>
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
                                                @if(in_array($handoff->status, ['failed', 'skipped'], true))
                                                    <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.integration-handoffs.retry', ['handoff' => $handoff->id] + $workspaceQuery) }}" class="mt-2">
                                                        @csrf
                                                        <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                                                        <button type="submit" class="btn btn-sm btn-outline-primary">Retry</button>
                                                    </form>
                                                @endif
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

                @php($accountingPermissions = $moduleData['accounting_permissions'] ?? [])

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
                                        @if($accountingPermissions['source_events_post'] ?? true)
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
                                        @endif
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
                                        @if($accountingPermissions['inventory_movements_post'] ?? true)
                                            <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.inventory-movements.post', ['stockMovement' => $movement->id] + $workspaceQuery) }}">
                                                @csrf
                                                <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                                                <button type="submit" class="btn btn-sm btn-primary">Post Inventory Valuation</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">No valued inventory movements are waiting for accounting posting.</p>
                        @endforelse
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header"><h5 class="card-title mb-0">Create Vendor Bill</h5></div>
                            <div class="card-body">
                                <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.vendor-bills.store', $workspaceQuery) }}">
                                    @csrf
                                    <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                                    <div class="row">
                                        <div class="col-md-6 mb-3"><label class="form-label">Supplier Name</label><input type="text" name="supplier_name" class="form-control" value="{{ old('supplier_name') }}" placeholder="Vendor or supplier"></div>
                                        <div class="col-md-3 mb-3"><label class="form-label">Bill Date</label><input type="date" name="bill_date" class="form-control" value="{{ old('bill_date', now()->toDateString()) }}"></div>
                                        <div class="col-md-3 mb-3"><label class="form-label">Due Date</label><input type="date" name="due_date" class="form-control" value="{{ old('due_date') }}"></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4 mb-3"><label class="form-label">Amount</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="{{ old('amount') }}"></div>
                                        <div class="col-md-4 mb-3"><label class="form-label">Tax Rate</label><select name="accounting_tax_rate_id" class="form-select"><option value="">No tax</option>@foreach(($moduleData['accounting_tax_rates'] ?? collect()) as $taxRate)<option value="{{ $taxRate->id }}">{{ $taxRate->name }}</option>@endforeach</select></div>
                                        <div class="col-md-4 mb-3"><label class="form-label">Tax Amount</label><input type="number" step="0.01" min="0" name="tax_amount" class="form-control" value="{{ old('tax_amount', '0') }}"></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4 mb-3"><label class="form-label">Expense Account</label><input type="text" name="expense_account" list="account-catalog-options" class="form-control" value="{{ old('expense_account', '5200 Operating Expense') }}"></div>
                                        <div class="col-md-4 mb-3"><label class="form-label">Payable Account</label><input type="text" name="payable_account" list="account-catalog-options" class="form-control" value="{{ old('payable_account', '2000 Accounts Payable') }}"></div>
                                        <div class="col-md-4 mb-3"><label class="form-label">Tax Account</label><input type="text" name="tax_account" list="account-catalog-options" class="form-control" value="{{ old('tax_account', '1410 VAT Input Receivable') }}"></div>
                                    </div>
                                    <input type="hidden" name="currency" value="USD">
                                    <div class="mb-3"><label class="form-label">Reference</label><input type="text" name="reference" class="form-control" value="{{ old('reference') }}"></div>
                                    <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea></div>
                                    <button type="submit" class="btn btn-primary">Create Vendor Bill</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header"><h5 class="card-title mb-0">Payables Review</h5></div>
                            <div class="card-body">
                                @forelse(($moduleData['vendor_bills'] ?? collect()) as $bill)
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="d-flex justify-content-between gap-3">
                                            <div>
                                                <h6 class="mb-1">{{ $bill->bill_number }}</h6>
                                                <div class="text-muted small">{{ $bill->supplier_name ?: $bill->supplier?->name ?: 'Vendor bill' }} · {{ $bill->reference ?: 'No reference' }}</div>
                                                <div class="text-muted small">{{ optional($bill->bill_date)->format('Y-m-d') }}{{ $bill->due_date ? ' · Due '.optional($bill->due_date)->format('Y-m-d') : '' }}</div>
                                                <div class="text-muted small">{{ $bill->expense_account }} → {{ $bill->payable_account }}</div>
                                                <div class="text-muted small">Paid {{ number_format((float) $bill->getAttribute('paid_amount'), 2) }} · Open {{ number_format((float) $bill->getAttribute('open_amount'), 2) }}</div>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge {{ $bill->status === 'posted' ? 'bg-success' : 'bg-warning text-dark' }}">{{ strtoupper($bill->status) }}</span>
                                                <div class="fw-semibold">{{ number_format((float) $bill->amount, 2) }} {{ $bill->currency }}</div>
                                                @if($bill->journal_entry_id)
                                                    <a href="{{ route('automotive.admin.modules.general-ledger.journal-entries.show', ['journalEntry' => $bill->journal_entry_id] + $workspaceQuery) }}" class="btn btn-sm btn-outline-light mt-2">Open Journal</a>
                                                @elseif($bill->status === 'draft')
                                                    @if($accountingPermissions['vendor_bills_post'] ?? true)
                                                    <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.vendor-bills.post', ['vendorBill' => $bill->id] + $workspaceQuery) }}" class="mt-2">
                                                        @csrf
                                                        <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                                                        <button type="submit" class="btn btn-sm btn-primary">Post To Payables</button>
                                                    </form>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">No vendor bills have been created yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header"><h5 class="card-title mb-0">Pay Vendor Bill</h5></div>
                            <div class="card-body">
                                @if(($moduleData['open_vendor_bills'] ?? collect())->isEmpty())
                                    <p class="text-muted mb-0">No open vendor bills are ready for payment.</p>
                                @else
                                    <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.vendor-bill-payments.store', $workspaceQuery) }}">
                                        @csrf
                                        <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                                        <div class="mb-3">
                                            <label class="form-label">Vendor Bill</label>
                                            <select name="accounting_vendor_bill_id" class="form-select">
                                                @foreach(($moduleData['open_vendor_bills'] ?? collect()) as $bill)
                                                    <option value="{{ $bill->id }}">{{ $bill->bill_number }} · {{ $bill->supplier_name ?: 'Vendor' }} · Open {{ number_format((float) $bill->getAttribute('open_amount'), 2) }} {{ $bill->currency }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4 mb-3"><label class="form-label">Payment Date</label><input type="date" name="payment_date" class="form-control" value="{{ old('payment_date', now()->toDateString()) }}"></div>
                                            <div class="col-md-4 mb-3"><label class="form-label">Amount</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="{{ old('amount') }}"></div>
                                            <div class="col-md-4 mb-3"><label class="form-label">Method</label><select name="method" class="form-select"><option value="bank_transfer">Bank Transfer</option><option value="cash">Cash</option><option value="card">Card</option><option value="check">Check</option><option value="other">Other</option></select></div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3"><label class="form-label">Bank/Cash Account</label><select name="accounting_bank_account_id" class="form-select">@foreach(($moduleData['accounting_bank_accounts'] ?? collect())->where('is_active', true) as $bankAccount)<option value="{{ $bankAccount->id }}" @selected($bankAccount->is_default_payment)>{{ $bankAccount->name }} · {{ $bankAccount->account_code }}</option>@endforeach</select></div>
                                            <div class="col-md-6 mb-3"><label class="form-label">Reference</label><input type="text" name="reference" class="form-control" value="{{ old('reference') }}"></div>
                                        </div>
                                        <input type="hidden" name="currency" value="USD">
                                        <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea></div>
                                        <button type="submit" class="btn btn-primary">Record Vendor Payment</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header"><h5 class="card-title mb-0">Recent Vendor Payments</h5></div>
                            <div class="card-body">
                                @forelse(($moduleData['recent_vendor_bill_payments'] ?? collect()) as $payment)
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="d-flex justify-content-between gap-3">
                                            <div>
                                                <h6 class="mb-1">{{ $payment->payment_number }}</h6>
                                                <div class="text-muted small">{{ $payment->vendorBill?->supplier_name ?: 'Vendor payment' }} · {{ ucfirst(str_replace('_', ' ', $payment->method)) }}</div>
                                                <div class="text-muted small">{{ optional($payment->payment_date)->format('Y-m-d') }} · {{ $payment->cash_account }} → {{ $payment->payable_account }}</div>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge {{ $payment->status === 'posted' ? 'bg-success' : 'bg-secondary' }}">{{ strtoupper($payment->status) }}</span>
                                                <span class="badge {{ $payment->reconciliation_status === 'reconciled' ? 'bg-primary' : 'bg-warning text-dark' }}">{{ strtoupper($payment->reconciliation_status ?: 'pending') }}</span>
                                                <div class="fw-semibold">{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</div>
                                                @if($payment->journal_entry_id)
                                                    <a href="{{ route('automotive.admin.modules.general-ledger.journal-entries.show', ['journalEntry' => $payment->journal_entry_id] + $workspaceQuery) }}" class="btn btn-sm btn-outline-light mt-2">Open Journal</a>
                                                @endif
                                                @if(($accountingPermissions['reconciliation_manage'] ?? true) && $payment->status === 'posted' && $payment->reconciliation_status !== 'reconciled')
                                                    <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.vendor-bill-payments.reconcile', ['payment' => $payment->id] + $workspaceQuery) }}" class="mt-2">
                                                        @csrf
                                                        <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                                                        <input type="hidden" name="bank_reconciliation_date" value="{{ now()->toDateString() }}">
                                                        <input type="hidden" name="bank_reference" value="{{ $payment->reference }}">
                                                        <button type="submit" class="btn btn-sm btn-outline-primary">Mark Reconciled</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">No vendor payments have been recorded yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header"><h5 class="card-title mb-0">Record Customer Payment</h5></div>
                            <div class="card-body">
                                @if(($moduleData['receivable_events'] ?? collect())->isEmpty())
                                    <p class="text-muted mb-0">No open customer receivables are ready for payment collection.</p>
                                @else
                                    <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.payments.store', $workspaceQuery) }}">
                                        @csrf
                                        <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                                        <div class="mb-3">
                                            <label class="form-label">Receivable</label>
                                            <select name="accounting_event_id" class="form-select">
                                                @foreach(($moduleData['receivable_events'] ?? collect()) as $event)
                                                    <option value="{{ $event->id }}">{{ data_get($event->payload, 'work_order_number', 'Accounting Event') }} · {{ data_get($event->payload, 'customer_name', 'Customer') }} · Open {{ number_format((float) $event->getAttribute('open_amount'), 2) }} {{ $event->currency }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4 mb-3"><label class="form-label">Payment Date</label><input type="date" name="payment_date" class="form-control" value="{{ old('payment_date', now()->toDateString()) }}"></div>
                                            <div class="col-md-4 mb-3"><label class="form-label">Amount</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="{{ old('amount') }}"></div>
                                            <div class="col-md-4 mb-3"><label class="form-label">Method</label><select name="method" class="form-select"><option value="cash">Cash</option><option value="bank_transfer">Bank Transfer</option><option value="card">Card</option><option value="check">Check</option><option value="other">Other</option></select></div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-5 mb-3"><label class="form-label">Payer Name</label><input type="text" name="payer_name" class="form-control" value="{{ old('payer_name') }}"></div>
                                            <div class="col-md-4 mb-3"><label class="form-label">Bank/Cash Account</label><select name="accounting_bank_account_id" class="form-select">@foreach(($moduleData['accounting_bank_accounts'] ?? collect())->where('is_active', true) as $bankAccount)<option value="{{ $bankAccount->id }}" @selected($bankAccount->is_default_receipt)>{{ $bankAccount->name }} · {{ $bankAccount->account_code }}</option>@endforeach</select></div>
                                            <div class="col-md-3 mb-3"><label class="form-label">Reference</label><input type="text" name="reference" class="form-control" value="{{ old('reference') }}"></div>
                                        </div>
                                        <input type="hidden" name="currency" value="USD">
                                        <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea></div>
                                        <button type="submit" class="btn btn-primary">Record Payment</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header"><h5 class="card-title mb-0">Create Deposit Batch</h5></div>
                            <div class="card-body">
                                @if(($moduleData['reconcilable_payments'] ?? collect())->isEmpty())
                                    <p class="text-muted mb-0">No posted payments are pending reconciliation.</p>
                                @else
                                    <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.deposit-batches.store', $workspaceQuery) }}">
                                        @csrf
                                        <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                                        <div class="row">
                                            <div class="col-md-4 mb-3"><label class="form-label">Deposit Date</label><input type="date" name="deposit_date" class="form-control" value="{{ old('deposit_date', now()->toDateString()) }}"></div>
                                            <div class="col-md-4 mb-3"><label class="form-label">Deposit Account</label><select name="accounting_bank_account_id" class="form-select">@foreach(($moduleData['accounting_bank_accounts'] ?? collect())->where('is_active', true) as $bankAccount)<option value="{{ $bankAccount->id }}" @selected($bankAccount->is_default_receipt)>{{ $bankAccount->name }} · {{ $bankAccount->account_code }}</option>@endforeach</select></div>
                                            <div class="col-md-4 mb-3"><label class="form-label">Reference</label><input type="text" name="reference" class="form-control" value="{{ old('reference') }}"></div>
                                        </div>
                                        <input type="hidden" name="currency" value="USD">
                                        <div class="mb-3">
                                            @foreach(($moduleData['reconcilable_payments'] ?? collect()) as $payment)
                                                <label class="d-flex justify-content-between align-items-start border rounded p-2 mb-2">
                                                    <span>
                                                        <input type="checkbox" name="payment_ids[]" value="{{ $payment->id }}" class="form-check-input me-2">
                                                        <span class="fw-semibold">{{ $payment->payment_number }}</span>
                                                        <span class="text-muted small d-block">{{ $payment->payer_name ?: 'Customer payment' }} · {{ optional($payment->payment_date)->format('Y-m-d') }} · {{ $payment->reference ?: 'No reference' }}</span>
                                                    </span>
                                                    <span class="fw-semibold">{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea></div>
                                        <button type="submit" class="btn btn-primary">Post Deposit Batch</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header"><h5 class="card-title mb-0">Recent Customer Payments</h5></div>
                            <div class="card-body">
                                @forelse(($moduleData['recent_accounting_payments'] ?? collect()) as $payment)
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="d-flex justify-content-between gap-3">
                                            <div>
                                                <h6 class="mb-1">{{ $payment->payment_number }}</h6>
                                                <div class="text-muted small">{{ $payment->payer_name ?: 'Customer payment' }} · {{ ucfirst(str_replace('_', ' ', $payment->method)) }}</div>
                                                <div class="text-muted small">{{ optional($payment->payment_date)->format('Y-m-d') }} · {{ $payment->cash_account }} → {{ $payment->receivable_account }}</div>
                                                @if($payment->depositBatch)
                                                    <div class="text-muted small">Deposit {{ $payment->depositBatch->deposit_number }} · {{ optional($payment->reconciled_at)->format('Y-m-d') }}</div>
                                                @endif
                                            </div>
                                            <div class="text-end">
                                                <span class="badge {{ $payment->status === 'posted' ? 'bg-success' : 'bg-secondary' }}">{{ strtoupper($payment->status) }}</span>
                                                <span class="badge {{ $payment->reconciliation_status === 'deposited' ? 'bg-primary' : 'bg-warning text-dark' }}">{{ strtoupper($payment->reconciliation_status ?: 'pending') }}</span>
                                                <div class="fw-semibold">{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</div>
                                                @if($payment->journal_entry_id)
                                                    <a href="{{ route('automotive.admin.modules.general-ledger.journal-entries.show', ['journalEntry' => $payment->journal_entry_id] + $workspaceQuery) }}" class="btn btn-sm btn-outline-light mt-2">Open Journal</a>
                                                @endif
                                                @if(($accountingPermissions['reconciliation_manage'] ?? true) && $payment->status === 'posted' && $payment->deposit_batch_id === null && $payment->reconciliation_status !== 'reconciled')
                                                    <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.payments.reconcile', ['payment' => $payment->id] + $workspaceQuery) }}" class="mt-2">
                                                        @csrf
                                                        <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                                                        <input type="hidden" name="bank_reconciliation_date" value="{{ now()->toDateString() }}">
                                                        <input type="hidden" name="bank_reference" value="{{ $payment->reference }}">
                                                        <button type="submit" class="btn btn-sm btn-outline-primary">Mark Reconciled</button>
                                                    </form>
                                                @endif
                                                @if($payment->status === 'posted' && ! in_array($payment->reconciliation_status, ['deposited', 'reconciled'], true))
                                                    <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.payments.void', ['payment' => $payment->id] + $workspaceQuery) }}" class="mt-2">
                                                        @csrf
                                                        <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Void Payment</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">No customer payments have been recorded yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Manual Journal Approvals</h5></div>
                    <div class="card-body">
                        @forelse(($moduleData['pending_manual_journal_approvals'] ?? collect()) as $entry)
                            <div class="border-bottom pb-3 mb-3">
                                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                    <div>
                                        <h6 class="mb-1">{{ $entry->journal_number }}</h6>
                                        <div class="text-muted small">{{ $entry->memo ?: 'Manual journal pending approval' }}</div>
                                        <div class="text-muted small">{{ optional($entry->entry_date)->format('Y-m-d') }} · Submitted {{ optional($entry->approval_submitted_at)->format('Y-m-d H:i') ?: '-' }}</div>
                                        <div class="text-muted small">Created by {{ $entry->creator?->name ?: 'System user' }}</div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-warning text-dark">PENDING APPROVAL</span>
                                        <div class="fw-semibold mt-1">{{ number_format((float) $entry->debit_total, 2) }} {{ $entry->currency }}</div>
                                        <div class="d-flex gap-2 mt-2 justify-content-end flex-wrap">
                                            <a href="{{ route('automotive.admin.modules.general-ledger.journal-entries.show', ['journalEntry' => $entry->id] + $workspaceQuery) }}" class="btn btn-sm btn-outline-light">Open Detail</a>
                                            @if($accountingPermissions['manual_journals_approve'] ?? true)
                                                <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.journal-entries.approve', ['journalEntry' => $entry->id] + $workspaceQuery) }}">
                                                    @csrf
                                                    <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                                                    <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                                </form>
                                                <form method="POST" action="{{ route('automotive.admin.modules.general-ledger.journal-entries.reject', ['journalEntry' => $entry->id] + $workspaceQuery) }}">
                                                    @csrf
                                                    <input type="hidden" name="workspace_product" value="{{ $workspaceQuery['workspace_product'] ?? data_get($focusedWorkspaceProduct, 'product_code', 'accounting') }}">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">No high-risk manual journals are pending approval.</p>
                        @endforelse
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Create Manual Journal Entry</h5></div>
                    <div class="card-body">
                        <datalist id="account-catalog-options">
                            @foreach(($moduleData['active_accounting_accounts'] ?? $moduleData['accounting_accounts'] ?? collect()) as $account)
                                <option value="{{ $account->code }}">{{ $account->name }}</option>
                            @endforeach
                        </datalist>
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
                                            <td><input type="text" name="lines[{{ $lineIndex }}][account_code]" list="account-catalog-options" class="form-control form-control-sm" value="{{ old("lines.$lineIndex.account_code") }}"></td>
                                            <td><input type="text" name="lines[{{ $lineIndex }}][account_name]" class="form-control form-control-sm" value="{{ old("lines.$lineIndex.account_name") }}"></td>
                                            <td><input type="text" name="lines[{{ $lineIndex }}][memo]" class="form-control form-control-sm" value="{{ old("lines.$lineIndex.memo") }}"></td>
                                            <td><input type="number" step="0.01" min="0" name="lines[{{ $lineIndex }}][debit]" class="form-control form-control-sm text-end" value="{{ old("lines.$lineIndex.debit") }}"></td>
                                            <td><input type="number" step="0.01" min="0" name="lines[{{ $lineIndex }}][credit]" class="form-control form-control-sm text-end" value="{{ old("lines.$lineIndex.credit") }}"></td>
                                        </tr>
                                    @endfor
                                    </tbody>
                                </table>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="manual_journal_requires_approval" name="requires_approval" value="1" {{ old('requires_approval') ? 'checked' : '' }}>
                                <label class="form-check-label" for="manual_journal_requires_approval">Submit for approval</label>
                            </div>
                            <button type="submit" class="btn btn-primary">Create Manual Journal</button>
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

                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Accounting Audit Timeline</h5></div>
                    <div class="card-body">
                        @forelse(($moduleData['accounting_audit_entries'] ?? collect()) as $audit)
                            <div class="border-bottom pb-2 mb-2">
                                <div class="d-flex justify-content-between gap-3">
                                    <div>
                                        <h6 class="mb-1">{{ str_replace('_', ' ', strtoupper($audit->event_type)) }}</h6>
                                        <div class="text-muted small">{{ $audit->description }}</div>
                                    </div>
                                    <div class="text-muted small text-end">{{ optional($audit->created_at)->format('Y-m-d H:i') }}</div>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">No accounting audit entries have been recorded yet.</p>
                        @endforelse
                    </div>
                </div>

                <div class="card"><div class="card-header"><h5 class="card-title mb-0">Accounting Events Ledger</h5></div><div class="card-body">@forelse(($moduleData['recent_accounting_events'] ?? collect()) as $event)<div class="border-bottom pb-2 mb-2"><div class="d-flex justify-content-between align-items-start"><div><h6 class="mb-1">{{ data_get($event->payload, 'work_order_number', 'Accounting Event') }}</h6><div class="text-muted small">{{ data_get($event->payload, 'title', $event->event_type) }}</div><div class="text-muted small">{{ data_get($event->payload, 'customer_name', 'No customer') }}{{ data_get($event->payload, 'vehicle') ? ' · '.data_get($event->payload, 'vehicle') : '' }}</div>@if(in_array($event->status, ['journal_posted', 'paid'], true))<div class="mt-2"><a class="btn btn-sm btn-outline-light" target="_blank" href="{{ route('automotive.admin.modules.general-ledger.accounting-events.invoice', ['accountingEvent' => $event->id] + $workspaceQuery) }}">Print Invoice</a></div>@endif</div><div class="text-end"><div class="fw-semibold">{{ number_format((float) $event->total_amount, 2) }} {{ $event->currency }}</div><div class="text-muted small">Labor {{ number_format((float) $event->labor_amount, 2) }} · Parts {{ number_format((float) $event->parts_amount, 2) }}</div><span class="badge {{ $event->status === 'journal_posted' ? 'bg-success' : 'bg-info' }} mt-1">{{ strtoupper(str_replace('_', ' ', $event->status)) }}</span></div></div></div>@empty<p class="text-muted mb-0">No local accounting events have been posted yet.</p>@endforelse</div></div>
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
