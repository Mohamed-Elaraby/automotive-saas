<?php $page = 'users'; ?>
@extends('automotive.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content container-fluid">

            @include('automotive.admin.partials.page-header', [
                'title' => 'Users',
                'subtitle' => 'Manage tenant users and access.',
                'breadcrumbs' => [
                    ['label' => 'Dashboard', 'url' => route('automotive.admin.dashboard')],
                    ['label' => 'Users'],
                ],
            ])

            <div class="mb-3">
                <a href="{{ route('automotive.admin.users.create') }}" class="btn btn-primary">
                    <i class="isax isax-add me-1"></i> Add User
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
                                <th>Email</th>
                                <th>Created At</th>
                                <th class="text-end">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($users as $user)
                                <tr>
                                    <td>{{ $user->name }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td>{{ optional($user->created_at)->format('Y-m-d H:i') }}</td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <a href="{{ route('automotive.admin.users.edit', $user) }}"
                                               class="btn btn-sm btn-outline-primary">
                                                Edit
                                            </a>

                                            <form action="{{ route('automotive.admin.users.destroy', $user) }}"
                                                  method="POST"
                                                  onsubmit="return confirm('Are you sure you want to delete this user?');">
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

                    @if($users->isEmpty())
                        <div class="mt-3">
                            @include('automotive.admin.partials.empty-state', [
                                'title' => 'No users found',
                                'message' => 'Create your first tenant user to start managing access.',
                            ])
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
