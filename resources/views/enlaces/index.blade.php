@extends('layouts.app')

@section('title', 'Enlaces para convocatoria')

@section('content')
<div class="page-heading"><div><span class="eyebrow">Participación · CCyF</span><h1>Enlaces para convocatoria</h1><p>Relaciona cada convocatoria con los planteles y CEMSaD que podrán recibir propuestas.</p></div></div>
<div class="link-overview"><div><strong>{{ $links->count() }}</strong><span>convocatorias</span></div><div><strong>{{ $links->where('activo', true)->count() }}</strong><span>activas</span></div><div><strong>{{ $links->sum('planteles_total') }}</strong><span>enlaces históricos</span></div></div>
<div class="convocation-list">
    @forelse ($links as $link)
        <article class="convocation-card link-card" @if (! $link->activo) data-inactive="true" @endif>
            <div class="convocation-main"><div class="type-meta"><span class="pill {{ $link->activo ? 'pill-ready' : 'pill-pending' }}">{{ $link->activo ? 'Activa' : 'Inactiva' }}</span><span>{{ $link->servicio_nombre ?: 'Servicio por asignar' }}</span></div><h2>{{ $link->numero }}</h2><p>{{ $link->planteles_total ? $link->planteles_total.' '.((int) $link->planteles_total === 1 ? 'plantel vinculado' : 'planteles vinculados') : 'Sin planteles vinculados' }}</p></div>
            <div class="link-card-meter"><span style="--link-width: {{ min(100, (int) $link->planteles_total) }}%"></span></div>
            <a class="small-button button-link" href="{{ route('enlaces.edit', $link->id) }}">{{ $link->planteles_total ? 'Editar enlaces' : 'Asignar planteles' }}</a>
        </article>
    @empty
        <div class="empty-state">No hay convocatorias registradas.</div>
    @endforelse
</div>
@endsection
