<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; background: #f8fafc; color: #111827; }
        .wrap { max-width: 1300px; margin: 0 auto; }
        .top { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; gap:12px; }
        .btn { display:inline-block; padding:10px 14px; border-radius:8px; text-decoration:none; border:0; cursor:pointer; }
        .btn-primary { background:#2563eb; color:#fff; }
        .btn-secondary { background:#374151; color:#fff; }
        .card { background:#fff; padding:20px; border-radius:12px; box-shadow:0 4px 18px rgba(0,0,0,.06); margin-bottom:18px; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:12px; border-bottom:1px solid #e5e7eb; text-align:left; vertical-align:top; }
        input, select { width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; margin-top:6px; }
        label { display:block; font-weight:600; }
        .filters { display:grid; grid-template-columns:2fr 1fr auto; gap:14px; align-items:end; }
        .badge-low { display:inline-block; padding:4px 10px; border-radius:999px; background:#fee2e2; color:#991b1b; font-size:12px; font-weight:700; }
        .badge-normal { display:inline-block; padding:4px 10px; border-radius:999px; background:#dcfce7; color:#166534; font-size:12px; font-weight:700; }
        .muted { color:#6b7280; }

        @media (max-width: 900px) {
            .filters {
                grid-template-columns: 1fr;
            }

            .top {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1>Inventory Report</h1>
            <p class="muted" style="margin:6px 0 0;">Current stock by branch and product.</p>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="/automotive/admin/dashboard" class="btn btn-secondary">Dashboard</a>
            <a href="/automotive/admin/inventory-adjustments" class="btn btn-secondary">Adjustments</a>
            <a href="/automotive/admin/stock-transfers" class="btn btn-primary">Transfers</a>
        </div>
    </div>

    <div class="card">
        <form method="GET" action="/automotive/admin/inventory-report">
            <div class="filters">
                <div>
                    <label>Search</label>
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Product name, SKU, barcode, branch..."
                    >
                </div>

                <div>
                    <label>Branch</label>
                    <select name="branch_id">
                        <option value="">All branches</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string)$branchId === (string)$branch->id)>
                                {{ $branch->name }} ({{ $branch->code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>Branch</th>
                <th>Product</th>
                <th>SKU</th>
                <th>Barcode</th>
                <th>Unit</th>
                <th>Quantity</th>
                <th>Min Alert</th>
                <th>Cost</th>
                <th>Sale</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($inventories as $inventory)
                @php
                    $product = $inventory->product;
                    $isLow = $product && (float) $inventory->quantity <= (float) $product->min_stock_alert;
                @endphp
                <tr>
                    <td>{{ $inventory->branch?->name ?? '—' }}</td>
                    <td>{{ $product?->name ?? '—' }}</td>
                    <td>{{ $product?->sku ?? '—' }}</td>
                    <td>{{ $product?->barcode ?: '—' }}</td>
                    <td>{{ $product?->unit ?? '—' }}</td>
                    <td>{{ $inventory->quantity }}</td>
                    <td>{{ $product?->min_stock_alert ?? 0 }}</td>
                    <td>{{ $product?->cost_price ?? 0 }}</td>
                    <td>{{ $product?->sale_price ?? 0 }}</td>
                    <td>
                        @if ($isLow)
                            <span class="badge-low">Low Stock</span>
                        @else
                            <span class="badge-normal">Normal</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">No inventory records found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
