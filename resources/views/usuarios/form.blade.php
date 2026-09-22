@extends('layouts.app')

@section('title', $user ? 'Editar usuario' : 'Nuevo usuario')

@section('content')
<a class="back-link" href="{{ route('usuarios.index') }}">← Volver a Gestión de usuarios</a>
<div class="page-heading"><div><span class="eyebrow">Administración · CCyF</span><h1>{{ $user ? 'Editar usuario' : 'Agregar usuario' }}</h1><p>{{ $user ? 'Actualiza sus datos y rol. Deja la contraseña vacía para conservar la actual.' : 'Crea una cuenta para acceder a CCyF con verificación por correo.' }}</p></div>@if ($user)<span class="pill {{ $user->est ? 'pill-ready' : 'pill-pending' }}">{{ $user->est ? 'Activo' : 'Inactivo' }}</span>@endif</div>
@if ($errors->any()) <div class="form-errors" role="alert"><strong>Revisa los datos:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
<form method="post" action="{{ $user ? route('usuarios.update', $user->usu_id) : route('usuarios.store') }}" class="panel identity-form">
    @csrf @if ($user) @method('PUT') @endif
    <div class="identity-form-intro"><span class="feature-icon">01</span><div><h2>Datos de la cuenta</h2><p>El correo se usa para iniciar sesión y recibir el código de verificación.</p></div></div>
    <div class="identity-fields">
        <div><label for="name">Nombre completo</label><input id="name" name="nombre" value="{{ old('nombre', $user->usu_area ?? '') }}" maxlength="150" required autocomplete="name"></div>
        <div><label for="email">Correo electrónico</label><input id="email" type="email" name="correo" value="{{ old('correo', $user->usu_correo ?? '') }}" maxlength="150" required autocomplete="email"></div>
        <div><label for="phone">Teléfono</label><input id="phone" name="telefono" value="{{ old('telefono', $user->usu_telf ?? '') }}" maxlength="30" inputmode="tel" autocomplete="tel"></div>
        <div><label for="area">Plantel o departamento</label><select id="area" name="area_id"><option value="">Sin asignar</option>@foreach ($areas as $area)<option value="{{ $area->area_id }}" @selected((string) old('area_id', $user->area_id ?? '') === (string) $area->area_id)>{{ $area->area_nom }}{{ $area->est ? '' : ' · Inactivo' }}</option>@endforeach</select></div>
        <div class="identity-full"><label for="role">Rol</label><select id="role" name="rol_id" required><option value="">Selecciona un rol</option>@foreach ($roles as $role)<option value="{{ $role->rol_id }}" @selected((string) old('rol_id', $user->rol_id ?? '') === (string) $role->rol_id)>{{ $role->rol_nom }}</option>@endforeach</select><p class="field-help">Los permisos de este rol se configuran en Gestión de roles.</p></div>
    </div>
    <div class="identity-form-intro identity-password-intro"><span class="feature-icon">02</span><div><h2>{{ $user ? 'Cambiar contraseña' : 'Contraseña inicial' }}</h2><p>{{ $user ? 'Completa estos campos solo si deseas cambiarla.' : 'Usa al menos diez caracteres. El usuario también verificará su acceso por correo.' }}</p></div></div>
    <div class="identity-fields">
        <div><label for="password">{{ $user ? 'Nueva contraseña' : 'Contraseña' }}</label><input id="password" type="password" name="password" minlength="10" maxlength="255" @if (! $user) required @endif autocomplete="new-password"></div>
        <div><label for="password-confirmation">Confirmar contraseña</label><input id="password-confirmation" type="password" name="password_confirmation" minlength="10" maxlength="255" @if (! $user) required @endif autocomplete="new-password"></div>
    </div>
    <div class="identity-form-actions"><a class="outline-button button-link" href="{{ route('usuarios.index') }}">Cancelar</a><button class="button" type="submit">{{ $user ? 'Guardar cambios' : 'Crear usuario' }}</button></div>
</form>
@endsection
