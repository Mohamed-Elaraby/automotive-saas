<?php $page = 'users'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content container-fluid">

            @include('automotive.admin.partials.page-header', [
                'title' => __('tenant.edit_user'),
                'subtitle' => __('tenant.edit_user_subtitle'),
                'breadcrumbs' => [
                    ['label' => __('shared.dashboard'), 'url' => route('automotive.admin.dashboard')],
                    ['label' => __('shared.users'), 'url' => route('automotive.admin.users.index')],
                    ['label' => __('tenant.edit_user')],
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
