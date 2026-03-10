<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Transfers</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; background: #f8fafc; color: #111827; }
        .wrap { max-width: 1200px; margin: 0 auto; }
        .top { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; gap:12px; }
        .btn { display:inline-block; padding:10px 14px; border-radius:8px; text-decoration:none; border:0; cursor:pointer; }
        .btn-primary { background:#2563eb; color:#fff; }
        .btn-secondary { background:#374151; color:#fff; }
        .card { background:#fff; padding:20px; border-radius:12px; box-shadow:0 4px 18px rgba(0,0,0,.06); }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:12px; border-bottom:1px solid #e5e7eb; text-align:left; vertical-align:top; }
        .alert { margin-bottom:16px; padding:12px 14px; border-radius:8px; }
        .alert-success { background:#dcfce7; color:#166534; }
        .badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; }
        .badge-draft { background:#fef3c7; color:#92400e; }
        .badge-posted { background:#dcfce7; color:#166534; }
        .badge-cancelled { background:#fee2e2; color:#991b1b; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1>Stock Transfers</h1>
            <p style="margin:6px 0 0;color:#6b7280;">Transfer stock between branches.</p>
        </div>

        <div style="display:flex;gap:10px;">
            <a href="/automotive/admin/dashboard" class="btn btn-secondary">Dashboard</a>
            <a href="/automotive/admin/stock-transfers/create" class="btn btn-primary">New Transfer</a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>#</th>
                <th>Reference</th>
                <th>From</th>
                <th>To</th>
                <th>Status</th>
                <th>Date</th>
                <th>Created By</th>
                <th>View</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($transfers as $transfer)
                <tr>
                    <td>{{ $transfer->id }}</td>
                    <td>{{ $transfer->reference }}</td>
                    <td>{{ $transfer->fromBranch?->name ?? '—' }}</td>
                    <td>{{ $transfer->toBranch?->name ?? '—' }}</td>
                    <td>
                        @if ($transfer->status === 'draft')
                            <span class="badge badge-draft">Draft</span>
                        @elseif ($transfer->status === 'posted')
                            <span class="badge badge-posted">Posted</span>
                        @else
                            <span class="badge badge-cancelled">{{ ucfirst($transfer->status) }}</span>
                        @endif
                    </td>
                    <td>{{ optional($transfer->transfer_date)->format('Y-m-d H:i') ?: '—' }}</td>
                    <td>{{ $transfer->creator?->name ?? '—' }}</td>
                    <td>
                        <a href="/automotive/admin/stock-transfers/{{ $transfer->id }}" class="btn btn-secondary">Open</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">No stock transfers found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
