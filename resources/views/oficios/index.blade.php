@extends('layouts.app')

@section('title', 'Nuevo registro')

@section('content')
<div class="page-heading"><div><span class="eyebrow">Participación CCyF</span><h1>Nuevo registro</h1><p>Selecciona la convocatoria para presentar tu propuesta, precios y documentación.</p></div></div>
@unless ($canSave) <div class="info-strip">Vista de administración: puedes revisar el formulario completo. El envío corresponde a las cuentas con permiso de Nuevo Registro.</div> @endunless
<div class="registration-intro"><div><span>01</span><strong>Elige convocatoria</strong><small>Servicio vigente</small></div><div><span>02</span><strong>Captura la propuesta</strong><small>Datos y precios</small></div><div><span>03</span><strong>Adjunta los PDF</strong><small>Máximo 3 MB</small></div><div><span>04</span><strong>Recibe tu folio</strong><small>Confirmación inmediata</small></div></div>
<div class="list-grid">
    @forelse ($catalogs as $catalog)
        @php($category = $categories->get($catalog->convocatoria_id))
        @if ($category)
        <article class="list-card registration-card"><div class="list-card-top"><span class="pill pill-ready">{{ $catalog->servicio_nombre }}</span><span class="muted">#{{ $category->cat_id }}</span></div><h2>{{ $category->cat_nom }}</h2><p>{{ $records->get($category->cat_id, 0) ? $records->get($category->cat_id).' registro(s) enviado(s) en esta convocatoria.' : 'Formulario disponible para los planteles enlazados.' }}</p><a class="text-link" href="{{ route('oficios.show', $category->cat_id) }}">{{ $canSave ? 'Iniciar registro' : 'Ver formulario' }} <span aria-hidden="true">→</span></a></article>
        @endif
    @empty
        <div class="empty-state">Aún no hay convocatorias vigentes con productos configurados.</div>
    @endforelse
</div>
@endsection
