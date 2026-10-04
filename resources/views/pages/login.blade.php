@extends('layouts.guest')

@section('title', 'Entrar · DeFinance')

@section('content')
    <div class="card card-pad" style="width:min(380px,100%);">
        <h1 style="font-size:1.25rem;" class="mb-1">Entrar</h1>
        <p style="color:var(--c-text-muted);" class="mb-4">Accede a tu contabilidad.</p>

        <form id="loginForm" autocomplete="on">
            <div class="mb-3">
                <label class="form-label" for="email">Correo</label>
                <input class="form-control" type="email" id="email" name="email" required>
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">Contraseña</label>
                <input class="form-control" type="password" id="password" name="password" required>
            </div>
            <button class="btn btn-primary w-100 justify-content-center" type="submit" data-loading-text="Entrando…">
                Entrar
            </button>
        </form>
    </div>

    @push('scripts')
    <script type="module">
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.currentTarget.querySelector('button[type="submit"]');
            const { email, password } = DF.forms.serialize(e.currentTarget);
            await DF.loading.withLoading(btn, async () => {
                try {
                    await DF.auth.signInWithSession(email, password);
                    window.location.href = '{{ route('dashboard') }}';
                } catch (err) {
                    DF.notify.error(err.message || 'No se pudo iniciar sesión.');
                }
            });
        });
    </script>
    @endpush
@endsection
