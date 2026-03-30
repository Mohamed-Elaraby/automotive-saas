<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Automotive SaaS')</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            color: #111827;
        }
        a {
            text-decoration: none;
        }
        @yield('page-styles')
    </style>
</head>
<body>
@yield('content')
</body>
</html>
