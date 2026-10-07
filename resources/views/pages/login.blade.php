@extends('layouts.guest')

@section('title', 'Entrar · DeFinance')

@section('content')
    <div class="auth-form">
        <img src="{{ asset('images/logo.png') }}" alt="DeFinance" class="auth-form__logo">

        <h1>Iniciar sesión</h1>
        <p class="sub">Accede a tu contabilidad para continuar.</p>

        <form id="loginForm" autocomplete="on">
            <div class="field">
                <label class="form-label" for="email">Correo</label>
                <input class="form-control" type="email" id="email" name="email" placeholder="correo@ejemplo.com" required>
            </div>
            <div class="field">
                <label class="form-label" for="password">Contraseña</label>
                <input class="form-control" type="password" id="password" name="password" placeholder="••••••••" required>
            </div>
            <button class="btn btn-primary" type="submit" data-loading-text="Entrando…">Entrar</button>
        </form>

        <div style="text-align:center">
            <button type="button" class="forgot" id="forgotBtn">¿Olvidaste tu contraseña?</button>
        </div>

        <p class="note">Al continuar aceptas el manejo seguro de tus datos fiscales.</p>
    </div>

    @push('scripts')
    <script type="module">
        const form = document.getElementById('loginForm');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            const { email, password } = DF.forms.serialize(form);
            await DF.loading.withLoading(btn, async () => {
                try {
                    await DF.auth.signInWithSession(email, password);
                    window.location.href = '{{ route('dashboard') }}';
                } catch (err) {
                    DF.notify.error(err.message || 'No se pudo iniciar sesión.');
                }
            });
        });

        document.getElementById('forgotBtn').addEventListener('click', async () => {
            const email = document.getElementById('email').value.trim();
            if (!email) {
                DF.notify.error('Escribe tu correo y te enviaremos un enlace para restablecerla.');
                return;
            }
            try {
                await DF.auth.resetPassword(email);
                DF.notify.success('Te enviamos un enlace para restablecer tu contraseña.');
            } catch (err) {
                DF.notify.error(err.message || 'No se pudo enviar el correo de recuperación.');
            }
        });
    </script>
    @endpush
@endsection
