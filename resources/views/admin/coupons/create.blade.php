<?php $page = 'admin-coupons-create'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>Create Coupon</h5>
                    <p class="text-muted mb-0">Create a new SaaS discount or promotion coupon.</p>
                </div>

                <a href="{{ route('admin.coupons.index') }}" class="btn btn-light">Back</a>
            </div>

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.coupons.store') }}">
                        @csrf

                        @include('admin.coupons._form')

                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Create Coupon</button>
                            <a href="{{ route('admin.coupons.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
