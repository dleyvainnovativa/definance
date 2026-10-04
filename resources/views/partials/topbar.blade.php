@php
    $display = auth()->user()->name ?? auth()->user()->email ?? 'Usuario';
    $initial = mb_strtoupper(mb_substr($display, 0, 1));
@endphp

<header class="topbar">
    <div class="d-flex align-items-center" style="gap:.75rem;">
        <button type="button" class="icon-btn sidebar-toggle" data-sidebar-toggle aria-label="Menú">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <span class="title">@yield('heading', 'Resumen')</span>
    </div>

    <div class="topbar-actions">
        <button type="button" class="icon-btn" data-theme-toggle aria-label="Cambiar tema" title="Cambiar tema">
            <i class="fa-solid fa-moon" aria-hidden="true"></i>
        </button>
        <span class="user-chip">
            <span class="avatar">{{ $initial }}</span>
            <span class="d-none d-sm-inline">{{ $display }}</span>
        </span>
        <button type="button" class="icon-btn" id="logoutBtn" aria-label="Salir" title="Salir">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </button>
    </div>
</header>
