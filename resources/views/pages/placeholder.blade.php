@extends('layouts.app')

@section('title', ($title ?? 'Sección') . ' · DeFinance')
@section('heading', $title ?? 'Sección')

@section('content')
    <div class="page-head">
        <h1>{{ $title ?? 'Sección' }}</h1>
        <p>Esta sección se construye en la Fase 7.</p>
    </div>

    <div class="card card-pad d-flex align-items-center gap-3" style="color:var(--c-text-muted);">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="9"/><path d="M12 8v4l3 2"/>
        </svg>
        Próximamente: la interfaz de esta sección conectada a la API.
    </div>
@endsection
