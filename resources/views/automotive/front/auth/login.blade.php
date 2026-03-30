<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Portal Login - Automotive SaaS</title>
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
            max-width: 460px;
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
        .success {
            background: #ecfdf5;
            color: #065f46;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 16px;
        }
        .error {
            background: #fdecec;
            color: #b91c1c;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 16px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
        }
        input[type="email"],
        input[type="password"] {
            width: 100%;
            box-sizing: border-box;
            padding: 12px 14px;
            border: 1px solid #d8dce3;
            border-radius: 10px;
            font-size: 14px;
        }
        .remember {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 18px;
            color: #374151;
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
        .field-error {
            color: #b91c1c;
            font-size: 13px;
            margin-top: 5px;
        }
        .footer-link {
            margin-top: 18px;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
        }
        .footer-link a {
            color: #1d4ed8;
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        <h1>Customer Portal Login</h1>
        <p>Sign in to continue to your Automotive customer portal.</p>

        @if (session('success'))
            <div class="success">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="error">
                <strong>Unable to sign in.</strong>
                <div style="margin-top:8px;">{{ $errors->first() }}</div>
            </div>
        @endif

        <form method="POST" action="{{ route('automotive.login.submit') }}">
            @csrf

            <div class="form-group">
                <label for="email">Business Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
                @error('email') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>
                @error('password') <div class="field-error">{{ $message }}</div> @enderror
            </div>

            <label class="remember" for="remember">
                <input id="remember" type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                <span>Remember me</span>
            </label>

            <button type="submit">Sign In To Portal</button>
        </form>

        <div class="footer-link">
            Need an account?
            <a href="{{ route('automotive.register') }}">Create one here</a>
        </div>
    </div>
</div>
</body>
</html>
