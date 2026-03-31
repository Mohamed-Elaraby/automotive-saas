<?php $page = 'branches'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content container-fluid">

            @include('automotive.admin.partials.page-header', [
                'title' => 'Create Branch',
                'subtitle' => 'Add a new branch.',
                'breadcrumbs' => [
                    ['label' => 'Dashboard', 'url' => route('automotive.admin.dashboard')],
                    ['label' => 'Branches', 'url' => route('automotive.admin.branches.index')],
                    ['label' => 'Create Branch'],
                ],
            ])

            <div class="card">
                <div class="card-body">
                    @include('automotive.admin.partials.alerts')

                    <form action="{{ route('automotive.admin.branches.store') }}" method="POST">
                        @include('automotive.admin.branches._form', [
                            'branch' => $branch ?? null,
                        ])
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
