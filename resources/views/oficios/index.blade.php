@extends('layouts.app')

@section('title', 'Nuevo oficio')

@section('content')
<div class="page-heading"><div><span class="eyebrow">Nuevo oficio</span><h1>Elige una convocatoria</h1><p>Los precios se capturan con los productos configurados para cada convocatoria.</p></div></div>
@unless ($canSave) <div class="info-strip">Vista de administración: puedes revisar el formulario, pero el registro de propuestas corresponde a usuarios autorizados.</div> @endunless
<div class="list-grid">
    @forelse ($catalogs as $catalog)
        @php($category = $categories->get($catalog->legacy_cat_id))
        <article class="list-card"><div class="list-card-top"><span class="pill pill-ready">{{ $catalog->servicio_nombre }}</span><span class="muted">#{{ $category->cat_id }}</span></div><h2>{{ $category->cat_nom }}</h2><p>Formulario de precios de esta convocatoria.</p><a class="text-link" href="{{ route('oficios.show', $category->cat_id) }}">{{ $canSave ? 'Capturar precios' : 'Vista previa' }} <span aria-hidden="true">→</span></a></article>
    @empty
        <div class="empty-state">Aún no hay catálogos configurados para convocatorias vigentes.</div>
    @endforelse
</div>
@endsection
