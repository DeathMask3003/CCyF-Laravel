@extends('layouts.app')

@section('title', 'Convocatorias pendientes')
@section('content')
<div class="page-heading review-heading"><div><span class="eyebrow">Gestión de propuestas · CCyF</span><h1>Convocatorias pendientes</h1><p>Revisa el expediente y la oferta de cada concursante antes de registrar el resultado.</p></div><span class="pill pill-pending">{{ $records->total() }} por revisar</span></div>
<div class="review-stats"><div><strong>{{ $records->total() }}</strong><span>Resultados de los filtros</span></div><div><strong>{{ $summary->sum() }}</strong><span>Propuestas recibidas</span></div><div><strong>{{ $convocations->count() }}</strong><span>Convocatorias registradas</span></div></div>
@include('revision.filters', ['action' => route('revision.pending')])
@if ($errors->any())<div class="form-errors" role="alert"><strong>Revisa la selección.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if ($records->isEmpty())
    <div class="empty-state review-empty"><strong>No hay propuestas pendientes con estos filtros.</strong><p>Los registros enviados desde Nuevo registro aparecerán aquí para su revisión.</p></div>
@else
    <form id="bulk-review" method="post" action="{{ route('revision.reject-bulk') }}" class="review-bulk panel">@csrf
        <div><strong>Finalizar varias como no aceptadas</strong><p>Selecciona las propuestas y escribe una respuesta que quedará en cada expediente.</p></div>
        <label class="sr-only" for="bulk-answer">Respuesta para las propuestas seleccionadas</label>
        <input id="bulk-answer" name="respuesta" maxlength="250" placeholder="Respuesta obligatoria para las seleccionadas" required>
        <button class="outline-button" type="submit" onclick="return confirm('¿Finalizar las propuestas seleccionadas como no aceptadas?')">Finalizar seleccionadas</button>
    </form>
    <div class="review-list">
        @foreach ($records as $record)
            <article class="review-row"><div class="review-selection"><input type="checkbox" name="registros[]" value="{{ $record->id }}" form="bulk-review" aria-label="Seleccionar {{ $record->folio }}"></div>
                <div class="review-row-main"><div class="review-row-top"><strong>{{ $record->folio }}</strong><span class="pill pill-pending">Recibido</span></div><h2>{{ $record->solicitante }}</h2><p>{{ $record->plantel_nombre }} · {{ $record->servicio_nombre }}</p><small>{{ $record->convocatoria_nombre }} · Enviado {{ \Illuminate\Support\Carbon::parse($record->enviado_at)->format('d/m/Y H:i') }}</small></div>
                <a class="outline-button button-link" href="{{ route('revision.show', $record->id) }}">Revisar expediente →</a></article>
        @endforeach
    </div>
    @include('revision.pagination')
@endif
@endsection
