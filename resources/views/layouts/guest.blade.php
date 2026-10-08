<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'DeFinance')</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <script>
        // Establish the theme before paint (no flash); app.js wires the toggle.
        (function () {
            try {
                var t = localStorage.getItem('df-theme') ||
                    (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.setAttribute('data-theme', t);
                document.documentElement.setAttribute('data-bs-theme', t);
            } catch (e) {}
        })();
    </script>
    {{-- Critical paint: themed background before the bundle loads (no flash). --}}
    <style>
        html { background: #f7f7f7; color: #17202e; font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }
        html[data-theme="dark"] { background: #0e1620; color: #e8eef5; }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* ---- Login split-screen — theme-aware (tokens), brand panel fixed gradient ---- */
        body.auth { margin: 0; background: var(--c-bg); }
        .auth-split { display: grid; grid-template-columns: 1.05fr .95fr; min-height: 100vh; }

        /* left — brand panel (fixed gradient; white text in both themes) */
        .auth-brand {
            position: relative; overflow: hidden;
            display: flex; flex-direction: column; justify-content: space-between;
            padding: 2.6rem 2.8rem;
            color: #fff;
            background:
                radial-gradient(85% 70% at 78% 18%, rgba(165,204,236,.16), transparent 55%),
                linear-gradient(150deg, #253561 0%, #3b3f78 48%, #6e4480 100%);
        }
        .auth-brand::before {
            content: ""; position: absolute; inset: 0; opacity: .5; pointer-events: none;
            background-image:
                linear-gradient(rgba(255,255,255,.06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.06) 1px, transparent 1px);
            background-size: 44px 44px;
            mask-image: radial-gradient(120% 90% at 20% 10%, #000 30%, transparent 75%);
        }
        .auth-brand > * { position: relative; }
        .auth-brand__logo { height: 34px; width: auto; }
        .auth-brand__hero { max-width: 24ch; }
        .auth-brand__hero h1 {
            font-size: clamp(1.7rem, 2.6vw, 2.35rem); line-height: 1.12; font-weight: 700;
            letter-spacing: -0.02em; margin: 0 0 .9rem;
        }
        .auth-brand__hero p { font-size: 1.02rem; line-height: 1.5; color: rgba(255,255,255,.82); margin: 0; }
        .auth-brand__foot { display: flex; gap: 1.4rem; font-family: var(--font-mono); font-size: .76rem; color: rgba(255,255,255,.6); }

        /* right — form panel (follows the theme) */
        .auth-form-panel {
            position: relative;
            display: flex; align-items: center; justify-content: center;
            padding: 2rem 1.5rem; background: var(--c-bg);
        }
        .auth-theme-toggle { position: absolute; top: 1.1rem; right: 1.1rem; }
        .auth-form { width: min(360px, 100%); }
        .auth-form__logo { height: 30px; margin-bottom: 1.8rem; display: none; }
        .auth-form h1 { font-size: 1.4rem; font-weight: 600; letter-spacing: -0.01em; color: var(--c-text); margin: 0 0 .3rem; }
        .auth-form .sub { color: var(--c-text-muted); font-size: .92rem; margin: 0 0 1.8rem; }

        .auth-form .form-label { color: var(--c-text-muted); font-size: .85rem; font-weight: 500; margin-bottom: .35rem; display: block; }
        .auth-form .form-control {
            background: var(--c-surface); color: var(--c-text);
            border: 1px solid var(--c-border-strong); border-radius: var(--radius);
            padding: .7rem .85rem; width: 100%; font-size: .95rem;
        }
        .auth-form .form-control::placeholder { color: var(--c-text-subtle); }
        .auth-form .form-control:focus {
            border-color: var(--c-primary); outline: none;
            box-shadow: 0 0 0 3px var(--c-primary-soft); background: var(--c-surface);
        }
        /* keep autofilled text legible in both themes (Chrome forces its own colours otherwise) */
        .auth-form .form-control:-webkit-autofill,
        .auth-form .form-control:-webkit-autofill:hover,
        .auth-form .form-control:-webkit-autofill:focus {
            -webkit-text-fill-color: var(--c-text);
            -webkit-box-shadow: 0 0 0 1000px var(--c-surface) inset;
            caret-color: var(--c-text);
            transition: background-color 9999s ease-in-out 0s;
        }
        .auth-form .field + .field { margin-top: 1.1rem; }
        .auth-form .btn-primary {
            width: 100%; justify-content: center; margin-top: 1.6rem;
            background: var(--c-primary); color: var(--c-on-primary); border: none;
            padding: .72rem 1rem; font-size: .95rem; border-radius: var(--radius);
        }
        .auth-form .btn-primary:hover { background: var(--c-primary-hover); }
        .auth-form .note { margin-top: 1.4rem; font-size: .8rem; color: var(--c-text-subtle); text-align: center; }
        .auth-form .forgot { display: inline-block; margin-top: .9rem; font-size: .84rem; color: var(--c-primary); background: none; border: none; cursor: pointer; padding: 0; }

        @media (max-width: 860px) {
            .auth-split { grid-template-columns: 1fr; }
            .auth-brand { display: none; }
            .auth-form__logo { display: block; }
        }
    </style>
</head>
<body class="auth">
    <div class="auth-split">
        <section class="auth-brand">
            <img src="{{ asset('images/logo.png') }}" alt="DeFinance" class="auth-brand__logo">
            <div class="auth-brand__hero">
                <h1>Partida doble, siempre cuadrada.</h1>
                <p>Pólizas, reportes y flujo de efectivo con saldos calculados al instante.</p>
            </div>
            <div class="auth-brand__foot">
                <span>Partida doble</span><span>Saldos calculados</span><span>MXN</span>
            </div>
        </section>

        <section class="auth-form-panel">
            <button type="button" class="icon-btn auth-theme-toggle" data-theme-toggle aria-label="Cambiar tema" title="Cambiar tema">
                <i class="fa-solid fa-moon" aria-hidden="true"></i>
            </button>
            @yield('content')
        </section>
    </div>
    @stack('scripts')
</body>
</html>
