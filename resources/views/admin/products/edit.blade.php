<?php $page = 'products-edit'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>{{ __('admin.edit_product') }}</h5>
                    <p class="text-muted mb-0">{{ __('admin.edit_product_intro') }}</p>
                </div>

                <a href="{{ route('admin.products.show', $product) }}" class="btn btn-outline-white">{{ __('admin.back_to_product_builder') }}</a>
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.products.update', $product) }}">
                        @csrf
                        @method('PUT')
                        @include('admin.products._form')

                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">{{ __('admin.save_changes') }}</button>
                            <a href="{{ route('admin.products.index') }}" class="btn btn-outline-white">{{ __('admin.cancel') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
