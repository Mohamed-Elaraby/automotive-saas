<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Get Started - Automotive SaaS</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            color: #111827;
        }
        .wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        .container {
            width: 100%;
            max-width: 1100px;
        }
        .hero {
            text-align: center;
            margin-bottom: 32px;
        }
        .hero h1 {
            margin: 0 0 12px;
            font-size: 36px;
        }
        .hero p {
            margin: 0;
            color: #6b7280;
            font-size: 16px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
        }
        .card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, .08);
            padding: 28px;
        }
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 14px;
        }
        .badge-primary {
            background: #dbeafe;
            color: #1d4ed8;
        }
        .badge-success {
            background: #dcfce7;
            color: #166534;
        }
        .title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .desc {
            color: #6b7280;
            line-height: 1.7;
            margin-bottom: 20px;
        }
        .features {
            margin: 0 0 24px;
            padding-left: 20px;
            color: #374151;
            line-height: 1.8;
        }
        .actions a {
            display: inline-block;
            text-decoration: none;
            padding: 14px 18px;
            border-radius: 12px;
            font-weight: bold;
        }
        .btn-primary {
            background: #1d4ed8;
            color: #fff;
        }
        .btn-secondary {
            background: #111827;
            color: #fff;
        }
        .foot-links {
            text-align: center;
            margin-top: 24px;
            color: #6b7280;
        }
        .foot-links a {
            color: #1d4ed8;
            text-decoration: none;
            margin: 0 8px;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="container">
        <div class="hero">
            <h1>Choose How You Want to Get Started</h1>
            <p>Start with a free trial or go straight to paid plans, depending on how ready you are.</p>
        </div>

        <div class="grid">
            @if($freeTrialEnabled)
                <div class="card">
                    <span class="badge badge-success">Free Trial</span>
                    <div class="title">Start Free Trial</div>
                    <div class="desc">
                        Create your account, reserve your coupon if needed, and start from a trial experience before moving to a paid plan.
                    </div>
                    <ul class="features">
                        <li>14-day trial onboarding</li>
                        <li>Trial coupon reservation supported</li>
                        <li>Upgrade later from your customer profile</li>
                    </ul>
                    <div class="actions">
                        <a href="{{ route('automotive.register') }}" class="btn-primary">Start Free Trial</a>
                    </div>
                </div>
            @endif

            <div class="card">
                <span class="badge badge-primary">Paid Plans</span>
                <div class="title">View Plans & Subscribe</div>
                <div class="desc">
                    Skip the trial and choose a paid plan directly. This will become your main paid onboarding entry path.
                </div>
                <ul class="features">
                    <li>Choose the plan that fits your business</li>
                    <li>Use coupons in the paid flow</li>
                    <li>Access your customer portal after subscription</li>
                </ul>
                <div class="actions">
                    <a href="#" class="btn-secondary">View Paid Plans</a>
                </div>
            </div>
        </div>

        <div class="foot-links">
            <a href="{{ route('login') }}">Tenant Login</a>
        </div>
    </div>
</div>
</body>
</html>
