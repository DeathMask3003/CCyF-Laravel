@extends('layouts.app')

@section('title', 'Nuevo registro')
@push('head')<link rel="stylesheet" href="{{ asset('css/new-registration.css') }}?v=20260924-1">@endpush

@section('content')
@php($available = $catalogs->filter(fn ($catalog) => $categories->has($catalog->convocatoria_id)))
<div class="newreg-page">
    <header class="newreg-hero newreg-hero-index">
        <div class="newreg-hero-copy"><span class="newreg-kicker"><span class="newreg-kicker-dot"></span> Concurso de Cafetería y Fotocopiado</span><h1>Tu propuesta comienza aquí.</h1><p>Elige una convocatoria vigente. Podrás revisar los planteles disponibles, capturar tus precios y entregar el expediente digital en un solo recorrido.</p><div class="newreg-hero-meta"><span>{{ $available->count() }} {{ $available->count() === 1 ? 'servicio disponible' : 'servicios disponibles' }}</span><span>Registro con folio al finalizar</span></div></div>
        <div class="newreg-hero-mark" aria-hidden="true"><span>CC<span>y</span>F</span><small>2026</small></div>
    </header>

    @unless ($canSave)<div class="info-strip">Vista de administración: puedes revisar el formulario completo. El envío corresponde a las cuentas con permiso de Nuevo Registro.</div>@endunless

    <section class="newreg-overview" aria-label="Cómo participar">
        <div class="newreg-overview-title"><span class="eyebrow">Proceso de participación</span><h2>De la convocatoria a tu folio</h2><p>Todo lo necesario para presentar una propuesta completa.</p></div>
        <ol class="newreg-steps"><li><span>01</span><strong>Elige convocatoria</strong><small>Selecciona el servicio vigente.</small></li><li><span>02</span><strong>Prepara tu oferta</strong><small>Indica plantel y precios.</small></li><li><span>03</span><strong>Adjunta los PDF</strong><small>Hasta 3 MB por archivo.</small></li><li><span>04</span><strong>Recibe tu folio</strong><small>Conserva tu comprobante.</small></li></ol>
    </section>

    <div class="newreg-section-heading"><div><span class="eyebrow">Convocatorias abiertas</span><h2>Selecciona dónde participar</h2></div><span class="newreg-count">{{ $available->count() }} disponibles</span></div>
    <div class="newreg-choice-grid">
        @forelse ($available as $catalog)
            @php($category = $categories->get($catalog->convocatoria_id))
            <article class="newreg-choice"><div class="newreg-choice-top"><span class="newreg-service">{{ $catalog->servicio_nombre }}</span><span class="newreg-active"><span></span> Vigente</span></div><span class="newreg-choice-caption">Convocatoria {{ str_pad((string) $category->cat_id, 2, '0', STR_PAD_LEFT) }}</span><h3>{{ $category->cat_nom }}</h3><p>Presenta una propuesta para los planteles vinculados a esta convocatoria.</p><div class="newreg-choice-bottom"><span>{{ $records->get($category->cat_id, 0) ? $records->get($category->cat_id).' registro(s) enviado(s)' : 'Sin registros enviados' }}</span><a href="{{ route('oficios.show', $category->cat_id) }}" aria-label="{{ $canSave ? 'Iniciar registro para' : 'Ver formulario de' }} {{ $category->cat_nom }}">{{ $canSave ? 'Iniciar registro' : 'Ver formulario' }} <span aria-hidden="true">↗</span></a></div></article>
        @empty
            <div class="newreg-empty"><span aria-hidden="true">◌</span><h3>Sin convocatorias disponibles</h3><p>Aún no hay convocatorias vigentes con productos configurados.</p></div>
        @endforelse
    </div>
</div>
@endsection
