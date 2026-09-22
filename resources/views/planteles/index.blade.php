@extends('layouts.app')

@section('title', 'Gestionar planteles')

@section('content')
<div class="page-heading"><div><span class="eyebrow">Catálogos · CCyF</span><h1>Gestionar planteles</h1><p>Administra los planteles y CEMSaD que pueden participar en las convocatorias.</p></div><a class="button button-link" href="{{ route('planteles.create') }}">Agregar plantel</a></div>
<form class="search-bar" method="get" action="{{ route('planteles.index') }}"><label class="sr-only" for="campus-search">Buscar plantel</label><input id="campus-search" name="q" value="{{ $search }}" placeholder="Buscar por nombre, correo o dirección"><button class="outline-button" type="submit">Buscar</button>@if ($search !== '')<a href="{{ route('planteles.index') }}">Limpiar</a>@endif</form>
<div class="campus-summary"><span>{{ $campuses->total() }} planteles</span><span>Página {{ $campuses->currentPage() }} de {{ $campuses->lastPage() }}</span></div>
<div class="campus-grid">
    @forelse ($campuses as $campus)
        <article class="campus-card" @if (! $campus->activo) data-inactive="true" @endif><div class="list-card-top"><span class="pill {{ $campus->activo ? 'pill-ready' : 'pill-pending' }}">{{ $campus->activo ? 'Activo' : 'Inactivo' }}</span><span class="muted">{{ $campus->legacy_area_id ? 'Ref. #'.$campus->legacy_area_id : 'Nuevo' }}</span></div><h2>{{ $campus->nombre }}</h2><p class="campus-email">{{ $campus->correo ?: 'Sin correo registrado' }}</p><p class="campus-address">{{ $campus->direccion ?: 'Sin dirección registrada' }}</p><div class="card-actions"><a class="text-link" href="{{ route('planteles.edit', $campus->id) }}">Editar datos <span aria-hidden="true">→</span></a><form method="post" action="{{ route('planteles.toggle', $campus->id) }}">@csrf @method('PATCH')<button class="quiet-button" type="submit">{{ $campus->activo ? 'Desactivar' : 'Activar' }}</button></form></div></article>
    @empty
        <div class="empty-state">No se encontraron planteles con ese criterio.</div>
    @endforelse
</div>
@if ($campuses->lastPage() > 1)<nav class="pagination" aria-label="Páginas de planteles">@if ($campuses->onFirstPage())<span>← Anterior</span>@else<a href="{{ $campuses->previousPageUrl() }}">← Anterior</a>@endif <strong>{{ $campuses->currentPage() }}</strong> @if ($campuses->hasMorePages())<a href="{{ $campuses->nextPageUrl() }}">Siguiente →</a>@else<span>Siguiente →</span>@endif</nav>@endif
@endsection
