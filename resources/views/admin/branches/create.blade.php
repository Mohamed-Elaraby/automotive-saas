@extends('admin.layouts.app')

@section('title', 'Branches')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 m-0">Branches</h1>
        <a href="{{ route('admin.branches.index') }}" class="btn btn-light">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.branches.store') }}">
                @csrf
                @include('admin.branches._form', ['item' => null])

                <div class="mt-3 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Save</button>
                    <a class="btn btn-outline-secondary" href="{{ route('admin.branches.index') }}">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
