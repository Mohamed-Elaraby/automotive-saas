<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Adjustments</title>
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
        .type-opening { color:#1d4ed8; font-weight:700; }
        .type-in { color:#166534; font-weight:700; }
        .type-out { color:#991b1b; font-weight:700; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1>Inventory Adjustments</h1>
            <p style="margin:6px 0 0;color:#6b7280;">Opening stock and manual stock adjustments.</p>
        </div>

        <div style="display:flex;gap:10px;">
            <a href="/automotive/admin/dashboard" class="btn btn-secondary">Dashboard</a>
            <a href="/automotive/admin/inventory-adjustments/create" class="btn btn-primary">New Adjustment</a>
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
                <th>Branch</th>
                <th>Product</th>
                <th>Type</th>
                <th>Quantity</th>
                <th>Created By</th>
                <th>Date</th>
                <th>Notes</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($movements as $movement)
                <tr>
                    <td>{{ $movement->id }}</td>
                    <td>{{ $movement->branch?->name ?? '—' }}</td>
                    <td>{{ $movement->product?->name ?? '—' }}</td>
                    <td>
                        @if ($movement->type === 'opening')
                            <span class="type-opening">Opening</span>
                        @elseif ($movement->type === 'adjustment_in')
                            <span class="type-in">Adjustment In</span>
                        @elseif ($movement->type === 'adjustment_out')
                            <span class="type-out">Adjustment Out</span>
                        @else
                            {{ $movement->type }}
                        @endif
                    </td>
                    <td>{{ $movement->quantity }}</td>
                    <td>{{ $movement->creator?->name ?? '—' }}</td>
                    <td>{{ optional($movement->movement_date)->format('Y-m-d H:i') }}</td>
                    <td>{{ $movement->notes ?: '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">No inventory adjustments found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
