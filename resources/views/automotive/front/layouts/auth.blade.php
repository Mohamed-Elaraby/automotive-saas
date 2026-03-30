@extends('automotive.front.layouts.public')

@section('page-styles')
    body {
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
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, .08);
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
    input,
    input[type="email"],
    input[type="password"] {
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
    .success {
        background: #ecfdf5;
        color: #065f46;
        padding: 12px;
        border-radius: 10px;
        margin-bottom: 16px;
    }
    .field-error {
        color: #b91c1c;
        font-size: 13px;
        margin-top: 5px;
    }
    @yield('auth-styles')
@endsection
