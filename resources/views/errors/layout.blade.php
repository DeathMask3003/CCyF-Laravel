<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#611232">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'No se pudo abrir la página') · CCyF</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('css/errors.css') }}?v=1">
</head>
<body>
    <div class="error-shell">
        <header class="error-header">
            <a class="error-brand" href="{{ url('/') }}" aria-label="CCyF, ir al inicio">
                <img src="{{ asset('images/ccyf-default.svg') }}" alt="" width="46" height="46">
                <span><strong>CCyF</strong><small>COBAEM · Estado de México</small></span>
            </a>
            <span class="error-header-label">PORTAL INSTITUCIONAL</span>
        </header>

        <main class="error-main">
            <section class="error-card" aria-labelledby="error-heading">
                <div class="error-illustration" aria-hidden="true">
                    <div class="error-document"><span></span><span></span><span></span></div>
                    <strong>@yield('code')</strong>
                </div>
                <div class="error-content">
                    <span class="error-kicker">CCyF · Aviso del portal</span>
                    <h1 id="error-heading">@yield('heading')</h1>
                    <p class="error-description">@yield('description')</p>
                    @hasSection('tip')
                        <p class="error-tip">@yield('tip')</p>
                    @endif
                    <div class="error-actions">
                        <a class="error-primary" href="@yield('action_url', url('/'))">@yield('action_label', 'Ir al inicio') <span aria-hidden="true">→</span></a>
                        <button class="error-secondary" type="button" onclick="history.back()">Volver atrás</button>
                    </div>
                </div>
            </section>
            <p class="error-assistance">Si el problema continúa, comunícate con el equipo de CCyF e indica el código <strong>@yield('code')</strong>.</p>
        </main>

        <footer class="error-footer">Colegio de Bachilleres del Estado de México <span aria-hidden="true">·</span> Concurso de Cafetería y Fotocopiado</footer>
    </div>
</body>
</html>
