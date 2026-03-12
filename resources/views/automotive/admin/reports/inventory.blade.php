<?php $page = 'inventory-report'; ?>
@extends('automotive.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content container-fluid">

            @include('automotive.admin.partials.page-header', [
                'title' => 'Inventory Report',
                'subtitle' => 'Current stock balance by branch and product.',
                'breadcrumbs' => [
                    ['label' => 'Dashboard', 'url' => route('automotive.admin.dashboard')],
                    ['label' => 'Inventory Report'],
                ],
            ])

            <div class="card">
                <div class="card-body">
                    @include('automotive.admin.partials.alerts')

                    <form method="GET" action="{{ route('automotive.admin.inventory-report.index') }}" class="mb-4">
                        <div class="row">
                            <div class="col-lg-4 col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Branch</label>
                                    <select name="branch_id" class="form-control">
                                        <option value="">All branches</option>
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>
                                                {{ $branch->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-4 col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Search</label>
                                    <input
                                        type="text"
                                        name="search"
                                        class="form-control"
                                        value="{{ request('search') }}"
                                        placeholder="Search by product name / SKU / code"
                                    >
                                </div>
                            </div>

                            <div class="col-lg-4 col-md-12 d-flex align-items-end">
                                <div class="form-group mb-3 w-100 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        Filter
                                    </button>
                                    <a href="{{ route('automotive.admin.inventory-report.index') }}" class="btn btn-light">
                                        Reset
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover datatable">
                            <thead class="thead-light">
                            <tr>
                                <th>Branch</th>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Current Stock</th>
                                <th>Min Alert</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($rows as $row)
                                @php
                                    $currentQty = (float) ($row->quantity ?? 0);
                                    $minAlert = (float) ($row->min_stock_alert ?? 0);
                                    $isLowStock = $minAlert > 0 && $currentQty <= $minAlert;
                                @endphp
                                <tr>
                                    <td>{{ $row->branch_name ?? $row->branch->name ?? '-' }}</td>
                                    <td>{{ $row->product_name ?? $row->product->name ?? '-' }}</td>
                                    <td>{{ $row->product_sku ?? $row->product->sku ?? '-' }}</td>
                                    <td>{{ number_format($currentQty, 2) }}</td>
                                    <td>{{ number_format($minAlert, 2) }}</td>
                                    <td>
                                        @if($isLowStock)
                                            <span class="badge bg-danger">Low Stock</span>
                                        @else
                                            <span class="badge bg-success">OK</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($rows->isEmpty())
                        <div class="mt-3">
                            @include('automotive.admin.partials.empty-state', [
                                'title' => 'No inventory data found',
                                'message' => 'The report currently shows no stock balances for the selected filters.',
                            ])
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
