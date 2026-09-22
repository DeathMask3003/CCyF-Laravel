@extends('layouts.app')

@section('title', 'Acceso')

@section('content')
<section class="card" aria-labelledby="login-title">
    <span class="eyebrow">Acceso seguro</span>
    <h2 id="login-title">Iniciar sesión</h2>
    <p class="muted">Ingresa con tu correo y contraseña de CCyF.</p>

    <form method="post" action="{{ route('login.store') }}">
        @csrf
        <label for="email">Correo electrónico</label>
        <input id="email" name="email" type="email" maxlength="150" autocomplete="username" value="{{ old('email') }}" required autofocus>
        @error('email') <p class="error" role="alert">{{ $message }}</p> @enderror

        <label for="password">Contraseña</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        @error('password') <p class="error" role="alert">{{ $message }}</p> @enderror

        <button class="primary" type="submit">Iniciar sesión</button>
    </form>
</section>
@endsection
