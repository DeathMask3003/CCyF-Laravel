@extends('layouts.app')

@section('title', 'Gestión de roles')

@section('content')
<div class="page-heading identity-heading"><div><span class="eyebrow">Administración · CCyF</span><h1>Gestión de roles</h1><p>Define el acceso a los módulos de CCyF para cada equipo de trabajo.</p></div><a class="button button-link" href="{{ route('roles.create') }}">Agregar rol</a></div>
@if ($errors->any()) <div class="form-errors" role="alert">{{ $errors->first() }}</div> @endif
<div class="identity-list-head"><span>{{ $roles->count() }} roles</span><a class="text-link" href="{{ route('usuarios.index') }}">Ver usuarios <span aria-hidden="true">→</span></a></div>
<div class="role-grid">
    @forelse ($roles as $role)
        <article class="role-card" @if (! $role->est) data-inactive="true" @endif>
            <div class="list-card-top"><span class="pill {{ $role->est ? 'pill-ready' : 'pill-pending' }}">{{ $role->est ? 'Activo' : 'Inactivo' }}</span><span class="muted">{{ $role->legacy_rol_id ? 'Rol histórico #'.$role->rol_id : 'Rol nuevo #'.$role->rol_id }}</span></div>
            <h2>{{ $role->rol_nom }}</h2><p>{{ $role->usuarios }} {{ $role->usuarios == 1 ? 'usuario asignado' : 'usuarios asignados' }}</p>
            <div class="card-actions"><a class="text-link" href="{{ route('roles.edit', $role->rol_id) }}">Editar permisos <span aria-hidden="true">→</span></a><form method="post" action="{{ route('roles.toggle', $role->rol_id) }}">@csrf @method('PATCH')<button class="quiet-button" type="submit">{{ $role->est ? 'Desactivar' : 'Activar' }}</button></form></div>
        </article>
    @empty
        <div class="empty-state">Aún no hay roles. Agrega el primero para comenzar.</div>
    @endforelse
</div>
@endsection
