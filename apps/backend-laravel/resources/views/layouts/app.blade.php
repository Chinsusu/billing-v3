<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Billing v3' }}</title>
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; background: #f7f7f8; color: #171717; }
        header { background: #111827; color: #fff; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; }
        header a, header button { color: #fff; margin-left: 14px; }
        main { max-width: 1080px; margin: 32px auto; padding: 0 20px; }
        .panel { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 18px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        label { display: block; font-weight: 600; margin-top: 12px; }
        input, select, textarea { width: 100%; box-sizing: border-box; padding: 10px; margin-top: 6px; border: 1px solid #d1d5db; border-radius: 6px; }
        button, .button { display: inline-block; background: #2563eb; color: #fff; border: 0; border-radius: 6px; padding: 10px 14px; text-decoration: none; cursor: pointer; }
        .button.secondary { background: #4b5563; }
        .button.danger { background: #dc2626; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 10px; text-align: left; }
        .error { color: #b91c1c; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
<header>
    <div><strong>Billing v3</strong></div>
    <nav>
        <a href="/products">Products</a>
        @auth
            <a href="/dashboard">Dashboard</a>
            <a href="/wallet">Wallet</a>
            <a href="/services">Services</a>
            @can('admin.access')<a href="/admin">Admin</a>@endcan
            <form method="POST" action="/logout" style="display:inline">@csrf<button type="submit" style="background:transparent;border:0;padding:0;text-decoration:underline">Logout</button></form>
        @else
            <a href="/login">Login</a>
            <a href="/register">Register</a>
        @endauth
    </nav>
</header>
<main>
    @if (session('status'))<div class="panel">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="panel error"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
</body>
</html>
