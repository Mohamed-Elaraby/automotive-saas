<?php $page = 'stock-transfers'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content container-fluid">

            @include('automotive.admin.partials.page-header', [
                'title' => __('tenant.stock_transfers'),
                'subtitle' => __('tenant.stock_transfers_subtitle'),
                'breadcrumbs' => [
                    ['label' => __('shared.dashboard'), 'url' => route('automotive.admin.dashboard')],
                    ['label' => __('tenant.stock_transfers')],
                ],
            ])

            <div class="mb-3">
                <a href="{{ route('automotive.admin.stock-transfers.create') }}" class="btn btn-primary">
                    <i class="isax isax-add me-1"></i> {{ __('tenant.new_transfer') }}
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
                                <th>{{ __('tenant.from') }}</th>
                                <th>{{ __('tenant.to') }}</th>
                                <th>{{ __('tenant.status') }}</th>
                                <th class="text-end">{{ __('tenant.actions') }}</th>
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
                                            {{ __('tenant.'.($transfer->status === 'posted' ? 'posted' : 'draft')) }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('automotive.admin.stock-transfers.show', $transfer) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            {{ __('tenant.view') }}
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
                                'title' => __('tenant.no_stock_transfers_found'),
                                'message' => __('tenant.no_stock_transfers_message'),
                            ])
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
