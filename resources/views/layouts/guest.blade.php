<!DOCTYPE html>
<html lang="es" data-theme="light" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'DeFinance')</title>
    <script>
        (function () {
            try {
                var t = localStorage.getItem('df-theme') ||
                    (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.setAttribute('data-theme', t);
                document.documentElement.setAttribute('data-bs-theme', t);
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="d-flex flex-column min-vh-100 align-items-center justify-content-center p-3">
        <div class="d-flex align-items-center gap-2 mb-4" style="font-weight:700; letter-spacing:-0.02em;">
            <span class="mark" style="width:30px;height:30px;border-radius:8px;display:grid;place-items:center;color:#fff;background:linear-gradient(135deg,var(--c-primary),var(--brand-magenta));">D</span>
            DeFinance
        </div>
        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>
