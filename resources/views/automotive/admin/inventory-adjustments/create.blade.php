<?php $page = 'inventory-adjustments'; ?>
@extends('automotive.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content container-fluid">

            @include('automotive.admin.partials.page-header', [
                'title' => 'Create Inventory Adjustment',
                'subtitle' => 'Apply opening balance or stock adjustment.',
                'breadcrumbs' => [
                    ['label' => 'Dashboard', 'url' => route('automotive.admin.dashboard')],
                    ['label' => 'Inventory Adjustments', 'url' => route('automotive.admin.inventory-adjustments.index')],
                    ['label' => 'Create Adjustment'],
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
