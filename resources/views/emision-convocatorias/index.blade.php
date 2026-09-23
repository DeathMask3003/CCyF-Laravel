@extends('layouts.app')

@section('title', 'Emisión de convocatorias')

@push('head')<link rel="stylesheet" href="{{ asset('css/emision-convocatorias.css') }}?v=2">@endpush

@section('content')
<div class="emision-shell">
    <section class="emision-hero">
        <div><span class="emision-kicker">CCyF · Documentos oficiales</span><h1>Emisión de convocatorias</h1><p>Prepara las bases, revisa los planteles y genera el PDF de cada convocatoria de cafetería o fotocopiado.</p></div>
        <a class="emision-primary" href="{{ route('emision.create') }}">+ Nueva convocatoria</a>
    </section>
    <div class="emision-stats" aria-label="Resumen del historial">
        <div><strong>{{ $totals->total ?? 0 }}</strong><span>Documentos</span></div>
        <div><strong>{{ $totals->cafeteria ?? 0 }}</strong><span>Cafetería</span></div>
        <div><strong>{{ $totals->fotocopiado ?? 0 }}</strong><span>Fotocopiado</span></div>
    </div>
    <section class="emision-panel">
        <div class="emision-panel-head"><div><span class="emision-kicker">Archivo</span><h2>Historial de convocatorias</h2><p>Consulta y actualiza los documentos que ya preparaste.</p></div></div>
        <div class="emision-tabs" role="navigation" aria-label="Filtrar por servicio">
            <a @class(['active' => ! $service]) href="{{ route('emision.index', ['q' => $search]) }}">Todas <span>{{ $totals->total ?? 0 }}</span></a>
            <a @class(['active' => $service === 'cafeteria']) href="{{ route('emision.index', ['servicio' => 'cafeteria', 'q' => $search]) }}">Cafetería <span>{{ $totals->cafeteria ?? 0 }}</span></a>
            <a @class(['active' => $service === 'fotocopiado']) href="{{ route('emision.index', ['servicio' => 'fotocopiado', 'q' => $search]) }}">Fotocopiado <span>{{ $totals->fotocopiado ?? 0 }}</span></a>
        </div>
        <form class="emision-search" method="get"><input type="hidden" name="servicio" value="{{ $service }}"><label class="sr-only" for="emision-q">Buscar por título o número</label><input id="emision-q" name="q" value="{{ $search }}" placeholder="Buscar título o número de convocatoria"><button type="submit">Buscar</button>@if ($search)<a href="{{ route('emision.index', ['servicio' => $service]) }}">Limpiar</a>@endif</form>
        @if ($documents->isEmpty())
            <div class="emision-empty"><div class="emision-empty-icon">✧</div><h3>{{ $search || $service ? 'No hay resultados con estos filtros' : 'Aún no hay documentos' }}</h3><p>{{ $search || $service ? 'Prueba con otro servicio o término de búsqueda.' : 'Comienza con una convocatoria activa y sus planteles participantes.' }}</p><a class="emision-secondary" href="{{ route('emision.create') }}">Crear documento</a></div>
        @else
            <div class="emision-history">
                @foreach ($documents as $item)
                    <article class="emision-history-item"><div class="emision-history-icon" aria-hidden="true">{{ str_starts_with(mb_strtolower($item->servicio_nombre ?? ''), 'cafeter') ? '☕' : '▤' }}</div><div class="emision-history-main"><div class="emision-history-meta"><span class="emision-badge">{{ $item->servicio_nombre }}</span><span>{{ $item->numero }}</span><span>{{ $item->planteles_total }} {{ $item->planteles_total == 1 ? 'plantel' : 'planteles' }}</span></div><h3>{{ $item->titulo }}</h3><small>Actualizada {{ \Carbon\Carbon::parse($item->updated_at)->format('d/m/Y · H:i') }}</small></div><div class="emision-history-actions"><a href="{{ route('emision.edit', $item->id) }}">Editar</a><a href="{{ route('emision.pdf', $item->id) }}" target="_blank" rel="noopener">Ver PDF</a><a href="{{ route('emision.pdf', ['document' => $item->id, 'descargar' => 1]) }}">Descargar</a></div></article>
                @endforeach
            </div>
            {{ $documents->links() }}
        @endif
    </section>
</div>
@endsection
