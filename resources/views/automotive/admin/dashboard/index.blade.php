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
            max-width: 1100px;
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
        }

        .btn {
            border: 0;
            padding: 10px 16px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .btn-users {
            background: #2563eb;
            color: #fff;
        }

        .btn-logout {
            background: #dc2626;
            color: #fff;
        }

        .grid {
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

        .stat {
            font-size: 28px;
            font-weight: 700;
            margin: 6px 0 8px;
        }

        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .top {
                flex-direction: column;
                align-items: flex-start;
            }

            .top-actions {
                flex-wrap: wrap;
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
                <a href="/automotive/admin/users" class="btn btn-users">Users</a>

                <form method="POST" action="/automotive/admin/logout" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn btn-logout">Logout</button>
                </form>
            </div>
        </div>

        <p>You are logged in successfully.</p>

        <div class="grid">
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

            <div class="card">
                <h2>User Limit</h2>

                <div class="stat">
                    {{ $userLimit['current'] }}
                    @if (!is_null($userLimit['limit']))
                        / {{ $userLimit['limit'] }}
                    @else
                        / ∞
                    @endif
                </div>

                <div class="meta-row">
                    <span class="muted">Current Users</span>
                    <strong>{{ $userLimit['current'] }}</strong>
                </div>

                <div class="meta-row">
                    <span class="muted">Max Users</span>
                    <strong>{{ is_null($userLimit['limit']) ? 'Unlimited' : $userLimit['limit'] }}</strong>
                </div>

                <div class="meta-row">
                    <span class="muted">Remaining</span>
                    <strong>{{ is_null($userLimit['remaining']) ? 'Unlimited' : $userLimit['remaining'] }}</strong>
                </div>
            </div>

            <div class="card">
                <h2>Quick Info</h2>

                <div class="meta-row">
                    <span class="muted">Logged-in User</span>
                    <strong>{{ auth('automotive_admin')->user()?->email }}</strong>
                </div>

                <div class="meta-row">
                    <span class="muted">Features Configured</span>
                    <strong>{{ is_array($plan?->features) ? count($plan->features) : 0 }}</strong>
                </div>

                <div class="meta-row">
                    <span class="muted">Plan Slug</span>
                    <strong>{{ $plan?->slug ?? '—' }}</strong>
                </div>

                <div class="meta-row">
                    <span class="muted">Sort Order</span>
                    <strong>{{ $plan?->sort_order ?? '—' }}</strong>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
