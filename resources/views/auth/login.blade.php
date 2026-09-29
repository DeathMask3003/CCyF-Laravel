@extends('auth.shell')

@section('title', 'Iniciar sesión')
@section('heading', 'Bienvenido de nuevo')
@section('intro', 'Ingresa con el correo y la contraseña de tu cuenta.')

@section('form')
<form method="post" action="{{ route('login.store') }}">
    @csrf
    <div class="field"><label for="email">Correo electrónico</label><input id="email" name="email" type="email" maxlength="150" autocomplete="username" value="{{ old('email') }}" placeholder="nombre@correo.com" required autofocus>@error('email')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
    <div class="field"><div class="label-row"><label for="password">Contraseña</label><a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a></div><div class="password-field"><input id="password" name="password" type="password" autocomplete="current-password" placeholder="Tu contraseña" required><button type="button" data-password-toggle="password" aria-label="Mostrar contraseña">Mostrar</button></div>@error('password')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
    <label class="remember"><input type="checkbox" name="remember" value="1" @checked(old('remember'))><span>Recordarme en este dispositivo</span></label>
    <x-turnstile action="login" />
    <button class="submit" type="submit">Entrar a CCyF <span aria-hidden="true">→</span></button>
</form>
@if(app(\App\Services\ProductionIntegrations::class)->google())
    <div class="auth-divider"><span>o continúa con</span></div>
    <a class="google-button" href="{{ route('login.google') }}"><span aria-hidden="true" class="google-mark">G</span> Google</a>
@endif
<p class="switch">¿Aún no tienes cuenta? <a href="{{ route('register') }}">Crear cuenta de concursante</a></p>
@endsection
