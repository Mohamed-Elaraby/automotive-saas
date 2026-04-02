<?php $page = 'billing-features'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two pb-0">

            <div class="d-flex d-block align-items-center justify-content-between flex-wrap gap-3 mb-3">
                <div>
                    <h6>Billing Features</h6>
                    <p class="mb-0">Manage the shared features catalog used by billing plans.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.plans.index') }}" class="btn btn-outline-white">Back to Plans</a>
                    <a href="{{ route('admin.billing-features.create') }}" class="btn btn-primary d-flex align-items-center">
                        <i class="isax isax-add-circle5 me-1"></i>New Feature
                    </a>
                </div>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if ($errors->has('delete'))
                <div class="alert alert-danger">{{ $errors->first('delete') }}</div>
            @endif

            <div class="table-responsive">
                <table class="table table-nowrap datatable">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Plans</th>
                        <th>Order</th>
                        <th class="no-sort"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($features as $feature)
                        <tr>
                            <td class="fw-medium text-dark">{{ $feature->name }}</td>
                            <td>{{ $feature->slug }}</td>
                            <td>
                                <small class="text-muted">{{ $feature->description ?: 'No description' }}</small>
                            </td>
                            <td>
                                @if ($feature->is_active)
                                    <span class="badge badge-soft-success d-inline-flex align-items-center">Active</span>
                                @else
                                    <span class="badge badge-soft-danger d-inline-flex align-items-center">Inactive</span>
                                @endif
                            </td>
                            <td>{{ $feature->plans_count }}</td>
                            <td>{{ $feature->sort_order }}</td>
                            <td class="action-item">
                                <a href="javascript:void(0);" data-bs-toggle="dropdown">
                                    <i class="isax isax-more"></i>
                                </a>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a href="{{ route('admin.billing-features.edit', $feature) }}" class="dropdown-item d-flex align-items-center">
                                            <i class="isax isax-edit me-2"></i>Edit
                                        </a>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('admin.billing-features.toggle-active', $feature) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="dropdown-item d-flex align-items-center">
                                                <i class="isax isax-refresh me-2"></i>{{ $feature->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('admin.billing-features.destroy', $feature) }}" onsubmit="return confirm('Are you sure you want to delete this feature?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item d-flex align-items-center text-danger">
                                                <i class="isax isax-trash me-2"></i>Delete
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="text-center py-4">
                                    <p class="mb-0">No billing features found.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

        </div>

        <div class="footer d-sm-flex align-items-center justify-content-between bg-white py-2 px-4 border-top">
            <p class="text-dark mb-0">&copy; 2025 <a href="javascript:void(0);" class="link-primary">Kanakku</a>, All Rights Reserved</p>
            <p class="text-dark">Version : 1.3.8</p>
        </div>
    </div>
@endsection
