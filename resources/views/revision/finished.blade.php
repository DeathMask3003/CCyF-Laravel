@extends('layouts.app')

@section('title', 'Convocatorias finalizadas')
@section('content')
<div class="page-heading review-heading"><div><span class="eyebrow">Resultados · CCyF</span><h1>Convocatorias finalizadas</h1><p>Consulta la respuesta y los documentos de propuestas actuales e históricas.</p></div><span class="pill pill-ready">{{ $total }} resultados</span></div>
<div class="info-strip">Aquí puedes consultar las propuestas finalizadas, incluidas las registradas antes de la actualización.</div>
@include('revision.filters', ['action' => route('revision.finished')])
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
