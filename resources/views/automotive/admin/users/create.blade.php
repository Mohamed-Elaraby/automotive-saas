<?php $page = 'users'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content container-fluid">

            @include('automotive.admin.partials.page-header', [
                'title' => 'Create User',
                'subtitle' => 'Add a new tenant user.',
                'breadcrumbs' => [
                    ['label' => 'Dashboard', 'url' => route('automotive.admin.dashboard')],
                    ['label' => 'Users', 'url' => route('automotive.admin.users.index')],
                    ['label' => 'Create User'],
                ],
            ])

            <div class="card">
                <div class="card-body">
                    @include('automotive.admin.partials.alerts')

                    <form action="{{ route('automotive.admin.users.store') }}" method="POST">
                        @include('automotive.admin.users._form', [
                            'mode' => 'create',
                        ])
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
