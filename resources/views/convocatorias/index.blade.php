@extends('layouts.app')

@section('title', 'Número de convocatoria')

@section('content')
<div class="page-heading"><div><span class="eyebrow">Catálogos · CCyF</span><h1>Número de convocatoria</h1><p>Administra cada convocatoria, su tipo de servicio y los planteles participantes.</p></div><a class="button button-link" href="{{ route('convocatorias.create') }}">Agregar convocatoria</a></div>
<div class="convocation-list">
    @forelse ($convocations as $convocation)
        <article class="convocation-card" @if (! $convocation->activo) data-inactive="true" @endif><div class="convocation-main"><div class="type-meta"><span class="pill {{ $convocation->activo ? 'pill-ready' : 'pill-pending' }}">{{ $convocation->activo ? 'Activa' : 'Inactiva' }}</span><span>{{ $convocation->servicio_nombre ?: 'Servicio por asignar' }}</span><span>{{ $convocation->planteles_total }} planteles</span><span>{{ $convocation->catalogo_id ? 'Productos configurados' : 'Productos pendientes' }}</span></div><h2>{{ $convocation->numero }}</h2></div><div class="convocation-actions"><a class="small-button button-link" href="{{ route('convocatorias.edit', $convocation->id) }}">Editar</a>@if ($convocation->activo && $convocation->servicio_id)<a class="outline-button button-link" href="{{ route('catalogos.show', $convocation->id) }}">{{ $convocation->catalogo_id ? 'Ver productos' : 'Preparar productos' }}</a>@endif<form method="post" action="{{ route('convocatorias.toggle', $convocation->id) }}">@csrf @method('PATCH')<button class="quiet-button" type="submit">{{ $convocation->activo ? 'Desactivar' : 'Activar' }}</button></form></div></article>
    @empty
        <div class="empty-state">No hay convocatorias registradas.</div>
    @endforelse
</div>
@endsection
