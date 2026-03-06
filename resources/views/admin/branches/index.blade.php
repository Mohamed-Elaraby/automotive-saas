@extends('admin.layouts.app')

@section('title', 'Branches')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 m-0">Branches</h1>
        <a href="{{ route('admin.branches.create') }}" class="btn btn-primary">Create</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        {{-- <seven-scaffold-table-columns> --}}
                        <th>ID</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $row)
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-light" href="{{ route('admin.branches.show', $row->id) }}">View</a>
                                <a class="btn btn-sm btn-light" href="{{ route('admin.branches.edit', $row->id) }}">Edit</a>
                                <form action="{{ route('admin.branches.destroy', $row->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted">No data</td></tr>
                    @endforelse
                </tbody>
            </table>

            @if(method_exists($items, 'links'))
                <div class="mt-3">{{ $items->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
