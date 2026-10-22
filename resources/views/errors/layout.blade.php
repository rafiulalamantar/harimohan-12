{{-- Standalone on purpose: no Vite assets, database or auth, so it still renders when those are what failed. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#064e3b">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; background: #f6f7f6; color: #1e293b; font-family: "Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", Roboto, "Hind Siliguri", sans-serif; }
        .card { width: 100%; max-width: 440px; background: #fff; border-radius: 24px; padding: 32px 24px; text-align: center; box-shadow: 0 1px 2px rgb(15 23 42 / .06), 0 12px 32px -12px rgb(15 23 42 / .15); border: 1px solid #e2e8f0; }
        .icon { font-size: 44px; line-height: 1; }
        .code { margin: 16px 0 0; font-size: 13px; font-weight: 700; letter-spacing: .08em; color: #047857; }
        h1 { margin: 4px 0 0; font-size: 22px; line-height: 1.3; color: #0f172a; }
        p { margin: 12px 0 0; font-size: 15px; line-height: 1.6; color: #475569; }
        .actions { margin-top: 24px; display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
        .btn { display: inline-block; border-radius: 999px; padding: 11px 20px; font-size: 14px; font-weight: 600; text-decoration: none; }
        .btn-primary { background: #0f172a; color: #fff; }
        .btn-primary:hover { background: #065f46; }
        .btn-secondary { background: #f1f5f9; color: #334155; }
        .btn-secondary:hover { background: #e2e8f0; }
        .brand { margin-top: 24px; font-size: 12px; color: #94a3b8; }
    </style>
</head>
<body>
<main class="card">
    <div class="icon" aria-hidden="true">@yield('icon')</div>
    <p class="code">ERROR @yield('code')</p>
    <h1>@yield('title')</h1>
    <p>@yield('message')</p>
    <div class="actions">
        @hasSection('retry')
            <a class="btn btn-primary" href="@yield('retry')">Try again</a>
            <a class="btn btn-secondary" href="{{ url('/') }}">Go to homepage</a>
        @else
            <a class="btn btn-primary" href="{{ url('/') }}">Go to homepage</a>
        @endif
    </div>
    <div class="brand">{{ config('app.name') }}</div>
</main>
</body>
</html>
