<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Users</title>
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
        th, td { padding:12px; border-bottom:1px solid #e5e7eb; text-align:left; }
        .alert { margin-bottom:16px; padding:12px 14px; border-radius:8px; }
        .alert-success { background:#dcfce7; color:#166534; }
        .alert-error { background:#fee2e2; color:#991b1b; }
        .actions { display:flex; gap:8px; flex-wrap:wrap; }
        form { display:inline; }
        .meta { font-size:14px; color:#4b5563; margin-bottom:16px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1>Tenant Users</h1>
            @if ($limitInfo)
                <div class="meta">
                    Current users: {{ $limitInfo['current'] }}
                    @if (!is_null($limitInfo['limit']))
                        / {{ $limitInfo['limit'] }}
                    @else
                        / Unlimited
                    @endif
                </div>
            @endif
        </div>

        <div style="display:flex; gap:10px;">
            <a href="{{ route('automotive.admin.dashboard') }}" class="btn btn-secondary">Dashboard</a>
            <a href="{{ route('automotive.admin.users.create') }}" class="btn btn-primary">Add User</a>
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
                <th>Email</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>{{ $user->id }}</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ optional($user->created_at)->format('Y-m-d H:i') }}</td>
                    <td>
                        <div class="actions">
                            <a href="{{ route('automotive.admin.users.edit', $user) }}" class="btn btn-secondary">Edit</a>

                            <form method="POST" action="{{ route('automotive.admin.users.destroy', $user) }}" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No users found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
