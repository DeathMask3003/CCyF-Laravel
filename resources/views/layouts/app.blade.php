<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#611232">
    <title>@yield('title', 'CCyF') · CoBaEMex</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=20260929">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v=20260929">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=20260929">
    <link rel="stylesheet" href="{{ asset('css/ccyf.css') }}?v=20260922-1">
    <link rel="stylesheet" href="{{ asset('css/branding.css') }}?v=20260923-1">
    <link rel="stylesheet" href="{{ asset('css/header.css') }}?v=20260923-2">
    @stack('head')
</head>
<body>
    @php($brandingService = app(\App\Services\Branding::class))
    @php($branding = $brandingService->current())
    <header class="site-header">
        <div class="header-inner">
            <a class="site-brand" href="{{ route('dashboard') }}" aria-label="CCyF, ir al panel">
                <img src="{{ $brandingService->logoUrl($branding) }}" alt="" aria-hidden="true">
                <span><strong>CCyF</strong><small title="{{ $branding->title }}">{{ $branding->title }}</small></span>
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
                @php($canPrevalAdmin = $ccyfMenu->allows(auth()->user(), 'Prevaluaciones_admin'))
                @php($canPreval = $ccyfMenu->allows(auth()->user(), 'prevaluacion') || $canPrevalAdmin)
                @php($canDocumentAdmin = $ccyfMenu->allows(auth()->user(), 'actualiza_docs'))
                @php($canContracts = $ccyfMenu->allows(auth()->user(), 'Permisionarios_aceptados_vujeig'))
                @php($canIssue = $ccyfMenu->allows(auth()->user(), 'convocatorias'))
                @php($canUsers = $ccyfMenu->allows(auth()->user(), 'Usuarios'))
                @php($canRoles = $ccyfMenu->allows(auth()->user(), 'Rol'))
                @php($canComplaints = $ccyfMenu->allows(auth()->user(), 'quejas'))
                <button class="header-menu-toggle" type="button" aria-controls="site-main-nav" aria-expanded="false" aria-label="Abrir menú principal" data-menu-toggle>
                    <span class="header-menu-icon" aria-hidden="true"><i></i><i></i><i></i></span><span>Menú</span>
                </button>
                <nav class="main-nav" id="site-main-nav" aria-label="Menú principal">
                    <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>Inicio</a>
                    @if ($ccyfMenu->allows(auth()->user(), 'NuevoOficio') || $canProducts)
                        <a href="{{ route('oficios.index') }}" @class(['active' => request()->routeIs('oficios.*')])>Nuevo registro</a>
                    @endif
                    @if ($canPending)<a href="{{ route('revision.pending') }}" aria-label="Convocatorias pendientes" @class(['active' => request()->routeIs('revision.pending')])>Pendientes</a>@endif
                    @if ($canIssue)<a href="{{ route('emision.index') }}" @class(['active' => request()->routeIs('emision.*')])>Convocatorias</a>@endif
                    @if ($canFinished)<a href="{{ route('revision.finished') }}" aria-label="{{ $ccyfMenu->isContestant(auth()->user()) ? 'Mis registros' : 'Convocatorias finalizadas' }}" @class(['active' => request()->routeIs('revision.finished', 'revision.historical')])>{{ $ccyfMenu->isContestant(auth()->user()) ? 'Mis registros' : 'Finalizadas' }}</a>@endif
                    @if ($canTracking)<a href="{{ route('seguimiento.index') }}" @class(['active' => request()->routeIs('seguimiento.*')])>Seguimiento</a>@endif
                    @if ($canPreval)<a href="{{ route('prevaluaciones.index') }}" @class(['active' => request()->routeIs('prevaluaciones.*') && ! request()->routeIs('prevaluaciones.assignments')])>Prevaluaciones</a>@endif
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
                    @if ($canUsers || $canRoles || $canPrevalAdmin || $canComplaints)
                        <details class="nav-dropdown">
                            <summary @class(['active' => request()->routeIs('usuarios.*', 'roles.*', 'branding.*', 'prevaluaciones.assignments', 'quejas.*')])>Administración <span aria-hidden="true">⌄</span></summary>
                            <div class="nav-dropdown-menu">
                                @if ($canUsers)<a href="{{ route('usuarios.index') }}" @class(['active' => request()->routeIs('usuarios.*')])>Gestión de usuarios</a>@endif
                                @if ($canRoles)<a href="{{ route('roles.index') }}" @class(['active' => request()->routeIs('roles.*')])>Gestión de roles</a>@endif
                                @if ($canPrevalAdmin)<a href="{{ route('prevaluaciones.assignments') }}" @class(['active' => request()->routeIs('prevaluaciones.assignments')])>Asignación de planteles</a>@endif
                                @if ($canComplaints)<a href="{{ route('quejas.index') }}" @class(['active' => request()->routeIs('quejas.*')])>Observaciones y quejas</a>@endif
                                @if ($canUsers)<a href="{{ route('branding.edit') }}" @class(['active' => request()->routeIs('branding.*')])>Identidad del portal</a>@endif
                            </div>
                        </details>
                    @endif
                </nav>
                <div class="header-account">
                    <details class="nav-dropdown account-dropdown">
                        <summary title="{{ auth()->user()->usu_area }}"><span class="account-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->usu_area, 0, 1)) }}</span><span class="account-name">{{ auth()->user()->usu_area }}</span><span class="account-chevron" aria-hidden="true">⌄</span></summary>
                        <div class="nav-dropdown-menu">
                            <a href="{{ route('perfil.show') }}" @class(['active' => request()->routeIs('perfil.*')])>Mi perfil</a>
                            <a href="{{ route('ubicaciones.index') }}" @class(['active' => request()->routeIs('ubicaciones.*')])>Mis ubicaciones</a>
                            <form method="post" action="{{ route('logout') }}">@csrf<button class="account-signout" type="submit">Cerrar sesión</button></form>
                        </div>
                    </details>
                </div>
            @else
                <span class="header-caption">{{ $branding->title }}</span>
            @endauth
        </div>
    </header>
    <main class="page-container @guest guest-container @endguest">
        @if (session('status')) <div class="flash" role="status">{{ session('status') }}</div> @endif
        @yield('content')
    </main>
    @stack('scripts')
    @auth
    <script>
    (() => {
        const toggle = document.querySelector('[data-menu-toggle]');
        const nav = document.getElementById('site-main-nav');
        if (!toggle || !nav) return;
        const dropdowns = [...document.querySelectorAll('.site-header details.nav-dropdown')];
        const close = () => {
            nav.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Abrir menú principal');
        };
        toggle.addEventListener('click', () => {
            const open = !nav.classList.contains('is-open');
            nav.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', String(open));
            toggle.setAttribute('aria-label', open ? 'Cerrar menú principal' : 'Abrir menú principal');
        });
        document.addEventListener('click', event => {
            dropdowns.forEach(dropdown => {
                if (!dropdown.contains(event.target)) dropdown.open = false;
            });
            if (!nav.contains(event.target) && !toggle.contains(event.target)) close();
        });
        dropdowns.forEach(dropdown => dropdown.addEventListener('toggle', () => {
            if (dropdown.open) dropdowns.forEach(other => { if (other !== dropdown) other.open = false; });
        }));
        document.addEventListener('keydown', event => {
            if (event.key !== 'Escape') return;
            dropdowns.forEach(dropdown => { dropdown.open = false; });
            close();
        });
        window.matchMedia('(min-width: 1101px)').addEventListener('change', event => { if (event.matches) close(); });
    })();
    </script>
    @endauth
</body>
</html>
