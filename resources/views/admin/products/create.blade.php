<?php $page = 'products-create'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>Add Product</h5>
                    <p class="text-muted mb-0">Create the base product record first. After saving, continue the full lifecycle from the product builder.</p>
                </div>

                <a href="{{ route('admin.products.index') }}" class="btn btn-outline-white">Back to Products</a>
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.products.store') }}">
                        @csrf
                        @include('admin.products._form')

                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Create Product</button>
                            <a href="{{ route('admin.products.index') }}" class="btn btn-outline-white">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
