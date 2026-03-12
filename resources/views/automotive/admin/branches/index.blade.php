<?php $page = 'branches'; ?>
@extends('automotive.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content container-fluid">

            @php
                $branchRows = $branches ?? collect();
            @endphp

            @include('automotive.admin.partials.page-header', [
                'title' => 'Branches',
                'subtitle' => 'Manage warehouse and store branches.',
                'breadcrumbs' => [
                    ['label' => 'Dashboard', 'url' => route('automotive.admin.dashboard')],
                    ['label' => 'Branches'],
                ],
            ])

            <div class="mb-3">
                <a href="{{ route('automotive.admin.branches.create') }}" class="btn btn-primary">
                    <i class="isax isax-add me-1"></i> Add Branch
                </a>
            </div>

            <div class="card">
                <div class="card-body">
                    @include('automotive.admin.partials.alerts')

                    <div class="table-responsive">
                        <table class="table table-center table-hover datatable">
                            <thead class="thead-light">
                            <tr>
                                <th>Name</th>
                                <th>Code</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
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
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-danger">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <a href="{{ route('automotive.admin.branches.edit', $branch) }}"
                                               class="btn btn-sm btn-outline-primary">
                                                Edit
                                            </a>

                                            <form action="{{ route('automotive.admin.branches.destroy', $branch) }}"
                                                  method="POST"
                                                  onsubmit="return confirm('Are you sure you want to delete this branch?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    Delete
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
                                'title' => 'No branches found',
                                'message' => 'Create your first branch to manage stock locations.',
                            ])
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
