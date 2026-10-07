@php
    $nav = [
        ['section' => null, 'items' => [
            ['route' => 'dashboard', 'label' => 'Resumen', 'icon' => 'grid'],
        ]],
        ['section' => 'Contabilidad', 'items' => [
            ['route' => 'accounts', 'label' => 'Catálogo de cuentas', 'icon' => 'list'],
            ['route' => 'entries', 'label' => 'Pólizas', 'icon' => 'book'],
            ['route' => 'import', 'label' => 'Importar pólizas', 'icon' => 'upload'],
            ['route' => 'cash-count', 'label' => 'Arqueo de caja', 'icon' => 'wallet'],
        ]],
        ['section' => 'Reportes', 'items' => [
            ['route' => 'trial-balance', 'label' => 'Balanza de comprobación', 'icon' => 'scale'],
            ['route' => 'income-statement', 'label' => 'Estado de resultados', 'icon' => 'trend'],
            ['route' => 'balance-sheet', 'label' => 'Balance general', 'icon' => 'layers'],
            ['route' => 'cash-flow', 'label' => 'Flujo de efectivo', 'icon' => 'cash'],
            ['route' => 'managed-cash-flow', 'label' => 'Flujo ajustado', 'icon' => 'sliders'],
            ['route' => 'budget', 'label' => 'Presupuesto', 'icon' => 'target'],
            ['route' => 'budget-monthly', 'label' => 'Presupuesto mensual', 'icon' => 'calendar'],
            ['route' => 'iva', 'label' => 'Declaración de IVA', 'icon' => 'percent'],
            ['route' => 'averages', 'label' => 'Promedios', 'icon' => 'average'],
        ]],
        ['section' => 'Cuenta', 'items' => [
            ['route' => 'profile', 'label' => 'Perfil', 'icon' => 'user'],
        ]],
    ];

    $icons = [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'list' => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="3.5" cy="6" r="1"/><circle cx="3.5" cy="12" r="1"/><circle cx="3.5" cy="18" r="1"/>',
        'book' => '<path d="M4 4h12a2 2 0 0 1 2 2v14H6a2 2 0 0 1-2-2V4z"/><line x1="8" y1="8" x2="14" y2="8"/>',
        'upload' => '<path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/><polyline points="8 8 12 4 16 8"/><line x1="12" y1="4" x2="12" y2="16"/>',
        'wallet' => '<rect x="2" y="6" width="20" height="14" rx="2"/><path d="M2 10h20"/><circle cx="17" cy="14" r="1.4"/>',
        'scale' => '<line x1="12" y1="3" x2="12" y2="21"/><path d="M5 7h14"/><path d="M5 7l-2.5 6a3 3 0 0 0 5 0L5 7z"/><path d="M19 7l-2.5 6a3 3 0 0 0 5 0L19 7z"/>',
        'trend' => '<polyline points="3 17 9 11 13 15 21 7"/><polyline points="15 7 21 7 21 13"/>',
        'layers' => '<polygon points="12 3 21 8 12 13 3 8 12 3"/><polyline points="3 13 12 18 21 13"/>',
        'cash' => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/>',
        'sliders' => '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
        'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/>',
        'percent' => '<line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
        'average' => '<line x1="4" y1="7" x2="15" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="10" y2="17"/>',
        'user' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/>',
    ];
@endphp

<aside class="sidebar">
    <div class="brand">
        <img src="{{ asset('images/mark.png') }}" alt="" class="brand-logo"> DeFinance
    </div>
    <nav class="nav">
        @foreach ($nav as $group)
            @if ($group['section'])
                <div class="nav-section">{{ $group['section'] }}</div>
            @endif
            @foreach ($group['items'] as $item)
                <a href="{{ route($item['route']) }}"
                   class="nav-link {{ request()->routeIs($item['route']) ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        {!! $icons[$item['icon']] !!}
                    </svg>
                    {{ $item['label'] }}
                </a>
            @endforeach
        @endforeach
    </nav>
</aside>
