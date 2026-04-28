<?php $page = 'inventory-adjustments'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content container-fluid">

            @php
                $adjustmentRows =
                    $movements
                    ?? $adjustments
                    ?? $inventoryAdjustments
                    ?? $inventoryAdjustmentRows
                    ?? $records
                    ?? $data
                    ?? collect();

                if ($adjustmentRows instanceof \Illuminate\Pagination\AbstractPaginator) {
                    $adjustmentCollection = collect($adjustmentRows->items());
                } else {
                    $adjustmentCollection = collect($adjustmentRows);
                }
            @endphp

            @include('automotive.admin.partials.page-header', [
                'title' => __('tenant.inventory_adjustments'),
                'subtitle' => __('tenant.inventory_adjustments_subtitle'),
                'breadcrumbs' => [
                    ['label' => __('shared.dashboard'), 'url' => route('automotive.admin.dashboard')],
                    ['label' => __('tenant.inventory_adjustments')],
                ],
            ])

            <div class="mb-3">
                <a href="{{ route('automotive.admin.inventory-adjustments.create') }}" class="btn btn-primary">
                    <i class="isax isax-add me-1"></i> {{ __('tenant.new_adjustment') }}
                </a>
            </div>

            <div class="card">
                <div class="card-body">
                    @include('automotive.admin.partials.alerts')

                    <div class="table-responsive">
                        <table class="table table-hover datatable">
                            <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>{{ __('tenant.date') }}</th>
                                <th>{{ __('tenant.type') }}</th>
                                <th>{{ __('tenant.branch') }}</th>
                                <th>{{ __('tenant.product') }}</th>
                                <th>{{ __('tenant.qty') }}</th>
                                <th>{{ __('tenant.notes') }}</th>
                                <th>{{ __('tenant.created_by') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($adjustmentCollection as $movement)
                                @php
                                    $movementId = $movement->id ?? '-';
                                    $movementDate = $movement->created_at ?? null;
                                    $movementType = $movement->type ?? '-';
                                    $branchName = $movement->branch->name ?? '-';
                                    $productName = $movement->product->name ?? '-';
                                    $qty = $movement->quantity ?? 0;
                                    $notes = $movement->notes ?? '-';
                                    $createdBy = $movement->creator->name ?? '-';
                                @endphp

                                <tr>
                                    <td>{{ $movementId }}</td>
                                    <td>{{ $movementDate ? \Illuminate\Support\Carbon::parse($movementDate)->format('Y-m-d H:i') : '-' }}</td>
                                    <td>
                                        <span class="badge bg-light text-dark">{{ $movementType }}</span>
                                    </td>
                                    <td>{{ $branchName }}</td>
                                    <td>{{ $productName }}</td>
                                    <td>{{ number_format((float) $qty, 2) }}</td>
                                    <td>{{ $notes }}</td>
                                    <td>{{ $createdBy }}</td>
                                </tr>
                            @empty
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($adjustmentCollection->isEmpty())
                        <div class="mt-3">
                            @include('automotive.admin.partials.empty-state', [
                                'title' => __('tenant.no_inventory_adjustments_found'),
                                'message' => __('tenant.no_inventory_adjustments_message'),
                            ])
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
