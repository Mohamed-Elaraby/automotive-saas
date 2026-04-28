<?php $page = 'users'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content container-fluid">

            @include('automotive.admin.partials.page-header', [
                'title' => __('tenant.create_user'),
                'subtitle' => __('tenant.create_user_subtitle'),
                'breadcrumbs' => [
                    ['label' => __('shared.dashboard'), 'url' => route('automotive.admin.dashboard')],
                    ['label' => __('shared.users'), 'url' => route('automotive.admin.users.index')],
                    ['label' => __('tenant.create_user')],
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
