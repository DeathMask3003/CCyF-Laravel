@extends('layouts.app')

@section('title', 'Panel')

@section('content')
<div class="page-heading">
    <div><span class="eyebrow">Panel de trabajo</span><h1>Bienvenido, {{ auth()->user()->usu_area }}</h1><p>Gestiona las convocatorias y propuestas de CCyF desde este espacio.</p></div>
</div>
@php($ccyfMenu = app(\App\Services\LegacyMenu::class))
@if ($ccyfMenu->isContestant(auth()->user()) || $ccyfMenu->allows(auth()->user(), 'Usuarios'))
    @php($portalBrandingService = app(\App\Services\Branding::class))
    @php($portalContent = $portalBrandingService->current())
    @php($showPortalDocument = $portalBrandingService->documentFile($portalContent) !== null)
    @php($showAnnouncement = $portalContent->announcement_visible)
    @if ($showPortalDocument || $showAnnouncement)
    <div class="portal-featured @if($showAnnouncement) has-announcement @endif">
        @if ($showAnnouncement)
            <article class="portal-announcement-card" aria-labelledby="portal-announcement-title">
                <div class="portal-announcement-media">
                    @if ($portalBrandingService->announcementVideoFile($portalContent))
                        <video controls playsinline preload="none" @if($portalBrandingService->announcementImageFile($portalContent)) poster="{{ route('branding.announcement.media', ['kind' => 'imagen']) }}" @endif aria-label="Video de la convocatoria"><source src="{{ route('branding.announcement.media', ['kind' => 'video']) }}" type="{{ str_ends_with($portalContent->announcement_video_path, '.webm') ? 'video/webm' : 'video/mp4' }}">Tu navegador no puede reproducir este video.</video>
                    @elseif ($portalBrandingService->announcementImageFile($portalContent))
                        <img src="{{ route('branding.announcement.media', ['kind' => 'imagen']) }}" alt="Imagen de la convocatoria" loading="lazy">
                    @else
                        <div class="portal-announcement-placeholder" aria-hidden="true"><span>CCyF</span><strong>Convocatorias</strong></div>
                    @endif
                </div>
                <div class="portal-announcement-body">
                    <span class="eyebrow">Convocatoria · COBAEM</span>
                    <h2 id="portal-announcement-title">{{ $portalContent->announcement_title }}</h2>
                    @if($portalContent->announcement_description)<p>{{ $portalContent->announcement_description }}</p>@endif
                    @if($portalContent->announcement_link_1_url || $portalContent->announcement_link_2_url)
                        <div class="portal-announcement-actions">
                            @foreach([1, 2] as $number)
                                @if($portalContent->{'announcement_link_'.$number.'_url'})
                                    <a href="{{ $portalContent->{'announcement_link_'.$number.'_url'} }}" @if($portalContent->{'announcement_link_'.$number.'_blank'}) target="_blank" rel="noopener noreferrer" @endif>{{ $portalContent->{'announcement_link_'.$number.'_label'} ?: ($number === 1 ? 'Consultar convocatoria' : 'Más información') }} <span aria-hidden="true">{{ $portalContent->{'announcement_link_'.$number.'_blank'} ? '↗' : '→' }}</span></a>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </article>
        @endif
        @if ($showPortalDocument)
            <section class="portal-document-card" aria-labelledby="portal-document-title"><div class="portal-document-icon" aria-hidden="true">PDF</div><div class="portal-document-copy"><span class="eyebrow">Información para participantes</span><h2 id="portal-document-title">{{ $portalContent->document_title }}</h2><p>{{ $portalContent->document_description }}</p></div><a class="button button-link portal-document-open" href="{{ route('branding.document') }}" data-review-document data-document-name="{{ $portalContent->document_title }}" data-document-mime="application/pdf">Consultar documento <span aria-hidden="true">↗</span></a></section>
        @endif
    </div>
    @endif
    @if ($showPortalDocument)
        @include('revision.document-viewer', ['viewerEyebrow' => 'Información para participantes'])
    @endif
