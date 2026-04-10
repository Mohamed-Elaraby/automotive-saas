<?php $page = 'product-capabilities-create'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>Add Capability</h5>
                    <p class="text-muted mb-0">Create a capability/module for {{ $product->name }}.</p>
                </div>

                <a href="{{ route('admin.products.capabilities.index', $product) }}" class="btn btn-outline-white">Back to Capabilities</a>
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.products.capabilities.store', $product) }}">
                        @csrf
                        @include('admin.product-capabilities._form')

                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Create Capability</button>
                            <a href="{{ route('admin.products.capabilities.index', $product) }}" class="btn btn-outline-white">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
