@extends('layouts.app')

@section('title', 'Panel')

@section('content')
<section class="card" aria-labelledby="welcome-title">
    <span class="eyebrow">Tu cuenta</span>
    <h2 id="welcome-title">Bienvenido, {{ auth()->user()->usu_area }}</h2>
    <p class="muted">Tu acceso está verificado. Las funciones de convocatorias y evaluación estarán disponibles aquí conforme termine su migración.</p>
    <form method="post" action="{{ route('logout') }}">
        @csrf
        <button class="primary" type="submit">Cerrar sesión</button>
    </form>
</section>
@endsection
