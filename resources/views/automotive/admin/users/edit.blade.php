<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; background: #f8fafc; color: #111827; }
        .wrap { max-width: 900px; margin: 0 auto; }
        .card { background:#fff; padding:20px; border-radius:12px; box-shadow:0 4px 18px rgba(0,0,0,.06); }
        input { width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; margin-top:6px; }
        label { display:block; font-weight:600; }
        .top { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
        .btn { display:inline-block; padding:10px 14px; border-radius:8px; text-decoration:none; border:0; cursor:pointer; }
        .btn-primary { background:#2563eb; color:#fff; }
        .btn-secondary { background:#374151; color:#fff; }
        .errors { margin-bottom:16px; padding:12px 14px; background:#fee2e2; color:#991b1b; border-radius:8px; }
        .actions { margin-top:20px; display:flex; gap:10px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <h1>Edit User: {{ $user->name }}</h1>
        <a href="{{ route('automotive.admin.users.index') }}" class="btn btn-secondary">Back</a>
    </div>

    @if ($errors->any())
        <div class="errors">
            <ul style="margin:0; padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <form method="POST" action="{{ route('automotive.admin.users.update', $user) }}">
            @csrf
            @method('PUT')

            @php($isEdit = true)
            @include('automotive.admin.users._form')

            <div class="actions">
                <button type="submit" class="btn btn-primary">Update User</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
