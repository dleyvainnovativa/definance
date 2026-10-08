@php
$display = auth()->user()->name ?? auth()->user()->email ?? 'Usuario';
$initial = mb_strtoupper(mb_substr($display, 0, 1));
@endphp

<header class="topbar">
    <div class="d-flex align-items-center" style="gap:.75rem;">
        <button type="button" class="icon-btn sidebar-toggle" data-sidebar-toggle aria-label="Menú">
            <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>
        <span class="title d-none">@yield('heading', 'Resumen')</span>
    </div>

    <div class="topbar-actions">
        <button type="button" class="icon-btn" data-quick-entry aria-label="Nuevo movimiento" title="Nuevo movimiento">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
        </button>
        <button type="button" class="icon-btn" data-bs-toggle="modal" data-bs-target="#calcModal" aria-label="Calculadora" title="Calculadora">
            <i class="fa-solid fa-calculator" aria-hidden="true"></i>
        </button>
        <button type="button" class="icon-btn" data-theme-toggle aria-label="Cambiar tema" title="Cambiar tema">
            <i class="fa-solid fa-moon" aria-hidden="true"></i>
        </button>
        <span class="user-chip">
            <span class="avatar">{{ $initial }}</span>
            <span class="d-none d-sm-inline">{{ $display }}</span>
        </span>
        <button type="button" class="icon-btn" id="logoutBtn" aria-label="Salir" title="Salir">
            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
        </button>
    </div>
</header>