<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Automotive Admin Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 24px;
            background: #f8fafc;
            color: #111827;
        }

        .wrap {
            max-width: 1250px;
            margin: 0 auto;
        }

        .box {
            background: #fff;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 6px 22px rgba(0, 0, 0, .06);
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }

        .top-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn {
            border: 0;
            padding: 10px 16px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #2563eb;
            color: #fff;
        }

        .btn-secondary {
            background: #374151;
            color: #fff;
        }

        .btn-logout {
            background: #dc2626;
            color: #fff;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin: 24px 0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            margin-top: 20px;
        }

        .card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 18px;
        }

        .card h2 {
            margin-top: 0;
            margin-bottom: 14px;
            font-size: 18px;
        }

        .stat-card h3 {
            margin: 0 0 8px;
            font-size: 15px;
            color: #6b7280;
        }

        .stat-value {
            font-size: 30px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .stat-meta {
            color: #6b7280;
            font-size: 14px;
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .meta-row:last-child {
            border-bottom: 0;
        }

        .muted {
            color: #6b7280;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .badge-active {
            background: #dcfce7;
            color: #166534;
        }

        .badge-trialing {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .badge-expired {
            background: #fee2e2;
            color: #991b1b;
        }

        .quick-links {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-top: 12px;
        }

        .quick-link {
            display: block;
            text-decoration: none;
            padding: 14px 16px;
            border-radius: 10px;
            background: #fff;
            border: 1px solid #e5e7eb;
            color: #111827;
            font-weight: 600;
        }

        .quick-link small {
            display: block;
            margin-top: 6px;
            color: #6b7280;
            font-weight: 400;
        }

        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .quick-links {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            .info-grid,
            .stats-grid,
            .quick-links {
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
    <div class="box">
        <div class="top">
            <div>
                <h1 style="margin:0 0 8px;">Automotive Admin Dashboard</h1>
                <p class="muted" style="margin:0;">
                    Welcome, {{ auth('automotive_admin')->user()?->name }}
                </p>
            </div>

            <div class="top-actions">
                <a href="/automotive/admin/users" class="btn btn-secondary">Users</a>
                <a href="/automotive/admin/branches" class="btn btn-secondary">Branches</a>
                <a href="/automotive/admin/products" class="btn btn-secondary">Products</a>

                <form method="POST" action="/automotive/admin/logout" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn btn-logout">Logout</button>
                </form>
            </div>
        </div>

        <div class="stats-grid">
            <div class="card stat-card">
                <h3>Users</h3>
                <div class="stat-value">{{ $usersCount }}</div>
                <div class="stat-meta">
                    @if (!is_null($userLimit['limit']))
                        Limit: {{ $userLimit['limit'] }} | Remaining: {{ $userLimit['remaining'] }}
                    @else
                        Unlimited
                    @endif
                </div>
            </div>

            <div class="card stat-card">
                <h3>Branches</h3>
                <div class="stat-value">{{ $branchesCount }}</div>
                <div class="stat-meta">
                    @if (!is_null($branchLimit['limit']))
                        Limit: {{ $branchLimit['limit'] }} | Remaining: {{ $branchLimit['remaining'] }}
                    @else
                        Unlimited
                    @endif
                </div>
            </div>

            <div class="card stat-card">
                <h3>Products</h3>
                <div class="stat-value">{{ $productsCount }}</div>
                <div class="stat-meta">
                    @if (!is_null($productLimit['limit']))
                        Limit: {{ $productLimit['limit'] }} | Remaining: {{ $productLimit['remaining'] }}
                    @else
                        Unlimited
                    @endif
                </div>
            </div>

            <div class="card stat-card">
                <h3>Inventory Records</h3>
                <div class="stat-value">{{ $inventoriesCount }}</div>
                <div class="stat-meta">Branch-product stock records</div>
            </div>

            <div class="card stat-card">
                <h3>Stock Transfers</h3>
                <div class="stat-value">{{ $stockTransfersCount }}</div>
                <div class="stat-meta">Draft + posted transfers</div>
            </div>

            <div class="card stat-card">
                <h3>Stock Movements</h3>
                <div class="stat-value">{{ $stockMovementsCount }}</div>
                <div class="stat-meta">Opening, adjustments, transfers</div>
            </div>
        </div>

        <div class="info-grid">
            <div class="card">
                <h2>Tenant Overview</h2>

                <div class="meta-row">
                    <span class="muted">Tenant ID</span>
                    <strong>{{ $tenant->id }}</strong>
                </div>

                <div class="meta-row">
                    <span class="muted">Company</span>
                    <strong>{{ data_get($tenant->data, 'company_name', '—') }}</strong>
                </div>

                <div class="meta-row">
                    <span class="muted">Database</span>
                    <strong>{{ data_get($tenant->data, 'db_name', '—') }}</strong>
                </div>
            </div>

            <div class="card">
                <h2>Current Plan</h2>

                <div class="meta-row">
                    <span class="muted">Plan</span>
                    <strong>{{ $plan?->name ?? 'No plan assigned' }}</strong>
                </div>

                <div class="meta-row">
                    <span class="muted">Billing</span>
                    <strong>{{ $plan ? ucfirst($plan->billing_period) : '—' }}</strong>
                </div>

                <div class="meta-row">
                    <span class="muted">Price</span>
                    <strong>
                        @if ($plan)
                            {{ $plan->price }} {{ $plan->currency }}
                        @else
                            —
                        @endif
                    </strong>
                </div>

                <div class="meta-row">
                    <span class="muted">Status</span>
                    <strong>
                        @php
                            $status = $subscription->status ?? 'unknown';
                        @endphp

                        @if ($status === 'active')
                            <span class="badge badge-active">Active</span>
                        @elseif ($status === 'trialing')
                            <span class="badge badge-trialing">Trialing</span>
                        @else
                            <span class="badge badge-expired">{{ ucfirst($status) }}</span>
                        @endif
                    </strong>
                </div>

                <div class="meta-row">
                    <span class="muted">Trial Ends At</span>
                    <strong>
                        {{ !empty($subscription?->trial_ends_at) ? \Carbon\Carbon::parse($subscription->trial_ends_at)->format('Y-m-d H:i') : '—' }}
                    </strong>
                </div>
            </div>

            <div class="card" style="grid-column: 1 / -1;">
                <h2>Quick Navigation</h2>

                <div class="quick-links">
                    <a href="/automotive/admin/users" class="quick-link">
                        Users
                        <small>Manage tenant users and seat usage.</small>
                    </a>

                    <a href="/automotive/admin/branches" class="quick-link">
                        Branches
                        <small>Manage active branches and branch limits.</small>
                    </a>

                    <a href="/automotive/admin/products" class="quick-link">
                        Products
                        <small>Manage product master data and product limits.</small>
                    </a>

                    <a href="/automotive/admin/inventory-adjustments" class="quick-link">
                        Inventory Adjustments
                        <small>Opening stock and manual stock changes.</small>
                    </a>

                    <a href="/automotive/admin/stock-transfers" class="quick-link">
                        Stock Transfers
                        <small>Transfer stock between branches.</small>
                    </a>

                    <a href="/automotive/admin/inventory-report" class="quick-link">
                        Inventory Report
                        <small>View stock by branch and product.</small>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
