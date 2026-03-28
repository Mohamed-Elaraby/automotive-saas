<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Start Free Trial - Automotive SaaS</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            margin: 0;
            padding: 0;
        }
        .wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px;
        }
        .card {
            width: 100%;
            max-width: 520px;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0,0,0,.08);
            padding: 30px;
        }
        h1 {
            margin: 0 0 10px;
            font-size: 28px;
        }
        p {
            color: #666;
            margin-bottom: 25px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
        }
        input {
            width: 100%;
            box-sizing: border-box;
            padding: 12px 14px;
            border: 1px solid #d8dce3;
            border-radius: 10px;
            font-size: 14px;
        }
        button {
            width: 100%;
            border: 0;
            background: #1d4ed8;
            color: #fff;
            padding: 14px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }
        .error {
            background: #fdecec;
            color: #b91c1c;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 16px;
        }
        .field-error {
            color: #b91c1c;
            font-size: 13px;
            margin-top: 5px;
        }
        .hint {
            font-size: 12px;
            color: #6b7280;
            margin-top: 6px;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        <h1>Start Your 14-Day Free Trial</h1>
        <p>Create your Automotive SaaS account and get your own subdomain instantly.</p>

        @if ($errors->any())
            <div class="error">
                <strong>Please review the form.</strong>
                @if ($errors->has('register'))
                    <div style="margin-top:8px;">{{ $errors->first('register') }}</div>
                @endif
            </div>
        @endif

        <form method="POST" action="{{ route('automotive.register.submit') }}">
            @csrf

            <div class="form-group">
                <label for="name">Full Name</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required>
                @error('name') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="email">Business Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required>
                @error('email') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="company_name">Company Name</label>
                <input id="company_name" type="text" name="company_name" value="{{ old('company_name') }}" required>
                @error('company_name') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="subdomain">Subdomain</label>
                <input id="subdomain" type="text" name="subdomain" value="{{ old('subdomain') }}" required>
                <div class="hint">Example: mido → mido.automotive.seven-scapital.com</div>
                @error('subdomain') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="coupon_code">Coupon Code</label>
                <input id="coupon_code" type="text" name="coupon_code" value="{{ old('coupon_code') }}" placeholder="Optional coupon code">
                <div class="hint">Optional. If valid, it will be reserved on your new trial subscription for future billing rules.</div>
                @error('coupon_code') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>
                @error('password') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="password_confirmation">Confirm Password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required>
            </div>

            <button type="submit">Start Free Trial</button>
        </form>
    </div>
</div>
</body>
</html>
