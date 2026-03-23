<?php $page = 'reference-cities-edit'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>Edit City</h5>
                    <p class="text-muted mb-0">Update the selected city reference record.</p>
                </div>

                <a href="{{ route('admin.reference-data.cities.index') }}" class="btn btn-light">Back</a>
            </div>

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.reference-data.cities.update', $city->id) }}">
                        @csrf
                        @method('PUT')

                        @include('admin.reference-data.cities._form')

                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Update City</button>
                            <a href="{{ route('admin.reference-data.cities.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
