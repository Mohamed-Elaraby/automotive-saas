<?php $page = 'stock-movements'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content container-fluid">

            @php
                $movementRows = $movements ?? collect();
                $branchRows = $branches ?? collect();
                $productRows = $products ?? collect();
                $movementTypes = $types ?? [];
            @endphp

            @include('automotive.admin.partials.page-header', [
                'title' => 'Stock Movement Report',
                'subtitle' => 'Detailed stock movement history by branch, product, and movement type.',
                'breadcrumbs' => [
                    ['label' => 'Dashboard', 'url' => route('automotive.admin.dashboard')],
                    ['label' => 'Stock Movement Report'],
                ],
            ])

            @include('automotive.admin.partials.alerts')

            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('automotive.admin.stock-movements.index') }}" class="mb-4">
                        <div class="row">
                            <div class="col-lg-3 col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Branch</label>
                                    <select name="branch_id" class="form-control">
                                        <option value="">All branches</option>
                                        @foreach($branchRows as $branch)
                                            <option value="{{ $branch->id }}" {{ (string) request('branch_id', $branchId ?? '') === (string) $branch->id ? 'selected' : '' }}>
                                                {{ $branch->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Product</label>
                                    <select name="product_id" class="form-control">
                                        <option value="">All products</option>
                                        @foreach($productRows as $product)
                                            <option value="{{ $product->id }}" {{ (string) request('product_id', $productId ?? '') === (string) $product->id ? 'selected' : '' }}>
                                                {{ $product->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Type</label>
                                    <select name="type" class="form-control">
                                        <option value="">All types</option>
                                        @foreach($movementTypes as $movementType)
                                            <option value="{{ $movementType }}" {{ request('type', $type ?? '') === $movementType ? 'selected' : '' }}>
                                                {{ ucfirst(str_replace('_', ' ', $movementType)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Search</label>
                                    <input
                                        type="text"
                                        name="search"
                                        class="form-control"
                                        value="{{ request('search', $search ?? '') }}"
                                        placeholder="Product / SKU / branch / notes"
                                    >
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Date From</label>
                                    <input
                                        type="date"
                                        name="date_from"
                                        class="form-control"
                                        value="{{ request('date_from', $dateFrom ?? '') }}"
                                    >
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Date To</label>
                                    <input
                                        type="date"
                                        name="date_to"
                                        class="form-control"
                                        value="{{ request('date_to', $dateTo ?? '') }}"
                                    >
                                </div>
                            </div>

                            <div class="col-lg-6 col-md-12 d-flex align-items-end">
                                <div class="form-group mb-3 w-100 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        Filter
                                    </button>
                                    <a href="{{ route('automotive.admin.stock-movements.index') }}" class="btn btn-light">
                                        Reset
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Branch</th>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Quantity</th>
                                <th>Notes</th>
                                <th>Created By</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($movementRows as $movement)
                                @php
                                    $movementType = $movement->type ?? '-';
                                    $qty = (float) ($movement->quantity ?? 0);

                                    $isNegative = in_array($movementType, ['adjustment_out', 'transfer_out'], true);
                                    $displayQty = $isNegative ? -1 * abs($qty) : abs($qty);
                                @endphp
                                <tr>
                                    <td>{{ $movement->id }}</td>
                                    <td>{{ optional($movement->created_at)->format('Y-m-d H:i') }}</td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            {{ ucfirst(str_replace('_', ' ', $movementType)) }}
                                        </span>
                                    </td>
                                    <td>{{ $movement->branch->name ?? '-' }}</td>
                                    <td>{{ $movement->product->name ?? '-' }}</td>
                                    <td>{{ $movement->product->sku ?? '-' }}</td>
                                    <td>{{ number_format($displayQty, 2) }}</td>
                                    <td>{{ $movement->notes ?? '-' }}</td>
                                    <td>{{ $movement->creator->name ?? '-' }}</td>
                                </tr>
                            @empty
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($movementRows instanceof \Illuminate\Contracts\Pagination\Paginator || $movementRows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                        <div class="mt-3">
                            {{ $movementRows->links() }}
                        </div>
                    @endif

                    @if(($movementRows instanceof \Illuminate\Support\Collection && $movementRows->isEmpty()) || (method_exists($movementRows, 'count') && $movementRows->count() === 0))
                        <div class="mt-3">
                            @include('automotive.admin.partials.empty-state', [
                                'title' => 'No stock movements found',
                                'message' => 'No stock movement records match the selected filters.',
                            ])
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
