<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#56152f">
    <title>@yield('title') · CCyF</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}?v=20260923-1">
    <link rel="stylesheet" href="{{ asset('css/branding.css') }}?v=20260923-1">
</head>
<body>
@php($brandingService = app(\App\Services\Branding::class))
@php($branding = $brandingService->current())
@php($mottoParts = preg_split('/(?<=[.!?])\s+/u', $branding->motto, 2))
<main class="auth-layout">
    <section class="auth-hero" aria-label="Colegio de Bachilleres del Estado de México">
        <div class="hero-inner">
            <a class="brand brand-light" href="{{ route('login') }}" aria-label="CCyF, página de acceso">
                <img class="brand-logo" src="{{ $brandingService->logoUrl($branding) }}" alt="" aria-hidden="true">
                <span><strong>CCyF</strong><small>COBAEM · Estado de México</small></span>
            </a>
            <div class="hero-copy">
                <span class="hero-kicker">PORTAL INSTITUCIONAL</span>
                <h1>{{ $mottoParts[0] }}@if(isset($mottoParts[1]))<br><em>{{ $mottoParts[1] }}</em>@endif</h1>
                <p>Un espacio para consultar convocatorias, registrar propuestas y dar seguimiento a tu participación en cafetería y fotocopiado.</p>
                <div class="hero-pills"><span>Convocatorias</span><span>Documentos</span><span>Seguimiento</span></div>
            </div>
            <p class="hero-footer">Colegio de Bachilleres del Estado de México <span>·</span> Coordinación de Cafetería y Fotocopiado</p>
        </div>
        <div class="hero-rings" aria-hidden="true"></div>
    </section>
    <section class="auth-side">
        <div class="mobile-brand"><img class="brand-logo" src="{{ $brandingService->logoUrl($branding) }}" alt="" aria-hidden="true"><span><strong>CCyF</strong><small>COBAEM · Estado de México</small></span></div>
        <div class="auth-card">
            <div class="card-top"><span class="eyebrow">@yield('eyebrow', 'Acceso a CCyF')</span><span class="secure-tag"><span aria-hidden="true">●</span> Conexión segura</span></div>
            @if(request()->routeIs('login'))
                <div class="auth-emblem"><img src="{{ $brandingService->logoUrl($branding) }}" alt="Logo de {{ $branding->title }}"><div><strong>{{ $branding->title }}</strong><span>Portal de convocatorias</span></div></div>
            @endif
            <h2>@yield('heading')</h2>
            <p class="intro">@yield('intro')</p>
            @if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
            @yield('form')
        </div>
        <p class="auth-footer">© {{ date('Y') }} CCyF · COBAEM</p>
    </section>
</main>
<script>
document.querySelectorAll('[data-password-toggle]').forEach(button => {
    button.addEventListener('click', () => {
        const field = document.getElementById(button.dataset.passwordToggle);
        if (!field) return;
        const shown = field.type === 'password';
        field.type = shown ? 'text' : 'password';
        button.textContent = shown ? 'Ocultar' : 'Mostrar';
        button.setAttribute('aria-label', shown ? 'Ocultar contraseña' : 'Mostrar contraseña');
    });
});
</script>
</body>
</html>
