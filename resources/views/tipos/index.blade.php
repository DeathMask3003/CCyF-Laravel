@extends('layouts.app')

@section('title', 'Tipo de documento')

@section('content')
<div class="page-heading"><div><span class="eyebrow">Catálogos · CCyF</span><h1>Tipo de documento</h1><p>Administra los tipos que pueden seleccionarse al preparar un nuevo oficio. Los tipos desactivados siguen visibles para conservar el historial.</p></div><span class="pill pill-neutral">{{ $types->where('activo', 1)->count() }} {{ $types->where('activo', 1)->count() === 1 ? 'activo' : 'activos' }}</span></div>
@if ($errors->any()) <div class="form-errors" role="alert"><strong>Revisa los datos:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
<section class="panel type-create"><div><span class="eyebrow">Nuevo registro</span><h2>Agregar tipo</h2><p class="muted">Estará disponible en los nuevos oficios desde que lo guardes.</p></div><form method="post" action="{{ route('tipos.store') }}">@csrf<label for="new-type">Nombre del tipo</label><div class="inline-form"><input id="new-type" name="nombre" value="{{ old('nombre') }}" maxlength="50" placeholder="Ej. Convocatoria" required><button class="button" type="submit">Agregar tipo</button></div></form></section>
<div class="section-bar"><div><span class="eyebrow">Catálogo</span><h2>Tipos registrados</h2></div><span class="muted">{{ $types->count() }} en total</span></div>
<div class="type-list">
    @forelse ($types as $type)
        <article class="type-item" @if (! $type->activo) data-inactive="true" @endif>
            <div class="type-meta"><span class="pill {{ $type->activo ? 'pill-ready' : 'pill-pending' }}">{{ $type->activo ? 'Activo' : 'Inactivo' }}</span><span>Ref. {{ $type->legacy_tipo_id ? '#'.$type->legacy_tipo_id : 'nueva' }}</span><span>{{ (int) ($legacyUsage->get($type->legacy_tipo_id) ?? 0) }} oficios anteriores</span></div>
            <div class="type-actions"><form method="post" action="{{ route('tipos.update', $type->id) }}">@csrf @method('PUT')<label for="type-{{ $type->id }}">Nombre del tipo</label><div class="inline-form"><input id="type-{{ $type->id }}" name="nombre" value="{{ $type->nombre }}" maxlength="50" required><button class="small-button" type="submit">Guardar nombre</button></div></form>
                <form method="post" action="{{ route('tipos.toggle', $type->id) }}">@csrf @method('PATCH')<button class="outline-button" type="submit">{{ $type->activo ? 'Desactivar' : 'Activar' }}</button></form>
            </div>
        </article>
    @empty
        <div class="empty-state">No hay tipos de documento. Agrega el primero para continuar.</div>
    @endforelse
</div>
@endsection
