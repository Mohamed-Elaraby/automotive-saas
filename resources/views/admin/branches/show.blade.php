@extends('admin.layouts.app')

@section('title', 'Branches')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 m-0">Branches</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.branches.edit', $row->id) }}" class="btn btn-light">Edit</a>
            <a href="{{ route('admin.branches.index') }}" class="btn btn-light">Back</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">ID</dt>
                <dd class="col-sm-9">{{ $item->id }}</dd>
                {{-- <seven-scaffold-show-fields> --}}
            </dl>
        </div>
    </div>
</div>
@endsection
