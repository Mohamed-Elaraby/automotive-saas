<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branches</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; background: #f8fafc; color: #111827; }
        .wrap { max-width: 1100px; margin: 0 auto; }
        .top { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; gap:12px; }
        .btn { display:inline-block; padding:10px 14px; border-radius:8px; text-decoration:none; border:0; cursor:pointer; }
        .btn-primary { background:#2563eb; color:#fff; }
        .btn-secondary { background:#374151; color:#fff; }
        .btn-danger { background:#dc2626; color:#fff; }
        .card { background:#fff; padding:20px; border-radius:12px; box-shadow:0 4px 18px rgba(0,0,0,.06); }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:12px; border-bottom:1px solid #e5e7eb; text-align:left; vertical-align:top; }
        .alert { margin-bottom:16px; padding:12px 14px; border-radius:8px; }
        .alert-success { background:#dcfce7; color:#166534; }
        .alert-error { background:#fee2e2; color:#991b1b; }
        .badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; }
        .badge-active { background:#dcfce7; color:#166534; }
        .badge-inactive { background:#fee2e2; color:#991b1b; }
        .actions { display:flex; gap:8px; flex-wrap:wrap; }
        form { display:inline; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1>Branches</h1>
            @if (!empty($limitInfo))
                <p style="margin:6px 0 0;color:#6b7280;">
                    Current branches: {{ $limitInfo['current'] }}
                    @if (!is_null($limitInfo['limit']))
                        / {{ $limitInfo['limit'] }} — Remaining: {{ $limitInfo['remaining'] }}
                    @else
                        / Unlimited
                    @endif
                </p>
            @else
                <p style="margin:6px 0 0;color:#6b7280;">Manage your tenant branches.</p>
            @endif
            <p style="margin:6px 0 0;color:#6b7280;">Manage your tenant branches.</p>
        </div>

        <div style="display:flex;gap:10px;">
            <a href="/automotive/admin/dashboard" class="btn btn-secondary">Dashboard</a>
            <a href="/automotive/admin/branches/create" class="btn btn-primary">Add Branch</a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->has('limit'))
        <div class="alert alert-error">{{ $errors->first('limit') }}</div>
    @endif

    @if ($errors->has('delete'))
        <div class="alert alert-error">{{ $errors->first('delete') }}</div>
    @endif

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Code</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Address</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($branches as $branch)
                <tr>
                    <td>{{ $branch->id }}</td>
                    <td>{{ $branch->name }}</td>
                    <td>{{ $branch->code }}</td>
                    <td>{{ $branch->phone ?: '—' }}</td>
                    <td>{{ $branch->email ?: '—' }}</td>
                    <td>{{ $branch->address ?: '—' }}</td>
                    <td>
                        @if ($branch->is_active)
                            <span class="badge badge-active">Active</span>
                        @else
                            <span class="badge badge-inactive">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <div class="actions">
                            <a href="/automotive/admin/branches/{{ $branch->id }}/edit" class="btn btn-secondary">Edit</a>

                            <form method="POST" action="/automotive/admin/branches/{{ $branch->id }}" onsubmit="return confirm('Are you sure you want to delete this branch?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">No branches found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
