<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - Automotive SaaS</title>
    <style>
        :root {
            --bg: #f4f7fb;
            --card: #ffffff;
            --text: #111827;
            --muted: #6b7280;
            --primary: #1d4ed8;
            --success: #166534;
            --warning: #b45309;
            --danger: #b91c1c;
            --border: #e5e7eb;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        .wrapper {
            min-height: 100vh;
            padding: 36px 20px 60px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 28px;
        }
        .title h1 {
            margin: 0 0 8px;
            font-size: 32px;
        }
        .title p {
            margin: 0;
            color: var(--muted);
        }
        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-block;
            text-decoration: none;
            border: 0;
            border-radius: 12px;
            padding: 14px 18px;
            font-weight: bold;
            cursor: pointer;
        }
        .btn-primary {
            background: var(--primary);
            color: #fff;
        }
        .btn-light {
            background: #fff;
            color: var(--text);
            border: 1px solid var(--border);
        }
        .btn-success {
            background: #166534;
            color: #fff;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 24px;
        }
        .col-8 { grid-column: span 8; }
        .col-4 { grid-column: span 4; }
        .card {
            background: var(--card);
            border-radius: 18px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, .06);
            padding: 24px;
        }
        .card h3 {
            margin-top: 0;
            margin-bottom: 16px;
            font-size: 20px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 18px;
        }
        .stat-label {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 8px;
        }
        .stat-value {
            font-size: 20px;
            font-weight: bold;
        }
        .badge {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-success { background: #dcfce7; color: var(--success); }
        .badge-info { background: #dbeafe; color: #1d4ed8; }
        .badge-warning { background: #fef3c7; color: var(--warning); }
        .badge-danger { background: #fee2e2; color: var(--danger); }
        .badge-muted { background: #e5e7eb; color: #374151; }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th,
        .table td {
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
            text-align: left;
            vertical-align: top;
        }
        .table th {
            width: 220px;
            color: var(--muted);
            font-weight: normal;
        }
        .muted {
            color: var(--muted);
        }
        .notice {
            border-radius: 14px;
            padding: 16px 18px;
            margin-bottom: 16px;
            line-height: 1.7;
        }
        .notice-info {
            background: #eff6ff;
            color: #1e3a8a;
        }
        .notice-warning {
            background: #fff7ed;
            color: #9a3412;
        }
        .notice-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        .domains {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .domain-item {
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px;
        }
        .domain-item a {
            color: var(--primary);
            text-decoration: none;
        }
        .stack {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .full-button-form {
            width: 100%;
        }
        .full-button-form button,
        .full-button-form a {
            width: 100%;
            text-align: center;
        }
        @media (max-width: 992px) {
            .col-8,
            .col-4 {
                grid-column: span 12;
            }
            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 640px) {
            .stats {
                grid-template-columns: 1fr;
            }
            .table th,
            .table td {
                display: block;
                width: 100%;
                padding: 8px 0;
            }
            .table th {
                padding-bottom: 2px;
            }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="container">
        <div class="topbar">
            <div class="title">
                <h1>My Account</h1>
                <p>Your front customer portal for onboarding, subscription status, and system access.</p>
            </div>

            <div class="actions">
                <a href="{{ route('automotive.get-started') }}" class="btn btn-light">View Plans & Subscribe</a>

                @if($allowSystemAccess && !empty($systemUrl))
                    <a href="{{ $systemUrl }}" target="_blank" class="btn btn-primary">Open My Trial System</a>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="notice notice-info">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="notice notice-danger">
                <ul style="margin: 0; padding-left: 20px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($status === 'trialing')
            <div class="notice notice-info">
                Your account is currently on a free trial.
                @if(!is_null($trialDaysRemaining))
                    You have <strong>{{ max((int) $trialDaysRemaining, 0) }}</strong> day(s) remaining before the trial ends.
                @endif
            </div>
        @elseif($status === 'past_due' || $status === 'suspended' || $status === 'expired')
            <div class="notice notice-warning">
                Your current subscription status is <strong>{{ strtoupper(str_replace('_', ' ', $status)) }}</strong>.
                Please review your plan and billing before opening the system workspace.
            </div>
        @elseif(empty($subscription))
            <div class="notice notice-info">
                Your account is ready. Choose how you want to continue: start a free trial or subscribe to a paid plan.
            </div>
        @endif

        <div class="stats">
            <div class="stat">
                <div class="stat-label">Current Plan</div>
                <div class="stat-value">
                    {{ $plan->name ?? $plan->slug ?? 'No Plan Yet' }}
                </div>
            </div>

            <div class="stat">
                <div class="stat-label">Current Status</div>
                <div class="stat-value">
                    @php
                        $badgeClass = match ($status) {
                            'active' => 'badge-success',
                            'trialing' => 'badge-info',
                            'past_due' => 'badge-warning',
                            'suspended', 'expired', 'cancelled' => 'badge-danger',
                            default => 'badge-muted',
                        };
                    @endphp
                    <span class="badge {{ $badgeClass }}">
                        {{ $status ? strtoupper(str_replace('_', ' ', $status)) : 'NOT STARTED' }}
                    </span>
                </div>
            </div>

            <div class="stat">
                <div class="stat-label">Reserved Subdomain</div>
                <div class="stat-value" style="font-size: 15px;">
                    {{ $profile->subdomain ?? '-' }}
                </div>
            </div>

            <div class="stat">
                <div class="stat-label">Primary Domain</div>
                <div class="stat-value" style="font-size: 15px;">
                    {{ $primaryDomainValue ?: '-' }}
                </div>
            </div>
        </div>

        <div class="grid">
            <div class="col-8">
                <div class="card">
                    <h3>Account Overview</h3>

                    <table class="table">
                        <tbody>
                        <tr>
                            <th>Full Name</th>
                            <td>{{ $user->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td>{{ $user->email ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Company Name</th>
                            <td>{{ $profile->company_name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Reserved Subdomain</th>
                            <td>{{ $profile->subdomain ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Current Plan</th>
                            <td>{{ $plan->name ?? $plan->slug ?? 'No Plan Yet' }}</td>
                        </tr>
                        <tr>
                            <th>Current Status</th>
                            <td>{{ $status ? strtoupper(str_replace('_', ' ', $status)) : 'NOT STARTED' }}</td>
                        </tr>
                        <tr>
                            <th>Billing Period</th>
                            <td>{{ !empty($subscription->billing_period) ? strtoupper((string) $subscription->billing_period) : '-' }}</td>
                        </tr>
                        <tr>
                            <th>Trial Ends At</th>
                            <td>{{ $trialEndsAt ? $trialEndsAt->format('Y-m-d H:i:s') : '-' }}</td>
                        </tr>
                        <tr>
                            <th>Days Remaining</th>
                            <td>{{ !is_null($trialDaysRemaining) ? max((int) $trialDaysRemaining, 0) : '-' }}</td>
                        </tr>
                        <tr>
                            <th>Gateway</th>
                            <td>{{ $subscription->gateway ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>System Access</th>
                            <td>{{ $allowSystemAccess ? 'Available' : 'Not Available Yet' }}</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-4">
                <div class="card" style="margin-bottom: 24px;">
                    <h3>Next Step</h3>

                    <div class="stack">
                        @if(empty($subscription) && $freeTrialEnabled)
                            <form method="POST" action="{{ route('automotive.portal.start-trial') }}" class="full-button-form">
                                @csrf
                                <button type="submit" class="btn btn-success">Start Free Trial</button>
                            </form>
                        @endif

                        <a href="{{ route('automotive.get-started') }}" class="btn btn-light">View Paid Plans</a>

                        @if($allowSystemAccess && !empty($systemUrl))
                            <a href="{{ $systemUrl }}" target="_blank" class="btn btn-primary">Go to My System</a>
                            <a href="{{ route('automotive.get-started') }}" class="btn btn-light">Upgrade to Paid Plan</a>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <h3>Domains</h3>

                    @if($domains->count() > 0)
                        <div class="domains">
                            @foreach($domains as $domain)
                                <div class="domain-item">
                                    <div><strong>{{ $domain['domain'] }}</strong></div>
                                    <div class="muted" style="margin: 8px 0 0;">
                                        <a href="{{ $domain['url'] }}" target="_blank">Open Domain</a>
                                        @if($allowSystemAccess)
                                            ·
                                            <a href="{{ $domain['admin_login_url'] }}" target="_blank">Open Admin Login</a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="muted">Your domain will appear here after trial or paid activation.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