@endif
<div class="dashboard-grid">
    @if ($ccyfMenu->allows(auth()->user(), 'NuevoOficio') || $ccyfMenu->allows(auth()->user(), 'Categorias_widi'))
        <article class="feature-card"><span class="feature-icon">01</span><h2>Nuevo registro</h2><p>Presenta la propuesta completa con precios, comentarios y documentos PDF.</p><a class="text-link" href="{{ route('oficios.index') }}">Iniciar registro <span aria-hidden="true">→</span></a></article>
    @endif
    @if ($ccyfMenu->allows(auth()->user(), 'Categorias_widi'))
        <article class="feature-card"><span class="feature-icon">02</span><h2>Productos y precios</h2><p>Organiza los alimentos o servicios de fotocopiado que se solicitarán en cada convocatoria.</p><a class="text-link" href="{{ route('catalogos.index') }}">Administrar catálogos <span aria-hidden="true">→</span></a></article>
    @endif
    @if ($ccyfMenu->allows(auth()->user(), 'Tipo'))
        <article class="feature-card"><span class="feature-icon">03</span><h2>Tipo de documento</h2><p>Define los tipos disponibles al preparar un nuevo oficio y conserva los anteriores para consulta.</p><a class="text-link" href="{{ route('tipos.index') }}">Administrar tipos <span aria-hidden="true">→</span></a></article>
    @endif
    @if ($ccyfMenu->allows(auth()->user(), 'Asuntos'))
        <article class="feature-card"><span class="feature-icon">04</span><h2>Tipos de servicios</h2><p>Administra los servicios de las convocatorias y su descripción para los nuevos registros.</p><a class="text-link" href="{{ route('servicios.index') }}">Administrar servicios <span aria-hidden="true">→</span></a></article>
    @endif
    @if ($ccyfMenu->allows(auth()->user(), 'Areas'))
        <article class="feature-card"><span class="feature-icon">05</span><h2>Gestionar planteles</h2><p>Actualiza los planteles participantes y sus condiciones para cada tipo de servicio.</p><a class="text-link" href="{{ route('planteles.index') }}">Administrar planteles <span aria-hidden="true">→</span></a></article>
    @endif
    @if ($ccyfMenu->allows(auth()->user(), 'Categorias_widi'))
        <article class="feature-card"><span class="feature-icon">06</span><h2>Número de convocatoria</h2><p>Define el número, el servicio y el estado de cada convocatoria.</p><a class="text-link" href="{{ route('convocatorias.index') }}">Administrar convocatorias <span aria-hidden="true">→</span></a></article>
    @endif
    @if ($ccyfMenu->allows(auth()->user(), 'Subcategorias_widi'))
        <article class="feature-card"><span class="feature-icon">07</span><h2>Enlaces para convocatoria</h2><p>Selecciona los planteles y CEMSaD autorizados para participar en cada convocatoria.</p><a class="text-link" href="{{ route('enlaces.index') }}">Administrar enlaces <span aria-hidden="true">→</span></a></article>
    @endif
    @if (! $ccyfMenu->isContestant(auth()->user()) && $ccyfMenu->allows(auth()->user(), 'gestionOficio'))
        <article class="feature-card"><span class="feature-icon">08</span><h2>Convocatorias pendientes</h2><p>Revisa propuestas, documentos y precios; registra la decisión y designación.</p><a class="text-link" href="{{ route('revision.pending') }}">Revisar propuestas <span aria-hidden="true">→</span></a></article>
    @endif
    @if ($ccyfMenu->allows(auth()->user(), 'buscarOficio') || $ccyfMenu->allows(auth()->user(), 'gestionOficio') || $ccyfMenu->allows(auth()->user(), 'NuevoOficio'))
        <article class="feature-card"><span class="feature-icon">09</span><h2>{{ $ccyfMenu->isContestant(auth()->user()) ? 'Mis registros' : 'Convocatorias finalizadas' }}</h2><p>{{ $ccyfMenu->isContestant(auth()->user()) ? 'Sigue tus propuestas enviadas y consulta sus resultados.' : 'Consulta resultados y expedientes actuales e históricos de CCyF.' }}</p><a class="text-link" href="{{ route('revision.finished') }}">{{ $ccyfMenu->isContestant(auth()->user()) ? 'Ver mis registros' : 'Ver resultados' }} <span aria-hidden="true">→</span></a></article>
    @endif
    @if ($ccyfMenu->allows(auth()->user(), 'Usuarios'))
        <article class="feature-card"><span class="feature-icon">10</span><h2>Gestión de usuarios</h2><p>Administra cuentas, roles asignados y acceso al sistema.</p><a class="text-link" href="{{ route('usuarios.index') }}">Administrar usuarios <span aria-hidden="true">→</span></a></article>
    @endif
    @if ($ccyfMenu->allows(auth()->user(), 'Rol'))
        <article class="feature-card"><span class="feature-icon">11</span><h2>Gestión de roles</h2><p>Define los permisos de los módulos de CCyF para cada rol.</p><a class="text-link" href="{{ route('roles.index') }}">Administrar roles <span aria-hidden="true">→</span></a></article>
    @endif
</div>
@endsection
