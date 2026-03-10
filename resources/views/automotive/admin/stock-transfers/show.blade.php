<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Transfer {{ $stockTransfer->reference }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; background: #f8fafc; color: #111827; }
        .wrap { max-width: 1100px; margin: 0 auto; }
        .card { background:#fff; padding:20px; border-radius:12px; box-shadow:0 4px 18px rgba(0,0,0,.06); margin-bottom:18px; }
        .top { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; gap:12px; }
        .btn { display:inline-block; padding:10px 14px; border-radius:8px; text-decoration:none; border:0; cursor:pointer; }
        .btn-primary { background:#2563eb; color:#fff; }
        .btn-secondary { background:#374151; color:#fff; }
        .meta-row { display:flex; justify-content:space-between; gap:12px; padding:10px 0; border-bottom:1px solid #e5e7eb; }
        .meta-row:last-child { border-bottom:0; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:12px; border-bottom:1px solid #e5e7eb; text-align:left; }
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
            <h1>Stock Transfer: {{ $stockTransfer->reference }}</h1>
        </div>

        <div style="display:flex;gap:10px;">
            <a href="/automotive/admin/stock-transfers" class="btn btn-secondary">Back</a>

            @if ($stockTransfer->status === 'draft')
                <form method="POST" action="/automotive/admin/stock-transfers/{{ $stockTransfer->id }}/post" style="margin:0;" onsubmit="return confirm('Are you sure you want to post this transfer?');">
                    @csrf
                    <button type="submit" class="btn btn-primary">Post Transfer</button>
                </form>
            @endif
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert" style="background:#fee2e2;color:#991b1b;">
            <ul style="margin:0; padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="meta-row">
            <span>Reference</span>
            <strong>{{ $stockTransfer->reference }}</strong>
        </div>

        <div class="meta-row">
            <span>From Branch</span>
            <strong>{{ $stockTransfer->fromBranch?->name ?? '—' }}</strong>
        </div>

        <div class="meta-row">
            <span>To Branch</span>
            <strong>{{ $stockTransfer->toBranch?->name ?? '—' }}</strong>
        </div>

        <div class="meta-row">
            <span>Status</span>
            <strong>
                @if ($stockTransfer->status === 'draft')
                    <span class="badge badge-draft">Draft</span>
                @elseif ($stockTransfer->status === 'posted')
                    <span class="badge badge-posted">Posted</span>
                @else
                    <span class="badge badge-cancelled">{{ ucfirst($stockTransfer->status) }}</span>
                @endif
            </strong>
        </div>

        <div class="meta-row">
            <span>Date</span>
            <strong>{{ optional($stockTransfer->transfer_date)->format('Y-m-d H:i') ?: '—' }}</strong>
        </div>

        <div class="meta-row">
            <span>Created By</span>
            <strong>{{ $stockTransfer->creator?->name ?? '—' }}</strong>
        </div>

        <div class="meta-row">
            <span>Notes</span>
            <strong>{{ $stockTransfer->notes ?: '—' }}</strong>
        </div>
    </div>

    <div class="card">
        <h2>Items</h2>

        <table>
            <thead>
            <tr>
                <th>#</th>
                <th>Product</th>
                <th>SKU</th>
                <th>Quantity</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($stockTransfer->items as $item)
                <tr>
                    <td>{{ $item->id }}</td>
                    <td>{{ $item->product?->name ?? '—' }}</td>
                    <td>{{ $item->product?->sku ?? '—' }}</td>
                    <td>{{ $item->quantity }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">No items found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
