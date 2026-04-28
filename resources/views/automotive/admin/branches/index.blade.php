<?php $page = 'branches'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content container-fluid">

            @php
                $branchRows = $branches ?? collect();
            @endphp

            @include('automotive.admin.partials.page-header', [
                'title' => __('shared.branches'),
                'subtitle' => __('tenant.branches_subtitle'),
                'breadcrumbs' => [
                    ['label' => __('shared.dashboard'), 'url' => route('automotive.admin.dashboard')],
                    ['label' => __('shared.branches')],
                ],
            ])

            <div class="mb-3">
                <a href="{{ route('automotive.admin.branches.create') }}" class="btn btn-primary">
                    <i class="isax isax-add me-1"></i> {{ __('tenant.add_branch') }}
                </a>
            </div>

            <div class="card">
                <div class="card-body">
                    @include('automotive.admin.partials.alerts')

                    <div class="table-responsive">
                        <table class="table table-center table-hover datatable">
                            <thead class="thead-light">
                            <tr>
                                <th>{{ __('tenant.name') }}</th>
                                <th>{{ __('tenant.code') }}</th>
                                <th>{{ __('tenant.phone') }}</th>
                                <th>{{ __('tenant.email') }}</th>
                                <th>{{ __('tenant.status') }}</th>
                                <th class="text-end">{{ __('tenant.actions') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($branchRows as $branch)
                                <tr>
                                    <td>{{ $branch->name }}</td>
                                    <td>{{ $branch->code }}</td>
                                    <td>{{ $branch->phone }}</td>
                                    <td>{{ $branch->email }}</td>
                                    <td>
                                        @if((bool) ($branch->is_active ?? true))
                                            <span class="badge bg-success">{{ __('tenant.active') }}</span>
                                        @else
                                            <span class="badge bg-danger">{{ __('tenant.inactive') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <a href="{{ route('automotive.admin.branches.edit', $branch) }}"
                                               class="btn btn-sm btn-outline-primary">
                                                {{ __('tenant.edit') }}
                                            </a>

                                            <form action="{{ route('automotive.admin.branches.destroy', $branch) }}"
                                                  method="POST"
                                                  onsubmit="return confirm('{{ __('tenant.delete_branch_confirm') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    {{ __('tenant.delete') }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($branchRows->isEmpty())
                        <div class="mt-3">
                            @include('automotive.admin.partials.empty-state', [
                                'title' => __('tenant.no_branches_found'),
                                'message' => __('tenant.no_branches_message'),
                            ])
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
