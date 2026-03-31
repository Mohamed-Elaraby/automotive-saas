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
                'title' => 'Transfer #'.$transfer->id,
                'subtitle' => 'Review and post stock transfer.',
                'breadcrumbs' => [
                    ['label' => 'Dashboard', 'url' => route('automotive.admin.dashboard')],
                    ['label' => 'Stock Transfers', 'url' => route('automotive.admin.stock-transfers.index')],
                    ['label' => 'Transfer #'.$transfer->id],
                ],
            ])

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body">
                            @include('automotive.admin.partials.alerts')

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Source Branch:</strong><br>
                                    {{ $fromBranchName }}
                                </div>
                                <div class="col-md-6">
                                    <strong>Destination Branch:</strong><br>
                                    {{ $toBranchName }}
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Status:</strong><br>
                                    <span class="badge {{ $transferStatus === 'posted' ? 'bg-success' : 'bg-warning text-dark' }}">
                                        {{ ucfirst($transferStatus) }}
                                    </span>
                                </div>
                                <div class="col-md-6">
                                    <strong>Created At:</strong><br>
                                    {{ optional($transfer->created_at)->format('Y-m-d H:i') }}
                                </div>
                            </div>

                            @if(!empty($transfer->notes))
                                <div class="mb-3">
                                    <strong>Notes:</strong><br>
                                    {{ $transfer->notes }}
                                </div>
                            @endif

                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="thead-light">
                                    <tr>
                                        <th>Product</th>
                                        <th>Quantity</th>
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
                                    Back
                                </a>

                                @if($transferStatus === 'draft')
                                    <form action="{{ route('automotive.admin.stock-transfers.post', $transfer) }}"
                                          method="POST"
                                          onsubmit="return confirm('Are you sure you want to post this transfer?');">
                                        @csrf
                                        <button type="submit" class="btn btn-primary">
                                            Post Transfer
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
                            <h6 class="mb-3">Summary</h6>
                            <p class="mb-2"><strong>Transfer ID:</strong> #{{ $transfer->id }}</p>
                            <p class="mb-2"><strong>Total Lines:</strong> {{ count($transferItems) }}</p>
                            <p class="mb-0"><strong>Status:</strong> {{ ucfirst($transferStatus) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
