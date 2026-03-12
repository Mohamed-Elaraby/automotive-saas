<?php $page = 'users'; ?>
@extends('automotive.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content container-fluid">

            @include('automotive.admin.partials.page-header', [
                'title' => 'Edit User',
                'subtitle' => 'Update tenant user details.',
                'breadcrumbs' => [
                    ['label' => 'Dashboard', 'url' => route('automotive.admin.dashboard')],
                    ['label' => 'Users', 'url' => route('automotive.admin.users.index')],
                    ['label' => 'Edit User'],
                ],
            ])

            <div class="card">
                <div class="card-body">
                    @include('automotive.admin.partials.alerts')

                    <form action="{{ route('automotive.admin.users.update', $user) }}" method="POST">
                        @csrf
                        @method('PUT')
                        @include('automotive.admin.users._form', [
                            'mode' => 'edit',
                            'user' => $user,
                        ])
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
