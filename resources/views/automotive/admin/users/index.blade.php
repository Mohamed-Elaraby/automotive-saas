<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plans</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; background: #f8fafc; color: #111827; }
        .wrap { max-width: 1200px; margin: 0 auto; }
        .top { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
        .btn { display:inline-block; padding:10px 14px; border-radius:8px; text-decoration:none; border:0; cursor:pointer; }
        .btn-primary { background:#2563eb; color:#fff; }
        .btn-warning { background:#f59e0b; color:#fff; }
        .btn-secondary { background:#374151; color:#fff; }
        .card { background:#fff; padding:20px; border-radius:12px; box-shadow:0 4px 18px rgba(0,0,0,.06); }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:12px; border-bottom:1px solid #e5e7eb; text-align:left; vertical-align:top; }
        .badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; }
        .badge-active { background:#dcfce7; color:#166534; }
        .badge-inactive { background:#fee2e2; color:#991b1b; }
        .actions { display:flex; gap:8px; flex-wrap:wrap; }
        form { display:inline; }
        .alert { margin-bottom:16px; padding:12px 14px; background:#dcfce7; color:#166534; border-radius:8px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <h1>Plans</h1>
        <a href="{{ route('admin.plans.create') }}" class="btn btn-primary">Add Plan</a>
    </div>

    @if (session('success'))
        <div class="alert">{{ session('success') }}</div>
    @endif

    @if ($errors->has('delete'))
        <div class="alert" style="background:#fee2e2;color:#991b1b;">
            {{ $errors->first('delete') }}
        </div>
    @endif

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Slug</th>
                <th>Price</th>
                <th>Billing</th>
                <th>Limits</th>
                <th>Status</th>
                <th>Order</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($plans as $plan)
                <tr>
                    <td>{{ $plan->id }}</td>
                    <td>{{ $plan->name }}</td>
                    <td>{{ $plan->slug }}</td>
                    <td>{{ $plan->price }} {{ $plan->currency }}</td>
                    <td>{{ ucfirst($plan->billing_period) }}</td>
                    <td>
                        Users: {{ $plan->max_users ?? '—' }}<br>
                        Branches: {{ $plan->max_branches ?? '—' }}<br>
                        Products: {{ $plan->max_products ?? '—' }}<br>
                        Storage: {{ $plan->max_storage_mb ?? '—' }} MB
                    </td>
                    <td>
                        @if ($plan->is_active)
                            <span class="badge badge-active">Active</span>
                        @else
                            <span class="badge badge-inactive">Inactive</span>
                        @endif
                    </td>
                    <td>{{ $plan->sort_order }}</td>
                    <td>
                        <div class="actions">
                            <a href="{{ route('admin.plans.edit', $plan) }}" class="btn btn-secondary">Edit</a>

                            <form method="POST" action="{{ route('admin.plans.toggle-active', $plan) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-warning">
                                    {{ $plan->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>

                            <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" onsubmit="return confirm('Are you sure you want to delete this plan?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn" style="background:#dc2626;color:#fff;">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">No plans found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
