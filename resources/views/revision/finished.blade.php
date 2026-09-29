@extends('layouts.app')

@section('title', $isContestant ? 'Mis registros' : 'Convocatorias finalizadas')
@push('head')<link rel="stylesheet" href="{{ asset('css/review-cards.css') }}?v=20260927-2">@endpush
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
    <div class="review-list review-cards">
        @foreach ($records as $record)
            @include('revision.partials.record-card', ['record' => $record, 'pending' => false, 'isContestant' => $isContestant])
        @endforeach
    </div>
    @include('revision.pagination')
@endif
@endsection
