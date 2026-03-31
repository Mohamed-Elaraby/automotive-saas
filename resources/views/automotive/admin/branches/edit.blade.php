<?php $page = 'branches'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content container-fluid">

            @include('automotive.admin.partials.page-header', [
                'title' => 'Edit Branch',
                'subtitle' => 'Update branch details.',
                'breadcrumbs' => [
                    ['label' => 'Dashboard', 'url' => route('automotive.admin.dashboard')],
                    ['label' => 'Branches', 'url' => route('automotive.admin.branches.index')],
                    ['label' => 'Edit Branch'],
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
