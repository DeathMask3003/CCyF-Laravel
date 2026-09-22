@extends('layouts.app')

@section('title', $role ? 'Editar rol' : 'Nuevo rol')

@section('content')
<a class="back-link" href="{{ route('roles.index') }}">← Volver a Gestión de roles</a>
<div class="page-heading"><div><span class="eyebrow">Administración · CCyF</span><h1>{{ $role ? 'Editar rol' : 'Agregar rol' }}</h1><p>Selecciona los módulos que podrán usar las cuentas con este rol.</p></div>@if ($role)<span class="pill {{ $role->est ? 'pill-ready' : 'pill-pending' }}">{{ $role->est ? 'Activo' : 'Inactivo' }}</span>@endif</div>
@if ($errors->any()) <div class="form-errors" role="alert"><strong>Revisa los datos:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
<form method="post" action="{{ $role ? route('roles.update', $role->rol_id) : route('roles.store') }}" class="role-form">
    @csrf @if ($role) @method('PUT') @endif
    <div class="panel"><label for="role-name">Nombre del rol</label><input id="role-name" name="nombre" value="{{ old('nombre', $role->rol_nom ?? '') }}" maxlength="80" required placeholder="Ej. Revisor de convocatorias"><p class="field-help">El nombre identifica a este grupo en la gestión de usuarios.</p></div>
    @foreach ($groups as $group => $permissions)
        <fieldset class="panel role-permission-group"><legend>{{ $group }}</legend><div class="role-permission-grid">
            @foreach ($permissions as $key => $label)
                <label class="role-permission"><input type="checkbox" name="permisos[]" value="{{ $key }}" @checked(in_array($key, old('permisos', $selected), true))><span><strong>{{ $label }}</strong><small>{{ $key === 'Rol' || $key === 'Usuarios' ? 'Administración del sistema' : 'Acceso al módulo' }}</small></span></label>
            @endforeach
        </div></fieldset>
    @endforeach
    <div class="sticky-actions"><a class="outline-button button-link" href="{{ route('roles.index') }}">Cancelar</a><button class="button" type="submit">{{ $role ? 'Guardar rol y permisos' : 'Crear rol' }}</button></div>
</form>
@endsection
