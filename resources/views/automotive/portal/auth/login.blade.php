@extends('automotive.portal.layouts.auth')

@section('title', 'Customer Portal Login - Automotive SaaS')

@section('auth-styles')
    .card {
        max-width: 460px;
    }
    .remember {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 18px;
        color: #374151;
        font-size: 14px;
    }
    .footer-link {
        margin-top: 18px;
        text-align: center;
        color: #6b7280;
        font-size: 14px;
    }
    .footer-link a {
        color: #1d4ed8;
    }
@endsection

@section('content')
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
@endsection
