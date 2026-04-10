<?php $page = 'plan-create'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two pb-0">

            <div class="d-flex d-block align-items-center justify-content-between flex-wrap gap-3 mb-3">
                <div>
                    <h6>Create Plan</h6>
                    <p class="mb-0">Add a new billing plan to the central catalog.</p>
                </div>
                <div>
                    <a href="{{ route('admin.plans.index') }}" class="btn btn-outline-white">Back to Plans</a>
                </div>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form id="admin-plan-form" method="POST" action="{{ route('admin.plans.store') }}">
                @csrf

                @include('admin.plans._form')

                <div class="d-flex align-items-center justify-content-end gap-2 mt-4">
                    <a href="{{ route('admin.plans.index') }}" class="btn btn-outline-white">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Plan</button>
                </div>
            </form>

        </div>

        <div class="footer d-sm-flex align-items-center justify-content-between bg-white py-2 px-4 border-top">
            <p class="text-dark mb-0">&copy; 2025 <a href="javascript:void(0);" class="link-primary">Kanakku</a>, All Rights Reserved</p>
            <p class="text-dark">Version : 1.3.8</p>
        </div>
    </div>
@endsection
