@extends('layouts.app')

@section('title', 'Catálogo de productos')

@section('content')
<a class="back-link" href="{{ route('catalogos.index') }}">← Productos por convocatoria</a>
<div class="page-heading"><div><span class="eyebrow">Convocatoria #{{ $legacy->cat_id }}</span><h1>{{ $legacy->cat_nom }}</h1><p>Define los productos que verá quien capture una propuesta. Los productos retirados se conservan para consultar borradores previos.</p></div><span class="pill {{ $legacy->est ? 'pill-ready' : 'pill-pending' }}">{{ $legacy->est ? 'Vigente' : 'Cerrada' }}</span></div>
@if ($errors->any()) <div class="form-errors" role="alert"><strong>Revisa los datos:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
@if (! $catalog)
    <section class="panel narrow-panel"><span class="eyebrow">Paso inicial</span><h2>Preparar catálogo</h2><p class="muted">Empezaremos con los productos que ya utiliza CCyF. Podrás modificar, agregar o retirar cualquiera de ellos.</p>
        <form method="post" action="{{ route('catalogos.prepare', $legacy->cat_id) }}">@csrf
            <label for="tipo">Servicio de esta convocatoria</label>
            <select id="tipo" name="tipo" required><option value="">Selecciona el servicio</option><option value="cafeteria" @selected(old('tipo', $suggestedType) === 'cafeteria')>Cafetería</option><option value="fotocopiado" @selected(old('tipo', $suggestedType) === 'fotocopiado')>Fotocopiado</option></select>
            <button class="button" type="submit">Crear catálogo</button>
        </form>
    </section>
@else
    <div class="section-bar"><div><span class="eyebrow">{{ $catalog->tipo === 'cafeteria' ? 'Cafetería' : 'Fotocopiado' }}</span><h2>Lista de productos</h2></div><span class="pill pill-neutral">{{ $products->where('activo', 1)->count() }} activos</span></div>
    <section class="panel"><div class="table-scroll"><table class="product-table"><thead><tr><th>Producto o servicio</th><th>Unidad</th><th>Orden</th><th>Visible</th><th></th></tr></thead><tbody>
        @foreach ($products as $product)
            <tr @class(['inactive-row' => ! $product->activo])><td colspan="5"><form class="product-row" method="post" action="{{ route('catalogos.products.update', [$legacy->cat_id, $product->id]) }}">@csrf @method('PUT')
                <input name="nombre" value="{{ $product->nombre }}" required maxlength="120" aria-label="Nombre del producto {{ $product->id }}">
                <input name="unidad" value="{{ $product->unidad }}" maxlength="40" aria-label="Unidad del producto {{ $product->id }}" placeholder="Por pieza">
                <input name="orden" type="number" min="1" max="999" value="{{ $product->orden }}" required aria-label="Orden del producto {{ $product->id }}">
                <label class="switch-label"><input name="activo" type="checkbox" value="1" @checked($product->activo)> <span>{{ $product->activo ? 'Sí' : 'No' }}</span></label>
                <button class="small-button" type="submit">Guardar</button>
            </form></td></tr>
        @endforeach
    </tbody></table></div></section>
    <section class="panel add-panel"><div><span class="eyebrow">Ampliar catálogo</span><h2>Agregar producto</h2><p class="muted">Aparecerá únicamente en esta convocatoria.</p></div><form class="add-form" method="post" action="{{ route('catalogos.products.store', $legacy->cat_id) }}">@csrf
        <div><label for="new-name">Nombre</label><input id="new-name" name="nombre" value="{{ old('nombre') }}" maxlength="120" placeholder="Ej. Ensalada de frutas" required></div>
        <div><label for="new-unit">Unidad</label><input id="new-unit" name="unidad" value="{{ old('unidad') }}" maxlength="40" placeholder="Ej. Porción"></div>
        <button class="button" type="submit">Agregar producto</button>
    </form></section>
    <a class="text-link" href="{{ route('oficios.show', $legacy->cat_id) }}">Ver formulario de precios <span aria-hidden="true">→</span></a>
@endif
@endsection
