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
    <div class="app">
        @include('partials.sidebar')
        <div class="scrim"></div>
        <main class="main">
            @include('partials.topbar')
            <div class="content">
                @yield('content')
            </div>
        </main>
    </div>

    <script type="module">
        document.getElementById('logoutBtn')?.addEventListener('click', async () => {
            try { await DF.auth.logout(); } catch (e) {}
            window.location.href = '{{ route('login') }}';
        });
    </script>
    @stack('scripts')
</body>
</html>
