<?php $page = 'dashboard'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    @php
        $focusedProductCode = $focusedWorkspaceProductFamily ?? 'automotive_service';
        $workspaceProductsCount = ($workspaceProducts ?? collect())->count();
    @endphp

    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                <div>
                    <div class="text-muted small mb-1">{{ $focusedExperience['eyebrow'] ?? __('shared.workspace_focus') }}</div>
                    <h4 class="mb-1">{{ $focusedExperience['title'] ?? __('shared.dashboard') }}</h4>
                    <p class="mb-0 text-muted">{{ $focusedExperience['description'] ?? __('shared.shared_workspace_overview') }}</p>
                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap">
                    @foreach(($dashboardActions ?? []) as $action)
                        <a href="{{ route($action['route'], $action['params'] ?? []) }}" class="btn btn-{{ $action['variant'] ?? 'outline-white' }} d-inline-flex align-items-center">
                            <i class="isax {{ $action['icon'] }} me-1"></i>{{ $action['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            @if(!empty($focusedWorkspaceProduct))
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                            <div>
                                <div class="text-muted small mb-1">{{ __('shared.focused_workspace_product') }}</div>
                                <h5 class="mb-1">{{ $focusedWorkspaceProduct['product_name'] }}</h5>
                                <p class="text-muted mb-2">
                                    {{ $focusedWorkspaceProduct['plan_name'] ?: __('shared.no_plan_mapped_yet') }} ·
                                    {{ $focusedWorkspaceProduct['is_accessible'] ? __('shared.connected_to_workspace') : $focusedWorkspaceProduct['status_label'] }}
                                </p>
                                @if(!empty($focusedWorkspaceProduct['capabilities']))
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach($focusedWorkspaceProduct['capabilities'] as $capabilityName)
                                            <span class="badge bg-primary-subtle text-primary border">{{ $capabilityName }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            @if(($workspaceProducts ?? collect())->count() > 1)
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach($workspaceProducts as $workspaceProduct)
                                        <a
                                            href="{{ route('automotive.admin.dashboard', ['workspace_product' => $workspaceProduct['product_code']]) }}"
                                            class="btn {{ $focusedWorkspaceProduct['product_code'] === $workspaceProduct['product_code'] ? 'btn-primary' : 'btn-outline-white' }}"
                                        >
                                            {{ $workspaceProduct['product_name'] }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <div class="row">
                <div class="col-xxl-3 col-xl-4 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-gray-6">{{ __('shared.users') }}</span>
                                <span class="avatar avatar-sm bg-primary-transparent rounded-circle">
                                    <i class="isax isax-profile-2user text-primary"></i>
                                </span>
                            </div>
                            <h3 class="mb-1">{{ $usersCount }}</h3>
                            <p class="mb-0 text-muted">
                                @if (!is_null($userLimit['limit']))
                                    {{ __('shared.limit') }}: {{ $userLimit['limit'] }} | {{ __('shared.remaining') }}: {{ $userLimit['remaining'] }}
                                @else
                                    {{ __('shared.unlimited') }}
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-xxl-3 col-xl-4 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-gray-6">{{ __('shared.branches') }}</span>
                                <span class="avatar avatar-sm bg-secondary-transparent rounded-circle">
                                    <i class="isax isax-buildings text-secondary"></i>
                                </span>
                            </div>
                            <h3 class="mb-1">{{ $branchesCount }}</h3>
                            <p class="mb-0 text-muted">
                                @if (!is_null($branchLimit['limit']))
                                    {{ __('shared.limit') }}: {{ $branchLimit['limit'] }} | {{ __('shared.remaining') }}: {{ $branchLimit['remaining'] }}
                                @else
                                    {{ __('shared.unlimited') }}
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-xxl-3 col-xl-4 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-gray-6">{{ __('shared.current_plan') }}</span>
                                <span class="avatar avatar-sm bg-info-transparent rounded-circle">
                                    <i class="isax isax-crown text-info"></i>
                                </span>
                            </div>
                            <h3 class="mb-1">{{ $plan?->name ?? __('shared.no_plan') }}</h3>
                            <p class="mb-0 text-muted">{{ $subscription->status ?? 'unknown' }}</p>
                        </div>
                    </div>
                </div>

                <div class="col-xxl-3 col-xl-4 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-gray-6">{{ __('shared.workspace_products') }}</span>
                                <span class="avatar avatar-sm bg-success-transparent rounded-circle">
                                    <i class="isax isax-layer text-success"></i>
                                </span>
                            </div>
                            <h3 class="mb-1">{{ $workspaceProductsCount }}</h3>
                            <p class="mb-0 text-muted">{{ __('shared.products_attached_to_workspace') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xxl-4 col-xl-5 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <h5 class="card-title mb-0">{{ __('tenant.tenant_overview') }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between border-bottom pb-2 mb-3">
                                <span class="text-gray-6">{{ __('tenant.tenant_id') }}</span>
                                <strong>{{ $tenant->id }}</strong>
                            </div>
                            <div class="d-flex justify-content-between border-bottom pb-2 mb-3">
                                <span class="text-gray-6">{{ __('tenant.company') }}</span>
                                <strong>{{ data_get($tenant->data, 'company_name', '—') }}</strong>
                            </div>
                            <div class="d-flex justify-content-between border-bottom pb-2 mb-3">
                                <span class="text-gray-6">{{ __('tenant.database') }}</span>
                                <strong>{{ data_get($tenant->data, 'db_name', '—') }}</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-gray-6">{{ __('tenant.logged_user') }}</span>
                                <strong>{{ auth('automotive_admin')->user()?->name ?? '—' }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xxl-4 col-xl-7 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <h5 class="card-title mb-0">{{ __('tenant.workspace_products') }}</h5>
                        </div>
                        <div class="card-body">
                            @if(($workspaceProducts ?? collect())->isEmpty())
                                <p class="text-muted mb-0">{{ __('tenant.no_product_subscriptions') }}</p>
                            @else
                                <div class="d-flex flex-column gap-3">
                                    @foreach($workspaceProducts as $workspaceProduct)
                                        <div class="d-flex justify-content-between align-items-start border-bottom pb-2">
                                            <div>
                                                <div class="fw-semibold">{{ $workspaceProduct['product_name'] }}</div>
                                                <div class="text-muted small">{{ $workspaceProduct['plan_name'] ?: __('shared.no_plan_mapped_yet') }}</div>
                                            </div>
                                            <span class="badge {{ $workspaceProduct['is_accessible'] ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $workspaceProduct['is_accessible'] ? __('tenant.connected') : $workspaceProduct['status_label'] }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-xxl-4 col-xl-12 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <h5 class="card-title mb-0">{{ __('tenant.focused_module_entry') }}</h5>
                        </div>
                        <div class="card-body">
                            @if ($focusedProductCode === 'parts_inventory')
                                <p class="text-muted">{{ __('tenant.parts_focus_note') }}</p>
                                <div class="d-grid gap-2">
                                    <a href="{{ route('automotive.admin.modules.supplier-catalog', $workspaceQuery) }}" class="btn btn-outline-light text-start">
                                        <i class="isax isax-shop me-2"></i>{{ __('tenant.open_supplier_catalog') }}
                                    </a>
                                    <a href="{{ route('automotive.admin.products.index', $workspaceQuery) }}" class="btn btn-outline-light text-start">
                                        <i class="isax isax-box me-2"></i>{{ __('tenant.open_stock_items') }}
                                    </a>
                                </div>
                            @elseif ($focusedProductCode === 'accounting')
                                <p class="text-muted">{{ __('tenant.accounting_focus_note') }}</p>
                                <div class="d-grid gap-2">
                                    <a href="{{ route('automotive.admin.modules.general-ledger', $workspaceQuery) }}" class="btn btn-outline-light text-start">
                                        <i class="isax isax-wallet-3 me-2"></i>{{ __('tenant.open_general_ledger') }}
                                    </a>
                                </div>
                            @else
                                <p class="text-muted">{{ __('tenant.automotive_focus_note') }}</p>
                                <div class="d-grid gap-2">
                                    <a href="{{ route('automotive.admin.modules.workshop-operations', $workspaceQuery) }}" class="btn btn-outline-light text-start">
                                        <i class="isax isax-car me-2"></i>{{ __('tenant.open_workshop_operations') }}
                                    </a>
                                    <a href="{{ route('automotive.admin.users.index', $workspaceQuery) }}" class="btn btn-outline-light text-start">
                                        <i class="isax isax-profile-2user me-2"></i>{{ __('tenant.manage_users') }}
                                    </a>
                                    <a href="{{ route('automotive.admin.branches.index', $workspaceQuery) }}" class="btn btn-outline-light text-start">
                                        <i class="isax isax-buildings me-2"></i>{{ __('tenant.manage_branches') }}
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            @include('automotive.admin.partials.workspace-integrations', [
                'title' => __('tenant.cross_product_integrations'),
                'columnClass' => 'col-xl-4',
            ])

            @if ($focusedProductCode === 'parts_inventory')
                <div class="row">
                    <div class="col-xxl-3 col-xl-4 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="text-gray-6">{{ __('shared.stock_items') }}</span>
                                    <span class="avatar avatar-sm bg-info-transparent rounded-circle">
                                        <i class="isax isax-box text-info"></i>
                                    </span>
                                </div>
                                <h3 class="mb-1">{{ $productsCount }}</h3>
                                <p class="mb-0 text-muted">
                                    @if (!is_null($productLimit['limit']))
                                        {{ __('shared.limit') }}: {{ $productLimit['limit'] }} | {{ __('shared.remaining') }}: {{ $productLimit['remaining'] }}
                                    @else
                                        {{ __('shared.unlimited') }}
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-xxl-3 col-xl-4 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="text-gray-6">{{ __('tenant.inventory_records') }}</span>
                                    <span class="avatar avatar-sm bg-success-transparent rounded-circle">
                                        <i class="isax isax-archive text-success"></i>
                                    </span>
                                </div>
                                <h3 class="mb-1">{{ $inventoriesCount }}</h3>
                                <p class="mb-0 text-muted">{{ __('tenant.branch_product_stock_records') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-xxl-3 col-xl-4 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="text-gray-6">{{ __('shared.stock_transfers') }}</span>
                                    <span class="avatar avatar-sm bg-warning-transparent rounded-circle">
                                        <i class="isax isax-arrow-right-3 text-warning"></i>
                                    </span>
                                </div>
                                <h3 class="mb-1">{{ $stockTransfersCount }}</h3>
                                <p class="mb-0 text-muted">{{ __('tenant.draft_posted_transfers') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-xxl-3 col-xl-4 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="text-gray-6">{{ __('tenant.stock_movements') }}</span>
                                    <span class="avatar avatar-sm bg-danger-transparent rounded-circle">
                                        <i class="isax isax-arrows-swap text-danger"></i>
                                    </span>
                                </div>
                                <h3 class="mb-1">{{ $stockMovementsCount }}</h3>
                                <p class="mb-0 text-muted">{{ __('tenant.opening_stock_adjustments_transfers') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xxl-4 col-xl-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header">
                                <h5 class="card-title mb-0">{{ __('tenant.low_stock_snapshot') }}</h5>
                            </div>
                            <div class="card-body">
                                @forelse ($lowStockItems as $item)
                                    <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-2">
                                        <div>
                                            <h6 class="fs-14 mb-1">{{ $item->product?->name ?? '—' }}</h6>
                                            <p class="mb-0 fs-13 text-muted">{{ $item->branch?->name ?? '—' }}</p>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-danger">{{ $item->quantity }}</span>
                                            <p class="mb-0 fs-12 text-muted">{{ __('tenant.min') }}: {{ $item->product?->min_stock_alert ?? 0 }}</p>
                                        </div>
                                    </div>
                                @empty
                                    <p class="mb-0 text-muted">{{ __('tenant.no_low_stock') }}</p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="col-xxl-4 col-xl-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header">
                                <h5 class="card-title mb-0">{{ __('tenant.recent_transfers') }}</h5>
                            </div>
                            <div class="card-body">
                                @forelse ($recentTransfers as $transfer)
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="fs-14 mb-1">{{ __('tenant.transfer_number', ['id' => $transfer->id]) }}</h6>
                                            <span class="badge {{ ($transfer->status ?? 'draft') === 'posted' ? 'bg-success' : 'bg-warning' }}">
                                                {{ ucfirst($transfer->status ?? 'draft') }}
                                            </span>
                                        </div>
                                        <p class="mb-1 fs-13 text-muted">{{ $transfer->fromBranch?->name ?? '—' }} → {{ $transfer->toBranch?->name ?? '—' }}</p>
                                        <p class="mb-0 fs-12 text-muted">{{ optional($transfer->created_at)->format('Y-m-d H:i') }}</p>
                                    </div>
                                @empty
                                    <p class="mb-0 text-muted">{{ __('tenant.no_stock_transfers') }}</p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="col-xxl-4 col-xl-12 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header">
                                <h5 class="card-title mb-0">{{ __('tenant.recent_stock_movements') }}</h5>
                            </div>
                            <div class="card-body">
                                @forelse ($recentMovements as $movement)
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="fs-14 mb-1">{{ $movement->product?->name ?? '—' }}</h6>
                                            <span class="badge bg-secondary">{{ strtoupper($movement->type ?? 'N/A') }}</span>
                                        </div>
                                        <p class="mb-1 fs-13 text-muted">{{ $movement->branch?->name ?? '—' }}</p>
                                        <p class="mb-0 fs-12 text-muted">{{ __('tenant.qty') }}: {{ $movement->quantity }} | {{ optional($movement->created_at)->format('Y-m-d H:i') }}</p>
                                    </div>
                                @empty
                                    <p class="mb-0 text-muted">{{ __('tenant.no_stock_movements') }}</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="row">
                    <div class="col-xl-12 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header">
                                <h5 class="card-title mb-0">{{ __('tenant.focused_product_notes') }}</h5>
                            </div>
                            <div class="card-body">
                                @if ($focusedProductCode === 'accounting')
                                    <p class="mb-0 text-muted">
                                        {{ __('tenant.accounting_product_note') }}
                                    </p>
                                @else
                                    <p class="mb-0 text-muted">
                                        {{ __('tenant.automotive_product_note') }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @include('automotive.admin.components.page-footer')
        </div>
    </div>
@endsection
