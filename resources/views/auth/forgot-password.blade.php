@extends('auth.shell')

@section('title', 'Recuperar acceso')
@section('eyebrow', 'Recuperación de acceso')
@section('heading', 'Recupera tu contraseña')
@section('intro', 'Escribe el correo asociado a tu cuenta. Te enviaremos un enlace para crear una contraseña nueva.')

@section('form')
<form method="post" action="{{ route('password.email') }}">
    @csrf
    <div class="field"><label for="email">Correo electrónico</label><input id="email" name="email" type="email" maxlength="150" autocomplete="email" value="{{ old('email') }}" placeholder="nombre@correo.com" required autofocus>@error('email')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
    <button class="submit" type="submit">Enviar enlace <span aria-hidden="true">→</span></button>
</form>
<p class="switch"><a href="{{ route('login') }}">← Volver al inicio de sesión</a></p>
@endsection
