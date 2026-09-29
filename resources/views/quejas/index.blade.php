@extends('layouts.app')

@push('head')<link rel="stylesheet" href="{{ asset('css/complaints.css') }}?v=20260927-2">@endpush
@section('title', 'Observaciones y quejas')
@section('content')
<div class="complaint-heading">
    <div><span class="eyebrow">Seguimiento institucional</span><h1>Observaciones y quejas</h1><p>Consulta el historial por participación y registra incidencias con su evidencia. Los registros históricos permanecen disponibles.</p></div>
    @if($view === 'manuales')<a class="button complaint-button" href="{{ route('quejas.manual.create') }}">+ Nueva queja manual</a>@endif
</div>

<div class="complaint-stats" aria-label="Resumen de observaciones y quejas">
    <div><span>Permisionarios</span><strong>{{ number_format($peopleCount) }}</strong></div>
    <div><span>Participaciones</span><strong>{{ number_format($participationCount) }}</strong></div>
    <div><span>Observaciones registradas</span><strong>{{ number_format($complaintCount) }}</strong></div>
    <div><span>Quejas manuales activas</span><strong>{{ number_format($manualCount) }}</strong></div>
</div>

<nav class="complaint-tabs" aria-label="Tipo de registro">
    <a href="{{ route('quejas.index') }}" @class(['active' => $view === 'participaciones']) @if($view === 'participaciones') aria-current="page" @endif>Por participación</a>
    <a href="{{ route('quejas.index', ['vista' => 'manuales']) }}" @class(['active' => $view === 'manuales']) @if($view === 'manuales') aria-current="page" @endif>Quejas manuales</a>
</nav>

<section class="panel complaint-filter-panel" aria-label="Filtros">
    <form method="get" action="{{ route('quejas.index') }}" class="complaint-filters" data-filter-form>
        <input type="hidden" name="vista" value="{{ $view }}">
        <label>Buscar <input type="search" name="buscar" value="{{ request('buscar') }}" placeholder="Permisionario, plantel o detalle" autocomplete="off" data-search></label>
        @if($view === 'participaciones')
            <label>Servicio <select name="servicio" data-autosubmit><option value="">Todos</option><option value="cafeteria" @selected(request('servicio') === 'cafeteria')>Cafetería</option><option value="fotocopiado" @selected(request('servicio') === 'fotocopiado')>Fotocopiado</option></select></label>
        @endif
        <label>Plantel <select name="plantel" data-autosubmit><option value="">Todos los planteles</option>@foreach($campuses as $campus)<option value="{{ $campus }}" @selected(request('plantel') === $campus)>{{ $campus }}</option>@endforeach</select></label>
        <label>Estado <select name="estado" data-autosubmit>
            <option value="">Todos</option>
            @if($view === 'participaciones')<option value="con" @selected(request('estado') === 'con')>Con historial</option><option value="sin" @selected(request('estado') === 'sin')>Sin historial</option>
            @else<option value="activas" @selected(request('estado') === 'activas')>Activas</option><option value="inactivas" @selected(request('estado') === 'inactivas')>Archivadas</option>@endif
        </select></label>
        <button class="outline-button" type="submit">Buscar</button>
    </form>
</section>

<div class="complaint-results-heading"><div><span class="eyebrow">{{ $view === 'manuales' ? 'Registro manual' : 'Expedientes' }}</span><h2>{{ $view === 'manuales' ? 'Quejas por plantel' : 'Permisionarios y participaciones' }}</h2></div><span class="pill pill-neutral">{{ $rows->total() }} resultados</span></div>

@if($rows->isEmpty())
    <div class="empty-state complaint-empty"><strong>Sin resultados</strong><p>Ajusta los filtros o realiza otra búsqueda.</p></div>
@elseif($view === 'manuales')
    <div class="manual-grid">
        @foreach($rows as $row)
            <article class="manual-card">
                <div class="manual-card-top"><span @class(['complaint-status', 'is-muted' => ! $row->activo])>{{ $row->activo ? 'Activa' : 'Archivada' }}</span><span class="manual-origin">{{ $row->origen === 'historico' ? 'Histórico' : 'CCyF' }}</span></div>
                <h3>{{ $row->permisionario }}</h3><p class="manual-campus">{{ $row->plantel }}</p>
                <p class="manual-text">{{ $row->queja }}</p>
                <div class="manual-card-footer"><small>{{ $row->autor }} · {{ $row->fecha ?: 'Sin fecha' }}</small>
                    @if($row->origen === 'local')<div class="manual-actions"><a href="{{ route('quejas.manual.edit', $row->id) }}">Editar</a><form method="post" action="{{ route('quejas.manual.toggle', $row->id) }}" onsubmit="return confirm('{{ $row->activo ? '¿Archivar esta queja?' : '¿Reactivar esta queja?' }}')">@csrf @method('PATCH')<button type="submit">{{ $row->activo ? 'Archivar' : 'Reactivar' }}</button></form></div>@endif
                </div>
            </article>
        @endforeach
    </div>
@else
    <div class="complaint-people">
        @foreach($rows as $person)
            <details class="person-card" @if(request('buscar') && $rows->count() === 1) open @endif>
                <summary><span class="person-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($person->nombre, 0, 1)) }}</span><span class="person-identity"><strong>{{ $person->nombre }}</strong><small>{{ $person->correo ?: 'Sin correo registrado' }}</small></span><span class="person-count">{{ $person->total }} {{ $person->total === 1 ? 'participación' : 'participaciones' }}</span><span class="person-scores"><span class="score-good">{{ $person->buenas }} favorables</span><span class="score-bad">{{ $person->malas }} incidencias</span></span><span class="person-chevron" aria-hidden="true">⌄</span></summary>
                <div class="participation-list">
                    @foreach($person->participaciones as $item)
                        <div class="participation-item"><div><strong>{{ $item->plantel }}</strong><p>{{ $item->convocatoria }} · {{ $item->servicio === 'cafeteria' ? 'Cafetería' : 'Fotocopiado' }}</p></div><div class="participation-meta"><span @class(['complaint-status', 'is-good' => $item->total && $item->buenas >= $item->malas, 'is-bad' => $item->malas > $item->buenas])>{{ $item->total ? ($item->buenas >= $item->malas ? 'Favorable' : 'Con incidencias') : 'Sin evaluación' }}</span><small>{{ $item->total }} {{ $item->total === 1 ? 'registro' : 'registros' }}</small></div><a class="outline-button" href="{{ route('quejas.show', [$item->origen, $item->registro_id]) }}">Ver historial y registrar</a></div>
                    @endforeach
                </div>
            </details>
        @endforeach
    </div>
@endif
<div class="complaint-pagination">{{ $rows->onEachSide(1)->links('quejas.pagination') }}</div>

@push('scripts')
<script>(() => { const form = document.querySelector('[data-filter-form]'); if (!form) return; form.querySelectorAll('[data-autosubmit]').forEach(input => input.addEventListener('change', () => form.requestSubmit())); const search = form.querySelector('[data-search]'); let timer; search?.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => form.requestSubmit(), 450); }); })();</script>
@endpush
@endsection
