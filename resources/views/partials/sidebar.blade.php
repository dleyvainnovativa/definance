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
            ['route' => 'label-report', 'label' => 'Reporte por etiqueta', 'icon' => 'tag'],
        ]],
        ['section' => 'Cuenta', 'items' => [
            ['route' => 'labels', 'label' => 'Etiquetas', 'icon' => 'tag'],
            ['route' => 'profile', 'label' => 'Perfil', 'icon' => 'user'],
        ]],
    ];

    $icons = [
        'grid' => 'fa-table-cells-large',
        'list' => 'fa-list',
        'book' => 'fa-book',
        'upload' => 'fa-file-import',
        'wallet' => 'fa-wallet',
        'scale' => 'fa-scale-balanced',
        'trend' => 'fa-chart-line',
        'layers' => 'fa-layer-group',
        'cash' => 'fa-money-bill-transfer',
        'sliders' => 'fa-sliders',
        'target' => 'fa-bullseye',
        'calendar' => 'fa-calendar-days',
        'percent' => 'fa-percent',
        'average' => 'fa-chart-simple',
        'user' => 'fa-user',
        'tag' => 'fa-tag',
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
                    <i class="fa-solid {{ $icons[$item['icon']] }}" aria-hidden="true"></i>
                    {{ $item['label'] }}
                </a>
            @endforeach
        @endforeach
    </nav>
</aside>
