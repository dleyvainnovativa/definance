@extends('layouts.app')

@section('title', 'Perfil · DeFinance')
@section('heading', 'Perfil')

@section('content')
    <div class="page-head"><div><h1>Perfil</h1><p>Tu información de cuenta.</p></div></div>

    <div class="card card-pad" style="max-width:520px;">
        <form id="profileForm">
            <div class="mb-3"><label class="form-label" for="name">Nombre</label><input class="form-control" id="name" name="name"></div>
            <div class="mb-3"><label class="form-label" for="email">Correo</label><input class="form-control" id="email" name="email" type="email"></div>
            <div class="mb-4"><label class="form-label">Identificador</label><input class="form-control num" id="uid" disabled></div>
            <button class="btn btn-primary" type="submit" data-loading-text="Guardando…">Guardar cambios</button>
        </form>
    </div>

    @push('scripts')
    <script type="module">
        const { http, notify, loading, guard } = DF;

        async function load() {
            if (!await guard.ensureAuth()) return;
            try {
                const p = await http.get('/profile');
                document.getElementById('name').value = p.name ?? '';
                document.getElementById('email').value = p.email ?? '';
                document.getElementById('uid').value = p.firebase_uid ?? '';
            } catch (e) { notify.error(e.message); }
        }

        document.getElementById('profileForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.currentTarget.querySelector('button[type="submit"]');
            await loading.withLoading(btn, async () => {
                try {
                    await http.put('/profile', {
                        name: document.getElementById('name').value,
                        email: document.getElementById('email').value,
                    });
                    notify.success('Perfil actualizado.');
                } catch (e) {
                    const msg = e.body?.errors ? Object.values(e.body.errors)[0][0] : e.message;
                    notify.error(msg);
                }
            });
        });

        load();
    </script>
    @endpush
@endsection
