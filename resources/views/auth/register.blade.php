@extends('auth.shell')

@section('title', 'Crear cuenta')
@section('eyebrow', 'Registro de concursantes')
@section('heading', 'Crea tu cuenta')
@section('intro', 'Regístrate para participar en las convocatorias vigentes y consultar tus registros.')

@section('form')
<form method="post" action="{{ route('register.store') }}">
    @csrf
    <div class="field"><label for="nombre">Nombre completo</label><input id="nombre" name="nombre" type="text" maxlength="150" autocomplete="name" value="{{ old('nombre') }}" required autofocus>@error('nombre')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
    <div class="field"><label for="email">Correo electrónico</label><input id="email" name="email" type="email" maxlength="150" autocomplete="email" value="{{ old('email') }}" required>@error('email')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
    <div class="field"><label for="telefono">Teléfono celular</label><input id="telefono" name="telefono" type="tel" inputmode="numeric" pattern="[0-9]{10}" minlength="10" maxlength="10" autocomplete="tel-national" value="{{ old('telefono') }}" placeholder="10 dígitos" required><small>Escribe únicamente los 10 dígitos de tu número.</small>@error('telefono')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
    <div class="field"><label for="password">Contraseña</label><div class="password-field"><input id="password" name="password" type="password" autocomplete="new-password" required><button type="button" data-password-toggle="password" aria-label="Mostrar contraseña">Mostrar</button></div><small>Mínimo 10 caracteres, mayúsculas, minúsculas y números.</small>@error('password')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
    <div class="field"><label for="password_confirmation">Confirmar contraseña</label><div class="password-field"><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required><button type="button" data-password-toggle="password_confirmation" aria-label="Mostrar contraseña">Mostrar</button></div></div>
    <x-turnstile action="register" />
    <button class="submit" type="submit">Crear cuenta <span aria-hidden="true">→</span></button>
</form>
<p class="switch">¿Ya tienes cuenta? <a href="{{ route('login') }}">Iniciar sesión</a></p>
@endsection
