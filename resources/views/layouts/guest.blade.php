<!DOCTYPE html>
<html lang="es" data-theme="dark" data-bs-theme="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'DeFinance')</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* ---- Login split-screen (self-contained; brand tokens are theme-independent) ---- */
        body.auth {
            margin: 0;
            background: #0d141f;
        }

        .auth-split {
            display: grid;
            grid-template-columns: 1.05fr .95fr;
            min-height: 100vh;
        }

        /* left — brand panel */
        .auth-brand {
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 2.6rem 2.8rem;
            color: #fff;
            background:
                radial-gradient(85% 70% at 78% 18%, rgba(165, 204, 236, .16), transparent 55%),
                linear-gradient(150deg, #253561 0%, #3b3f78 48%, #6e4480 100%);
        }

        /* subtle grid texture */
        .auth-brand::before {
            content: "";
            position: absolute;
            inset: 0;
            opacity: .5;
            pointer-events: none;
            background-image:
                linear-gradient(rgba(255, 255, 255, .06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, .06) 1px, transparent 1px);
            background-size: 44px 44px;
            mask-image: radial-gradient(120% 90% at 20% 10%, #000 30%, transparent 75%);
        }

        .auth-brand>* {
            position: relative;
        }

        .auth-brand__logo {
            width: 300px;
        }

        .auth-brand__hero {
            max-width: 24ch;
        }

        .auth-brand__hero h1 {
            font-size: clamp(1.7rem, 2.6vw, 2.35rem);
            line-height: 1.12;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin: 0 0 .9rem;
        }

        .auth-brand__hero p {
            font-size: 1.02rem;
            line-height: 1.5;
            color: rgba(255, 255, 255, .82);
            margin: 0;
        }

        .auth-brand__foot {
            display: flex;
            gap: 1.4rem;
            font-family: var(--font-mono);
            font-size: .76rem;
            color: rgba(255, 255, 255, .6);
        }

        /* right — form panel (always dark, minimal) */
        .auth-form-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.5rem;
            background: #0d141f;
        }

        .auth-form {
            width: min(360px, 100%);
        }

        .auth-form__logo {
            height: 30px;
            margin-bottom: 1.8rem;
            display: none;
        }

        .auth-form h1 {
            font-size: 1.4rem;
            font-weight: 600;
            letter-spacing: -0.01em;
            color: #eef3f9;
            margin: 0 0 .3rem;
        }

        .auth-form .sub {
            color: #93a4b7;
            font-size: .92rem;
            margin: 0 0 1.8rem;
        }

        .auth-form .form-label {
            color: #93a4b7;
            font-size: .85rem;
            font-weight: 500;
            margin-bottom: .35rem;
            display: block;
        }

        .auth-form .form-control {
            background: #131c2a;
            color: #eef3f9;
            border: 1px solid #243244;
            border-radius: var(--radius);
            padding: .7rem .85rem;
            width: 100%;
            font-size: .95rem;
        }

        .auth-form .form-control::placeholder {
            color: #5f7187;
        }

        .auth-form .form-control:focus {
            border-color: var(--brand-blue-300);
            outline: none;
            box-shadow: 0 0 0 3px rgba(165, 204, 236, .18);
            background: #16202f;
        }

        .auth-form .field+.field {
            margin-top: 1.1rem;
        }

        .auth-form .btn-primary {
            width: 100%;
            justify-content: center;
            margin-top: 1.6rem;
            background: var(--brand-blue-700);
            color: #fff;
            border: none;
            padding: .72rem 1rem;
            font-size: .95rem;
            border-radius: var(--radius);
        }

        .auth-form .btn-primary:hover {
            background: #4a7bc0;
        }

        .auth-form .note {
            margin-top: 1.4rem;
            font-size: .8rem;
            color: #5f7187;
            text-align: center;
        }

        .auth-form .forgot {
            display: inline-block;
            margin-top: .9rem;
            font-size: .84rem;
            color: var(--brand-blue-300);
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
        }

        @media (max-width: 860px) {
            .auth-split {
                grid-template-columns: 1fr;
            }

            .auth-brand {
                display: none;
            }

            .auth-form__logo {
                display: block;
            }
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
            @yield('content')
        </section>
    </div>
    @stack('scripts')
</body>

</html>