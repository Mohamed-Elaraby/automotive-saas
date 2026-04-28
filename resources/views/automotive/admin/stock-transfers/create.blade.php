<?php $page = 'stock-transfers'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content container-fluid">

            @include('automotive.admin.partials.page-header', [
                'title' => __('tenant.create_stock_transfer'),
                'subtitle' => __('tenant.create_stock_transfer_subtitle'),
                'breadcrumbs' => [
                    ['label' => __('shared.dashboard'), 'url' => route('automotive.admin.dashboard')],
                    ['label' => __('tenant.stock_transfers'), 'url' => route('automotive.admin.stock-transfers.index')],
                    ['label' => __('tenant.create_transfer')],
                ],
            ])

            <div class="card">
                <div class="card-body">
                    @include('automotive.admin.partials.alerts')

                    <form action="{{ route('automotive.admin.stock-transfers.store') }}" method="POST">
                        @include('automotive.admin.stock-transfers._form', [
                            'stockTransfer' => $stockTransfer ?? null,
                            'branches' => $branches ?? collect(),
                            'products' => $products ?? collect(),
                        ])
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
