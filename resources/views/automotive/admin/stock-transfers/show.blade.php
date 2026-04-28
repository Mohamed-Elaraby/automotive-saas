<?php $page = 'stock-transfers'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content container-fluid">

            @php
                $transfer = $stockTransfer ?? $transfer ?? null;

                $fromBranchName =
                    $transfer->fromBranch->name
                    ?? $transfer->sourceBranch->name
                    ?? $transfer->branchFrom->name
                    ?? '-';

                $toBranchName =
                    $transfer->toBranch->name
                    ?? $transfer->destinationBranch->name
                    ?? $transfer->branchTo->name
                    ?? '-';

                $transferStatus = $transfer->status ?? 'draft';

                $transferItems =
                    $transfer->items
                    ?? collect();

                if (! $transferItems || (is_countable($transferItems) && count($transferItems) === 0)) {
                    $singleProductId =
                        $transfer->product_id
                        ?? null;

                    $singleProductName =
                        $transfer->product->name
                        ?? '-';

                    $singleQuantity =
                        $transfer->quantity
                        ?? 0;

                    $transferItems = collect([
                        (object) [
                            'product_id' => $singleProductId,
                            'quantity' => $singleQuantity,
                            'product' => (object) [
                                'name' => $singleProductName,
                            ],
                        ]
                    ]);
                }
            @endphp

            @include('automotive.admin.partials.page-header', [
                'title' => __('tenant.transfer_title', ['id' => $transfer->id]),
                'subtitle' => __('tenant.review_post_stock_transfer'),
                'breadcrumbs' => [
                    ['label' => __('shared.dashboard'), 'url' => route('automotive.admin.dashboard')],
                    ['label' => __('tenant.stock_transfers'), 'url' => route('automotive.admin.stock-transfers.index')],
                    ['label' => __('tenant.transfer_title', ['id' => $transfer->id])],
                ],
            ])

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body">
                            @include('automotive.admin.partials.alerts')

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>{{ __('tenant.source_branch') }}:</strong><br>
                                    {{ $fromBranchName }}
                                </div>
                                <div class="col-md-6">
                                    <strong>{{ __('tenant.destination_branch') }}:</strong><br>
                                    {{ $toBranchName }}
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>{{ __('tenant.status') }}:</strong><br>
                                    <span class="badge {{ $transferStatus === 'posted' ? 'bg-success' : 'bg-warning text-dark' }}">
                                        {{ __('tenant.'.($transferStatus === 'posted' ? 'posted' : 'draft')) }}
                                    </span>
                                </div>
                                <div class="col-md-6">
                                    <strong>{{ __('tenant.created_at_label') }}:</strong><br>
                                    {{ optional($transfer->created_at)->format('Y-m-d H:i') }}
                                </div>
                            </div>

                            @if(!empty($transfer->notes))
                                <div class="mb-3">
                                    <strong>{{ __('tenant.notes') }}:</strong><br>
                                    {{ $transfer->notes }}
                                </div>
                            @endif

                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="thead-light">
                                    <tr>
                                        <th>{{ __('tenant.product') }}</th>
                                        <th>{{ __('tenant.quantity') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($transferItems as $item)
                                        <tr>
                                            <td>{{ $item->product->name ?? '-' }}</td>
                                            <td>{{ number_format((float) ($item->quantity ?? 0), 2) }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex gap-2 mt-3">
                                <a href="{{ route('automotive.admin.stock-transfers.index') }}" class="btn btn-light">
                                    {{ __('tenant.back') }}
                                </a>

                                @if($transferStatus === 'draft')
                                    <form action="{{ route('automotive.admin.stock-transfers.post', $transfer) }}"
                                          method="POST"
                                          onsubmit="return confirm('{{ __('tenant.post_transfer_confirm') }}');">
                                        @csrf
                                        <button type="submit" class="btn btn-primary">
                                            {{ __('tenant.post_transfer') }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="mb-3">{{ __('tenant.summary') }}</h6>
                            <p class="mb-2"><strong>{{ __('tenant.transfer_id') }}:</strong> #{{ $transfer->id }}</p>
                            <p class="mb-2"><strong>{{ __('tenant.total_lines') }}:</strong> {{ count($transferItems) }}</p>
                            <p class="mb-0"><strong>{{ __('tenant.status') }}:</strong> {{ __('tenant.'.($transferStatus === 'posted' ? 'posted' : 'draft')) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
