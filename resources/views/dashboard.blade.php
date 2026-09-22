@extends('layouts.app')

@section('title', 'Panel')

@section('content')
<div class="page-heading">
    <div><span class="eyebrow">Panel de trabajo</span><h1>Bienvenido, {{ auth()->user()->usu_area }}</h1><p>Gestiona las convocatorias y propuestas de CCyF desde este espacio.</p></div>
</div>
<div class="dashboard-grid">
    @php($ccyfMenu = app(\App\Services\LegacyMenu::class))
    @if ($ccyfMenu->allows(auth()->user(), 'NuevoOficio') || $ccyfMenu->allows(auth()->user(), 'Categorias_widi'))
        <article class="feature-card"><span class="feature-icon">01</span><h2>Nuevo oficio</h2><p>Captura precios de acuerdo con los productos vigentes en cada convocatoria.</p><a class="text-link" href="{{ route('oficios.index') }}">Ver convocatorias <span aria-hidden="true">→</span></a></article>
    @endif
    @if ($ccyfMenu->allows(auth()->user(), 'Categorias_widi'))
        <article class="feature-card"><span class="feature-icon">02</span><h2>Productos y precios</h2><p>Organiza los alimentos o servicios de fotocopiado que se solicitarán en cada convocatoria.</p><a class="text-link" href="{{ route('catalogos.index') }}">Administrar catálogos <span aria-hidden="true">→</span></a></article>
    @endif
</div>
@endsection
