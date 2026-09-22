@extends('layouts.app')

@section('title', 'Verificación')

@section('content')
<section class="card" aria-labelledby="challenge-title">
    <span class="eyebrow">Verificación adicional</span>
    <h2 id="challenge-title">Revisa tu correo</h2>
    <p class="muted">Escribe el código de seis dígitos que te enviamos. Caduca en diez minutos.</p>
    @if (session('status')) <p class="notice" role="status">{{ session('status') }}</p> @endif

    <form method="post" action="{{ route('mfa.verify') }}">
        @csrf
        <label for="code">Código de verificación</label>
        <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
        @error('code') <p class="error" role="alert">{{ $message }}</p> @enderror
        <button class="primary" type="submit">Verificar acceso</button>
    </form>

    <form method="post" action="{{ route('mfa.resend') }}">
        @csrf
        <button class="link-button" type="submit">Enviar otro código</button>
    </form>
</section>
@endsection
