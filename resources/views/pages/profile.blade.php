@extends('layouts.app')

@section('title', 'Perfil · DeFinance')
@section('heading', 'Perfil')

@section('content')
    <style>
        .device-item { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .7rem .9rem; border: 1px solid var(--c-border); border-radius: var(--radius); margin-bottom: .5rem; }
        .device-item .meta { font-size: .82rem; color: var(--c-text-muted); }
    </style>

    <div class="page-head"><div><h1>Perfil</h1><p>Tu información de cuenta.</p></div></div>

    <div style="max-width:560px;">
        <div class="card card-pad" style="margin-bottom:1rem;">
            <form id="profileForm">
                <div class="mb-3"><label class="form-label" for="name">Nombre</label><input class="form-control" id="name" name="name"></div>
                <div class="mb-3"><label class="form-label" for="email">Correo</label><input class="form-control" id="email" name="email" type="email"></div>
                <div class="mb-4"><label class="form-label">Identificador</label><input class="form-control num" id="uid" disabled></div>
                <button class="btn btn-primary" type="submit" data-loading-text="Guardando…">Guardar cambios</button>
            </form>
        </div>

        <div class="card card-pad" style="margin-bottom:1rem;">
            <h2 style="font-size:1rem;margin-bottom:.4rem;">Seguridad</h2>
            <p style="color:var(--c-text-muted);font-size:.85rem;margin-bottom:.8rem;">
                La contraseña se administra con tu proveedor de acceso. Te enviaremos un correo para cambiarla.
            </p>
            <button class="btn btn-ghost" id="resetBtn" type="button" data-loading-text="Enviando…">Cambiar contraseña</button>
        </div>

        <div class="card card-pad">
            <h2 style="font-size:1rem;margin-bottom:.6rem;">Dispositivos</h2>
            <div id="devices"><div class="empty">Cargando…</div></div>
        </div>
    </div>

    @push('scripts')
    <script type="module">
        const { http, notify, loading, guard, auth, format } = DF;
        let currentEmail = '';

        async function load() {
            if (!await guard.ensureAuth()) return;
            try {
                const p = await http.get('/profile');
                currentEmail = p.email ?? '';
                document.getElementById('name').value = p.name ?? '';
                document.getElementById('email').value = currentEmail;
                document.getElementById('uid').value = p.firebase_uid ?? '';
            } catch (e) { notify.error(e.message); }
            loadDevices();
        }

        async function loadDevices() {
            try {
                const res = await http.get('/devices');
                const el = document.getElementById('devices');
                if (!res.data.length) { el.innerHTML = '<div class="empty">Sin dispositivos registrados.</div>'; return; }
                el.innerHTML = res.data.map(d => `
                    <div class="device-item">
                        <div>
                            <div>${escapeHtml(d.label)}</div>
                            <div class="meta">${d.ip_address ?? ''}${d.last_login_at ? ' · ' + format.date(d.last_login_at) : ''}</div>
                        </div>
                        <button class="btn btn-ghost" data-revoke="${d.id}">Revocar</button>
                    </div>`).join('');
                el.querySelectorAll('[data-revoke]').forEach(b => b.addEventListener('click', () => revoke(b.dataset.revoke)));
            } catch (e) { notify.error(e.message); }
        }

        function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }

        async function revoke(id) {
            if (!window.confirm('¿Revocar este dispositivo?')) return;
            try { await http.del(`/devices/${id}`); notify.success('Dispositivo revocado.'); loadDevices(); }
            catch (e) { notify.error(e.message); }
        }

        document.getElementById('resetBtn').addEventListener('click', async () => {
            const btn = document.getElementById('resetBtn');
            if (!currentEmail) { notify.error('No hay correo en tu cuenta.'); return; }
            await loading.withLoading(btn, async () => {
                try { await auth.resetPassword(currentEmail); notify.success('Te enviamos un correo para cambiar tu contraseña.'); }
                catch (e) { notify.error(e.message || 'No se pudo enviar el correo.'); }
            });
        });

        document.getElementById('profileForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.currentTarget.querySelector('button[type="submit"]');
            await loading.withLoading(btn, async () => {
                try {
                    await http.put('/profile', {
                        name: document.getElementById('name').value,
                        email: document.getElementById('email').value,
                    });
                    currentEmail = document.getElementById('email').value;
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
