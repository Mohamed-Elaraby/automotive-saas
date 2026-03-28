<?php $page = 'admin-coupons-edit'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>Edit Coupon</h5>
                    <p class="text-muted mb-0">Update coupon rules, status, and plan applicability.</p>
                </div>

                <a href="{{ route('admin.coupons.index') }}" class="btn btn-light">Back</a>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.coupons.update', $coupon) }}">
                        @csrf
                        @method('PUT')

                        @include('admin.coupons._form')

                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <a href="{{ route('admin.coupons.index') }}" class="btn btn-light">Back</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.coupons.toggle-active', $coupon) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-warning">
                            {{ $coupon->is_active ? 'Deactivate Coupon' : 'Activate Coupon' }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}" onsubmit="return confirm('Delete this coupon?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Delete Coupon</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
