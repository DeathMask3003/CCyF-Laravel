@extends('layouts.app')

@section('title', 'Tipos de servicios')

@section('content')
<div class="page-heading"><div><span class="eyebrow">Catálogos · CCyF</span><h1>Tipos de servicios</h1><p>Administra los servicios disponibles para las convocatorias. Los servicios desactivados conservan sus oficios y configuraciones anteriores.</p></div><span class="pill pill-neutral">{{ $services->where('activo', 1)->count() }} {{ $services->where('activo', 1)->count() === 1 ? 'activo' : 'activos' }}</span></div>
@if ($errors->any()) <div class="form-errors" role="alert"><strong>Revisa los datos:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
<section class="panel service-create"><div><span class="eyebrow">Nuevo registro</span><h2>Agregar tipo de servicio</h2><p class="muted">Después podrás asignarlo a una convocatoria y definir sus productos.</p></div><form method="post" action="{{ route('servicios.store') }}">@csrf<div><label for="new-service">Nombre del servicio</label><input id="new-service" name="nombre" value="{{ old('nombre') }}" maxlength="50" placeholder="Ej. Cafetería" required></div><div><label for="new-description">Descripción breve</label><input id="new-description" name="descripcion" value="{{ old('descripcion') }}" maxlength="200" placeholder="Describe para qué se utilizará" required></div><button class="button" type="submit">Agregar servicio</button></form></section>
<div class="section-bar"><div><span class="eyebrow">Catálogo</span><h2>Servicios registrados</h2></div><span class="muted">{{ $services->count() }} en total</span></div>
<div class="service-list">
    @forelse ($services as $service)
        <article class="service-item" @if (! $service->activo) data-inactive="true" @endif>
            <div class="type-meta"><span class="pill {{ $service->activo ? 'pill-ready' : 'pill-pending' }}">{{ $service->activo ? 'Activo' : 'Inactivo' }}</span><span>Ref. {{ $service->legacy_trami_id ? '#'.$service->legacy_trami_id : 'nueva' }}</span><span>{{ (int) ($legacyUsage->get($service->legacy_trami_id) ?? 0) }} oficios anteriores</span><span>{{ (int) ($catalogUsage->get($service->id) ?? 0) }} convocatorias configuradas</span></div>
            <form class="service-edit" method="post" action="{{ route('servicios.update', $service->id) }}">@csrf @method('PUT')<div><label for="service-{{ $service->id }}">Tipo de servicio</label><input id="service-{{ $service->id }}" name="nombre" value="{{ $service->nombre }}" maxlength="50" required></div><div><label for="description-{{ $service->id }}">Descripción breve</label><input id="description-{{ $service->id }}" name="descripcion" value="{{ $service->descripcion }}" maxlength="200" required></div><button class="small-button" type="submit">Guardar cambios</button></form>
            <form class="service-toggle" method="post" action="{{ route('servicios.toggle', $service->id) }}">@csrf @method('PATCH')<button class="outline-button" type="submit">{{ $service->activo ? 'Desactivar' : 'Activar' }}</button></form>
        </article>
    @empty
        <div class="empty-state">No hay tipos de servicios. Agrega el primero para continuar.</div>
    @endforelse
</div>
@endsection
