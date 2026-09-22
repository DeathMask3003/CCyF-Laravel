<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#611232">
    <title>@yield('title', 'CCyF') · CoBaEMex</title>
    <link rel="stylesheet" href="{{ asset('css/ccyf.css') }}">
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a class="site-brand" href="{{ route('dashboard') }}" aria-label="CCyF, ir al panel">
                <img src="{{ asset('images/ccyf.png') }}" alt="" aria-hidden="true">
                <span><strong>CCyF</strong><small>CoBaEMex</small></span>
            </a>
            @auth
                @php($ccyfMenu = app(\App\Services\LegacyMenu::class))
                <nav class="main-nav" aria-label="Menú principal">
                    <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>Inicio</a>
                    @if ($ccyfMenu->allows(auth()->user(), 'NuevoOficio') || $ccyfMenu->allows(auth()->user(), 'Categorias_widi'))
                        <a href="{{ route('oficios.index') }}" @class(['active' => request()->routeIs('oficios.*')])>Nuevo oficio</a>
                    @endif
                    @if ($ccyfMenu->allows(auth()->user(), 'Categorias_widi'))
                        <a href="{{ route('catalogos.index') }}" @class(['active' => request()->routeIs('catalogos.*')])>Productos y precios</a>
                    @endif
                    @if ($ccyfMenu->allows(auth()->user(), 'Tipo'))
                        <a href="{{ route('tipos.index') }}" @class(['active' => request()->routeIs('tipos.*')])>Tipo de documento</a>
                    @endif
                </nav>
                <div class="header-account">
                    <span title="{{ auth()->user()->usu_area }}">{{ auth()->user()->usu_area }}</span>
                    <form method="post" action="{{ route('logout') }}">@csrf<button class="logout-button" type="submit">Salir</button></form>
                </div>
            @else
                <span class="header-caption">Concurso de Cafetería y Fotocopiado</span>
            @endauth
        </div>
    </header>
    <main class="page-container @guest guest-container @endguest">
        @if (session('status')) <div class="flash" role="status">{{ session('status') }}</div> @endif
        @yield('content')
    </main>
</body>
</html>
