@extends('layouts.app')

@push('head')<link rel="stylesheet" href="{{ asset('css/catalog-bulk.css') }}?v=20260923-1">@endpush

@section('title', 'Catálogo de productos')

@section('content')
<a class="back-link" href="{{ route('catalogos.index') }}">← Productos por convocatoria</a>
<div class="page-heading"><div><span class="eyebrow">Convocatoria #{{ $legacy->cat_id }}</span><h1>{{ $legacy->cat_nom }}</h1><p>Define los productos que verá quien capture una propuesta. Los productos retirados se conservan para consultar borradores previos.</p></div><span class="pill {{ $legacy->est ? 'pill-ready' : 'pill-pending' }}">{{ $legacy->est ? 'Vigente' : 'Cerrada' }}</span></div>
@if ($errors->any()) <div class="form-errors" role="alert"><strong>Revisa los datos:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
@if (! $catalog)
    <section class="panel narrow-panel"><span class="eyebrow">Paso inicial</span><h2>Preparar catálogo</h2><p class="muted">Empezaremos con los productos que ya utiliza CCyF. Podrás modificar, agregar o retirar cualquiera de ellos.</p>
        <form method="post" action="{{ route('catalogos.prepare', $legacy->cat_id) }}">@csrf
            <label>Tipo de servicio de esta convocatoria</label>
            <div class="readonly-value">{{ $service->nombre }}</div>
            <button class="button" type="submit">Crear catálogo</button>
        </form>
    </section>
@else
    <div class="section-bar"><div><span class="eyebrow">{{ $catalog->servicio_nombre ?: 'Tipo de servicio' }}</span><h2>Lista de productos</h2></div><span class="pill pill-neutral">{{ $products->where('activo', 1)->count() }} activos</span></div>
    @if($products->isNotEmpty())
        <form class="catalog-bulk-form" method="post" action="{{ route('catalogos.products.update-bulk', $legacy->cat_id) }}" data-catalog-form>@csrf @method('PUT')
            <input type="hidden" name="version" value="{{ $catalogVersion }}">
            <section class="panel"><div class="table-scroll"><table class="product-table"><thead><tr><th>Producto o servicio</th><th>Unidad</th><th>Orden</th><th>Visible</th></tr></thead><tbody>
                @foreach ($products as $product)
                    <tr @class(['inactive-row' => ! $product->activo]) data-catalog-row><td colspan="4"><div class="product-row">
                        <input name="products[{{ $product->id }}][nombre]" value="{{ old('products.'.$product->id.'.nombre', $product->nombre) }}" data-original="{{ $product->nombre }}" required maxlength="120" aria-label="Nombre del producto {{ $product->id }}">
                        <input name="products[{{ $product->id }}][unidad]" value="{{ old('products.'.$product->id.'.unidad', $product->unidad) }}" data-original="{{ $product->unidad }}" maxlength="40" aria-label="Unidad del producto {{ $product->id }}" placeholder="Por pieza">
                        <input name="products[{{ $product->id }}][orden]" type="number" min="1" max="999" value="{{ old('products.'.$product->id.'.orden', $product->orden) }}" data-original="{{ $product->orden }}" required aria-label="Orden del producto {{ $product->id }}">
                        <label class="switch-label"><input type="hidden" name="products[{{ $product->id }}][activo]" value="0"><input name="products[{{ $product->id }}][activo]" type="checkbox" value="1" data-original="{{ (int) $product->activo }}" @checked(old('products.'.$product->id.'.activo', $product->activo))> <span>{{ old('products.'.$product->id.'.activo', $product->activo) ? 'Sí' : 'No' }}</span></label>
                    </div></td></tr>
                @endforeach
            </tbody></table></div></section>
            <div class="catalog-save-bar"><p><strong data-change-count>Sin cambios pendientes</strong><span>Revisa los productos y guarda todos los cambios a la vez.</span></p><button class="button" type="submit">Guardar todos los productos</button></div>
        </form>
    @endif
    <section class="panel add-panel"><div><span class="eyebrow">Ampliar catálogo</span><h2>Agregar producto</h2><p class="muted">Aparecerá únicamente en esta convocatoria.</p></div><form class="add-form" method="post" action="{{ route('catalogos.products.store', $legacy->cat_id) }}">@csrf
        <div><label for="new-name">Nombre</label><input id="new-name" name="nombre" value="{{ old('nombre') }}" maxlength="120" placeholder="Ej. Ensalada de frutas" required></div>
        <div><label for="new-unit">Unidad</label><input id="new-unit" name="unidad" value="{{ old('unidad') }}" maxlength="40" placeholder="Ej. Porción"></div>
        <button class="button" type="submit">Agregar producto</button>
    </form></section>
    <a class="text-link" href="{{ route('oficios.show', $legacy->cat_id) }}">Ver formulario de precios <span aria-hidden="true">→</span></a>
@endif
@push('scripts')
<script>
(() => {
    const form = document.querySelector('[data-catalog-form]');
    if (!form) return;
    const rows = [...form.querySelectorAll('[data-catalog-row]')];
    const counter = form.querySelector('[data-change-count]');
    const update = () => {
        let changed = 0;
        rows.forEach(row => {
            const fields = [...row.querySelectorAll('[data-original]')];
            const dirty = fields.some(field => (field.type === 'checkbox' ? String(Number(field.checked)) : field.value.trim()) !== field.dataset.original);
            row.classList.toggle('catalog-row-changed', dirty);
            row.classList.toggle('inactive-row', !row.querySelector('input[type="checkbox"]').checked);
            row.querySelector('.switch-label span').textContent = row.querySelector('input[type="checkbox"]').checked ? 'Sí' : 'No';
            if (dirty) changed++;
        });
        if (counter) counter.textContent = changed ? `${changed} ${changed === 1 ? 'producto modificado' : 'productos modificados'}` : 'Sin cambios pendientes';
    };
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    update();
})();
</script>
@endpush
@endsection
