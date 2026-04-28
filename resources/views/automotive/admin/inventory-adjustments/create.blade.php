<?php $page = 'inventory-adjustments'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content container-fluid">

            @include('automotive.admin.partials.page-header', [
                'title' => __('tenant.create_inventory_adjustment'),
                'subtitle' => __('tenant.create_inventory_adjustment_subtitle'),
                'breadcrumbs' => [
                    ['label' => __('shared.dashboard'), 'url' => route('automotive.admin.dashboard')],
                    ['label' => __('tenant.inventory_adjustments'), 'url' => route('automotive.admin.inventory-adjustments.index')],
                    ['label' => __('tenant.create_adjustment')],
                ],
            ])

            <div class="card">
                <div class="card-body">
                    @include('automotive.admin.partials.alerts')

                    <form action="{{ route('automotive.admin.inventory-adjustments.store') }}" method="POST">
                        @include('automotive.admin.inventory-adjustments._form', [
                            'inventoryAdjustment' => $inventoryAdjustment ?? null,
                            'branches' => $branches ?? collect(),
                            'products' => $products ?? collect(),
                        ])
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
