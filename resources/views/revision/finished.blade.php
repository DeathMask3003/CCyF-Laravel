@extends('layouts.app')

@section('title', $isContestant ? 'Mis registros' : 'Convocatorias finalizadas')
@section('content')
<div class="page-heading review-heading"><div><span class="eyebrow">{{ $isContestant ? 'Participaciones · CCyF' : 'Resultados · CCyF' }}</span><h1>{{ $isContestant ? 'Mis registros' : 'Convocatorias finalizadas' }}</h1><p>{{ $isContestant ? 'Consulta tus propuestas enviadas y sigue su estado hasta la resolución.' : 'Consulta la respuesta y los documentos de propuestas actuales e históricas.' }}</p></div><span class="pill pill-ready">{{ $total }} {{ $isContestant ? 'registros' : 'resultados' }}</span></div>
<div class="info-strip">{{ $isContestant ? 'Los registros recién enviados aparecen como Recibido mientras se revisan. Solo tú puedes consultar tus expedientes.' : 'Aquí puedes consultar las propuestas finalizadas, incluidas las registradas antes de la actualización.' }}</div>
@include('revision.filters', ['action' => route('revision.finished')])
<section class="export-panel panel" aria-label="Exportar resultados">
    <div class="export-current"><div><span class="eyebrow">Exportación</span><h2>{{ $isContestant ? 'Mis resultados finalizados' : 'Resultados del filtro actual' }}</h2><p>{{ $isContestant ? 'Descarga únicamente tus propuestas que ya tienen resolución.' : 'Incluye los '.$total.' resultados, aunque estén distribuidos en varias páginas.' }}</p></div>
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
    <div class="empty-state review-empty"><strong>{{ $isContestant ? 'Aún no tienes registros con estos filtros.' : 'No se encontraron propuestas finalizadas.' }}</strong><p>{{ $isContestant ? 'Prueba otro servicio o limpia la búsqueda.' : 'Prueba con otros filtros o consulta todas las convocatorias.' }}</p></div>
@else
    <div class="review-list">
        @foreach ($records as $record)
            <article class="review-row"><div class="review-row-main"><div class="review-row-top"><strong>{{ $record->folio }}</strong><span class="pill {{ $record->origen === 'historico' ? 'pill-neutral' : 'pill-ready' }}">{{ $record->origen === 'historico' ? 'Histórico' : 'Actual' }}</span><span class="pill {{ $record->estado === 'Recibido' ? 'pill-pending' : ($record->decision === 'designado' || $record->decision === 'Designado' ? 'pill-ready' : 'pill-neutral') }}">{{ $record->estado === 'Recibido' ? 'Recibido · en revisión' : match ($record->decision) { 'designado', 'Designado' => 'Designado', 'no_aceptado' => 'No aceptado', default => 'No designado' } }}</span></div><h2>{{ $record->solicitante ?: 'Participante' }}</h2><p>{{ $record->plantel_nombre ?: 'Plantel sin dato' }} · {{ $record->servicio_nombre ?: 'Servicio CCyF' }}</p><small>{{ $record->convocatoria_nombre ?: 'Convocatoria histórica' }} · {{ $record->estado === 'Recibido' ? 'Enviado' : 'Finalizado' }} {{ ($record->finalizado_at ?: $record->enviado_at) ? \Illuminate\Support\Carbon::parse($record->finalizado_at ?: $record->enviado_at)->format('d/m/Y H:i') : 'sin fecha registrada' }}</small></div>
                <div class="review-row-actions">
                    <a class="outline-button button-link" href="{{ $record->origen === 'historico' ? route('revision.historical', $record->id) : route('revision.show', $record->id) }}">{{ $record->estado === 'Recibido' ? 'Ver registro' : 'Ver resultado' }} →</a>
                    @if ($record->resultado_pdf_disponible)
                        <a class="button button-link" href="{{ route('revision.historical-result-pdf', $record->id) }}" target="_blank" rel="noopener">Carta PDF ↗</a>
                    @endif
                    @if ($record->evaluacion_pdf_disponible)
                        <a class="outline-button button-link" href="{{ $record->origen === 'historico' ? route('revision.historical-final-evaluation-pdf', $record->id) : route('revision.final-evaluation-pdf', $record->id) }}" target="_blank" rel="noopener">Evaluación PDF ↗</a>
                    @endif
                </div></article>
        @endforeach
    </div>
    @include('revision.pagination')
@endif
@endsection
