<?php $page = 'stock-transfers'; ?>
@extends('automotive.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content container-fluid">

            @include('automotive.admin.partials.page-header', [
                'title' => 'Create Stock Transfer',
                'subtitle' => 'Move stock between branches as a draft first.',
                'breadcrumbs' => [
                    ['label' => 'Dashboard', 'url' => route('automotive.admin.dashboard')],
                    ['label' => 'Stock Transfers', 'url' => route('automotive.admin.stock-transfers.index')],
                    ['label' => 'Create Transfer'],
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
