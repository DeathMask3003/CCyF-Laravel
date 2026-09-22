@extends('layouts.app')

@section('title', 'Convocatorias finalizadas')
@section('content')
<div class="page-heading review-heading"><div><span class="eyebrow">Resultados · CCyF</span><h1>Convocatorias finalizadas</h1><p>Consulta la respuesta y los documentos de propuestas actuales e históricas.</p></div><span class="pill pill-ready">{{ $total }} resultados</span></div>
<div class="info-strip">Aquí puedes consultar las propuestas finalizadas, incluidas las registradas antes de la actualización.</div>
@include('revision.filters', ['action' => route('revision.finished')])
<section class="export-panel panel" aria-label="Exportar resultados">
    <div class="export-current"><div><span class="eyebrow">Exportación</span><h2>Resultados del filtro actual</h2><p>Incluye los {{ $total }} resultados, aunque estén distribuidos en varias páginas.</p></div>
        <div class="export-actions"><a class="outline-button button-link" href="{{ route('revision.export', ['format' => 'xlsx'] + request()->only(['buscar', 'servicio', 'convocatoria'])) }}">Descargar Excel</a><a class="button button-link" href="{{ route('revision.export', ['format' => 'pdf'] + request()->only(['buscar', 'servicio', 'convocatoria'])) }}">Descargar PDF</a></div></div>
    @if ($canReview)
        <div class="export-services"><p>Listado completo por servicio, como en la tabla anterior</p><div>
            @foreach ($services->filter(fn ($service) => in_array((int) $service->legacy_trami_id, [3, 4], true)) as $service)
                <div class="export-service"><strong>{{ $service->nombre }}</strong><span>Todos los finalizados</span><a href="{{ route('revision.export', ['format' => 'xlsx', 'servicio' => $service->id]) }}">Excel ↓</a><a href="{{ route('revision.export', ['format' => 'pdf', 'servicio' => $service->id]) }}">PDF ↓</a></div>
            @endforeach
        </div></div>
    @endif
</section>
@if ($records->isEmpty())
    <div class="empty-state review-empty"><strong>No se encontraron propuestas finalizadas.</strong><p>Prueba con otros filtros o consulta todas las convocatorias.</p></div>
@else
    <div class="review-list">
        @foreach ($records as $record)
            <article class="review-row"><div class="review-row-main"><div class="review-row-top"><strong>{{ $record->folio }}</strong><span class="pill {{ $record->origen === 'historico' ? 'pill-neutral' : 'pill-ready' }}">{{ $record->origen === 'historico' ? 'Histórico' : 'Actual' }}</span><span class="pill {{ $record->decision === 'designado' || $record->decision === 'Designado' ? 'pill-ready' : 'pill-neutral' }}">{{ match ($record->decision) { 'designado', 'Designado' => 'Designado', 'no_aceptado' => 'No aceptado', default => 'No designado' } }}</span></div><h2>{{ $record->solicitante ?: 'Participante' }}</h2><p>{{ $record->plantel_nombre ?: 'Plantel sin dato' }} · {{ $record->servicio_nombre ?: 'Servicio CCyF' }}</p><small>{{ $record->convocatoria_nombre ?: 'Convocatoria histórica' }} · Finalizado {{ $record->finalizado_at ? \Illuminate\Support\Carbon::parse($record->finalizado_at)->format('d/m/Y H:i') : 'sin fecha registrada' }}</small></div>
                <a class="outline-button button-link" href="{{ $record->origen === 'historico' ? route('revision.historical', $record->id) : route('revision.show', $record->id) }}">Ver resultado →</a></article>
        @endforeach
    </div>
    @include('revision.pagination')
@endif
@endsection
