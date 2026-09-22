@extends('layouts.app')

@section('title', 'Gestión de usuarios')

@section('content')
<div class="page-heading identity-heading"><div><span class="eyebrow">Administración · CCyF</span><h1>Gestión de usuarios</h1><p>Consulta las cuentas, asigna roles y controla quién puede ingresar al sistema.</p></div><a class="button button-link" href="{{ route('usuarios.create') }}">Agregar usuario</a></div>
@if ($errors->any()) <div class="form-errors" role="alert">{{ $errors->first() }}</div> @endif
<div class="identity-metrics"><div><strong>{{ $summary->total }}</strong><span>Cuentas registradas</span></div><div><strong>{{ $summary->activos ?? 0 }}</strong><span>Activas</span></div><div><strong>{{ $roles->where('est', 1)->count() }}</strong><span>Roles activos</span></div></div>
<form class="identity-filters panel" method="get" action="{{ route('usuarios.index') }}" aria-label="Filtrar usuarios">
    <div><label for="user-search">Nombre, correo o teléfono</label><input id="user-search" type="search" name="q" value="{{ $search }}" placeholder="Buscar usuario"></div>
    <div><label for="user-role">Rol</label><select id="user-role" name="rol"><option value="">Todos los roles</option>@foreach ($roles as $role)<option value="{{ $role->rol_id }}" @selected($roleFilter === (int) $role->rol_id)>{{ $role->rol_nom }}</option>@endforeach</select></div>
    <div><label for="user-status">Estado</label><select id="user-status" name="estado"><option value="">Todos</option><option value="1" @selected($status === '1')>Activos</option><option value="0" @selected($status === '0')>Inactivos</option></select></div>
    <button class="button" type="submit">Aplicar filtros</button>
    @if ($search !== '' || $roleFilter || $status !== '') <a class="identity-clear" href="{{ route('usuarios.index') }}">Limpiar</a> @endif
</form>
<div class="identity-list-head"><span>{{ $users->total() }} resultados</span><a class="text-link" href="{{ route('roles.index') }}">Gestionar roles <span aria-hidden="true">→</span></a></div>
<div class="identity-list">
    @forelse ($users as $user)
        <article class="identity-user" @if (! $user->est || ! $user->rol_activo) data-inactive="true" @endif>
            <div class="identity-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->usu_area, 0, 1)) }}</div>
            <div class="identity-user-main"><div class="identity-user-title"><h2>{{ $user->usu_area }}</h2><span class="pill {{ $user->est && $user->rol_activo ? 'pill-ready' : 'pill-pending' }}">{{ ! $user->est ? 'Inactivo' : (! $user->rol_activo ? 'Rol inactivo' : 'Activo') }}</span></div><p>{{ $user->usu_correo ?: 'Sin correo' }}</p><div class="identity-user-meta"><span>{{ $user->rol_nom ?: 'Sin rol' }}</span><span>{{ $user->usu_telf ?: 'Sin teléfono' }}</span><span>{{ $user->legacy_usu_id ? 'Cuenta histórica #'.$user->usu_id : 'Cuenta nueva #'.$user->usu_id }}</span></div></div>
            <div class="identity-actions"><a class="outline-button button-link" href="{{ route('usuarios.edit', $user->usu_id) }}">Editar</a><form method="post" action="{{ route('usuarios.toggle', $user->usu_id) }}">@csrf @method('PATCH')<button class="quiet-button" type="submit">{{ $user->est ? 'Desactivar' : 'Activar' }}</button></form></div>
        </article>
    @empty
        <div class="empty-state">No se encontraron usuarios con esos filtros.</div>
    @endforelse
</div>
@if ($users->lastPage() > 1)<nav class="pagination" aria-label="Páginas de usuarios">@if ($users->onFirstPage())<span>← Anterior</span>@else<a href="{{ $users->previousPageUrl() }}">← Anterior</a>@endif <strong>Página {{ $users->currentPage() }} de {{ $users->lastPage() }}</strong> @if ($users->hasMorePages())<a href="{{ $users->nextPageUrl() }}">Siguiente →</a>@else<span>Siguiente →</span>@endif</nav>@endif
@endsection
