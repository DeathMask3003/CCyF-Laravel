<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#611232">
    <title>@yield('title', 'CCyF') · CoBaEMex</title>
    <link rel="stylesheet" href="{{ asset('css/ccyf.css') }}?v=20260922-1">
    @stack('head')
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
                @php($canProducts = $ccyfMenu->allows(auth()->user(), 'Categorias_widi'))
                @php($canTypes = $ccyfMenu->allows(auth()->user(), 'Tipo'))
                @php($canServices = $ccyfMenu->allows(auth()->user(), 'Asuntos'))
                @php($canCampuses = $ccyfMenu->allows(auth()->user(), 'Areas'))
                @php($canConvocations = $ccyfMenu->allows(auth()->user(), 'Categorias_widi'))
                @php($canLinks = $ccyfMenu->allows(auth()->user(), 'Subcategorias_widi'))
                @php($canPending = ! $ccyfMenu->isContestant(auth()->user()) && $ccyfMenu->allows(auth()->user(), 'gestionOficio'))
                @php($canFinished = $ccyfMenu->allows(auth()->user(), 'buscarOficio') || $canPending || $ccyfMenu->allows(auth()->user(), 'NuevoOficio'))
                @php($canTracking = $ccyfMenu->allows(auth()->user(), 'seguimiento_permisionarios'))
                @php($canPreval = $ccyfMenu->allows(auth()->user(), 'prevaluacion') || $ccyfMenu->allows(auth()->user(), 'Prevaluaciones_admin'))
                @php($canDocumentAdmin = $ccyfMenu->allows(auth()->user(), 'actualiza_docs'))
                @php($canContracts = $ccyfMenu->allows(auth()->user(), 'Permisionarios_aceptados_vujeig'))
                @php($canIssue = $ccyfMenu->allows(auth()->user(), 'convocatorias'))
                @php($canUsers = $ccyfMenu->allows(auth()->user(), 'Usuarios'))
                @php($canRoles = $ccyfMenu->allows(auth()->user(), 'Rol'))
                <nav class="main-nav" aria-label="Menú principal">
                    <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>Inicio</a>
                    @if ($ccyfMenu->allows(auth()->user(), 'NuevoOficio') || $canProducts)
                        <a href="{{ route('oficios.index') }}" @class(['active' => request()->routeIs('oficios.*')])>Nuevo registro</a>
                    @endif
                    @if ($canPending)<a href="{{ route('revision.pending') }}" aria-label="Convocatorias pendientes" @class(['active' => request()->routeIs('revision.pending')])>Pendientes</a>@endif
                    @if ($canIssue)<a href="{{ route('emision.index') }}" @class(['active' => request()->routeIs('emision.*')])>Convocatorias</a>@endif
                    @if ($canFinished)<a href="{{ route('revision.finished') }}" aria-label="Convocatorias finalizadas" @class(['active' => request()->routeIs('revision.finished', 'revision.historical')])>Finalizadas</a>@endif
                    @if ($canTracking)<a href="{{ route('seguimiento.index') }}" @class(['active' => request()->routeIs('seguimiento.*')])>Seguimiento</a>@endif
                    @if ($canPreval)<a href="{{ route('prevaluaciones.index') }}" @class(['active' => request()->routeIs('prevaluaciones.*')])>Prevaluaciones</a>@endif
                    @if ($canDocumentAdmin)<a href="{{ route('documentacion.index') }}" @class(['active' => request()->routeIs('documentacion.*')])>Documentación</a>@endif
                    @if ($canContracts)<a href="{{ route('contratos.index') }}" @class(['active' => request()->routeIs('contratos.*')])>Contratos</a>@endif
                    @if ($canProducts || $canTypes || $canServices || $canCampuses || $canConvocations || $canLinks)
                        <details class="nav-dropdown">
                            <summary @class(['active' => request()->routeIs('catalogos.*', 'tipos.*', 'servicios.*', 'planteles.*', 'convocatorias.*', 'enlaces.*')])>Catálogos <span aria-hidden="true">⌄</span></summary>
                            <div class="nav-dropdown-menu">
                                @if ($canCampuses)<a href="{{ route('planteles.index') }}" @class(['active' => request()->routeIs('planteles.*')])>Gestionar planteles</a>@endif
                                @if ($canConvocations)<a href="{{ route('convocatorias.index') }}" @class(['active' => request()->routeIs('convocatorias.*')])>Número de convocatoria</a>@endif
                                @if ($canLinks)<a href="{{ route('enlaces.index') }}" @class(['active' => request()->routeIs('enlaces.*')])>Enlaces para convocatoria</a>@endif
                                @if ($canProducts)<a href="{{ route('catalogos.index') }}" @class(['active' => request()->routeIs('catalogos.*')])>Productos y precios</a>@endif
                                @if ($canTypes)<a href="{{ route('tipos.index') }}" @class(['active' => request()->routeIs('tipos.*')])>Tipo de documento</a>@endif
                                @if ($canServices)<a href="{{ route('servicios.index') }}" @class(['active' => request()->routeIs('servicios.*')])>Tipos de servicios</a>@endif
                            </div>
                        </details>
                    @endif
                    @if ($canUsers || $canRoles)
                        <details class="nav-dropdown">
                            <summary @class(['active' => request()->routeIs('usuarios.*', 'roles.*')])>Administración <span aria-hidden="true">⌄</span></summary>
                            <div class="nav-dropdown-menu">
                                @if ($canUsers)<a href="{{ route('usuarios.index') }}" @class(['active' => request()->routeIs('usuarios.*')])>Gestión de usuarios</a>@endif
                                @if ($canRoles)<a href="{{ route('roles.index') }}" @class(['active' => request()->routeIs('roles.*')])>Gestión de roles</a>@endif
                            </div>
                        </details>
                    @endif
                </nav>
                <div class="header-account">
                    <details class="nav-dropdown account-dropdown">
                        <summary title="{{ auth()->user()->usu_area }}">{{ auth()->user()->usu_area }} <span aria-hidden="true">⌄</span></summary>
                        <div class="nav-dropdown-menu">
                            <a href="{{ route('perfil.show') }}" @class(['active' => request()->routeIs('perfil.*')])>Mi perfil</a>
                            <a href="{{ route('documentacion.index') }}" @class(['active' => request()->routeIs('documentacion.*')])>Mis documentos</a>
                            <a href="{{ route('ubicaciones.index') }}" @class(['active' => request()->routeIs('ubicaciones.*')])>Mis ubicaciones</a>
                        </div>
                    </details>
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
    @stack('scripts')
</body>
</html>
