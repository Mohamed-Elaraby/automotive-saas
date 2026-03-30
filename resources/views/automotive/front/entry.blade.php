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
            <h1>Create Your Account First</h1>
            <p>After registration, you will enter your customer portal and choose either free trial or a paid plan.</p>
        </div>

        <div class="grid">
            @if($freeTrialEnabled)
                <div class="card">
                    <span class="badge badge-success">Free Trial</span>
                    <div class="title">Register Then Start Trial</div>
                    <div class="desc">
                        Create your account first, reserve your preferred subdomain, then start your free trial from the customer portal.
                    </div>
                    <ul class="features">
                        <li>Account registration first</li>
                        <li>14-day trial starts later from portal</li>
                        <li>Upgrade to paid when ready</li>
                    </ul>
                    <div class="actions">
                        <a href="{{ route('automotive.register') }}" class="btn-primary">Create Account</a>
                    </div>
                </div>
            @endif

            <div class="card">
                <span class="badge badge-primary">Paid Plans</span>
                <div class="title">Register Then View Paid Plans</div>
                <div class="desc">
                    Create your account first, then open the customer portal to choose a paid plan and continue to checkout.
                </div>
                <ul class="features">
                    <li>Account registration first</li>
                    <li>Paid plan selection inside portal</li>
                    <li>Stripe checkout starts from portal</li>
                </ul>
                <div class="actions">
                    <a href="{{ route('automotive.register') }}" class="btn-secondary">Create Account To Continue</a>
                </div>
            </div>
        </div>

        <div class="foot-links">
            <a href="{{ route('automotive.login') }}">Customer Portal Login</a>
        </div>
    </div>
</div>
</body>
</html>
