<?php $page = 'branches'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content container-fluid">

            @include('automotive.admin.partials.page-header', [
                'title' => __('tenant.edit_branch'),
                'subtitle' => __('tenant.edit_branch_subtitle'),
                'breadcrumbs' => [
                    ['label' => __('shared.dashboard'), 'url' => route('automotive.admin.dashboard')],
                    ['label' => __('shared.branches'), 'url' => route('automotive.admin.branches.index')],
                    ['label' => __('tenant.edit_branch')],
                ],
            ])

            <div class="card">
                <div class="card-body">
                    @include('automotive.admin.partials.alerts')

                    <form action="{{ route('automotive.admin.branches.update', $branch) }}" method="POST">
                        @csrf
                        @method('PUT')
                        @include('automotive.admin.branches._form', [
                            'branch' => $branch,
                        ])
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
