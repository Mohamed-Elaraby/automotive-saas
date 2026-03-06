{{--@extends('automotive.layouts.adminLayout.mainlayout')--}}

{{--@section('title','Dashboard')--}}

{{--@section('content')--}}
{{--    <!-- ========================--}}
{{--        Start Page Content--}}
{{--    ========================= -->--}}

{{--    <div class="page-wrapper">--}}

{{--        <div class="content content-two">--}}

{{--            <h1>Welcome Mohamed 👋</h1>--}}

{{--        </div>--}}
{{--    </div>--}}
{{--@endsection--}}

    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Automotive SaaS</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; margin: 0; padding: 30px; }
        .box {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 14px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,.06);
        }
        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        button {
            border: 0;
            background: #dc2626;
            color: #fff;
            padding: 10px 16px;
            border-radius: 8px;
            cursor: pointer;
        }
    </style>
</head>
<body>
<div class="box">
    <div class="top">
        <h1>Automotive Admin Dashboard</h1>

        <form method="POST" action="{{ route('automotive.admin.logout') }}">
            @csrf
            <button type="submit">Logout</button>
        </form>
    </div>

    <p>Welcome, {{ auth('automotive_admin')->user()?->name }}</p>
    <p>You are logged in successfully.</p>
</div>
</body>
</html>
