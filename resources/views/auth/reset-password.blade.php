@extends('auth.shell')

@section('title', 'Nueva contraseña')
@section('eyebrow', 'Recuperación de acceso')
@section('heading', 'Elige una contraseña nueva')
@section('intro', 'Cuenta: '.$email)

@section('form')
<form method="post" action="{{ route('password.update') }}">
    @csrf
    <input type="hidden" name="cuenta" value="{{ $account }}"><input type="hidden" name="token" value="{{ $token }}">
    <div class="field"><label for="password">Nueva contraseña</label><div class="password-field"><input id="password" name="password" type="password" autocomplete="new-password" required autofocus><button type="button" data-password-toggle="password" aria-label="Mostrar contraseña">Mostrar</button></div><small>Mínimo 10 caracteres, mayúsculas, minúsculas y números.</small>@error('password')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
    <div class="field"><label for="password_confirmation">Confirmar contraseña</label><div class="password-field"><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required><button type="button" data-password-toggle="password_confirmation" aria-label="Mostrar contraseña">Mostrar</button></div></div>
    <button class="submit" type="submit">Guardar contraseña <span aria-hidden="true">→</span></button>
</form>
<p class="switch"><a href="{{ route('login') }}">← Volver al inicio de sesión</a></p>
@endsection
