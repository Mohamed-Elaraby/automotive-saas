<?php $page = 'stock-transfers'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content container-fluid">

            @include('automotive.admin.partials.page-header', [
                'title' => 'Stock Transfers',
                'subtitle' => 'Create and post stock transfers between branches.',
                'breadcrumbs' => [
                    ['label' => 'Dashboard', 'url' => route('automotive.admin.dashboard')],
                    ['label' => 'Stock Transfers'],
                ],
            ])

            <div class="mb-3">
                <a href="{{ route('automotive.admin.stock-transfers.create') }}" class="btn btn-primary">
                    <i class="isax isax-add me-1"></i> New Transfer
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
                                <th>Date</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($transfers as $transfer)
                                <tr>
                                    <td>{{ $transfer->id }}</td>
                                    <td>{{ optional($transfer->created_at)->format('Y-m-d H:i') }}</td>
                                    <td>{{ $transfer->sourceBranch->name ?? '-' }}</td>
                                    <td>{{ $transfer->destinationBranch->name ?? '-' }}</td>
                                    <td>
                                        <span class="badge {{ $transfer->status === 'posted' ? 'bg-success' : 'bg-warning text-dark' }}">
                                            {{ ucfirst($transfer->status) }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('automotive.admin.stock-transfers.show', $transfer) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @empty
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($transfers->isEmpty())
                        <div class="mt-3">
                            @include('automotive.admin.partials.empty-state', [
                                'title' => 'No stock transfers found',
                                'message' => 'Create your first transfer between branches.',
                            ])
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
