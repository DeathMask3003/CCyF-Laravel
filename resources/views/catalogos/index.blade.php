@extends('layouts.app')

@section('title', 'Productos y precios')

@section('content')
<div class="page-heading"><div><span class="eyebrow">Configuración</span><h1>Productos por convocatoria</h1><p>Cada convocatoria tiene su propia lista. Puedes agregar, ordenar y retirar productos cuando cambie la convocatoria.</p></div></div>
<div class="list-grid">
    @forelse ($categories as $category)
        @php($catalog = $configured->get($category->cat_id))
        <article class="list-card">
            <div class="list-card-top"><span class="pill {{ $catalog ? 'pill-ready' : 'pill-pending' }}">{{ $catalog ? 'Configurado' : 'Por configurar' }}</span><span class="muted">#{{ $category->cat_id }}</span></div>
            <h2>{{ $category->cat_nom }}</h2>
            <p>{{ $catalog ? ($catalog->tipo === 'cafeteria' ? 'Cafetería' : 'Fotocopiado') : 'Selecciona el servicio y revisa su lista inicial.' }}</p>
            <a class="text-link" href="{{ route('catalogos.show', $category->cat_id) }}">{{ $catalog ? 'Administrar productos' : 'Preparar catálogo' }} <span aria-hidden="true">→</span></a>
        </article>
    @empty
        <div class="empty-state">No hay convocatorias activas en la copia de datos.</div>
    @endforelse
</div>
@endsection
