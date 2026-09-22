@extends('layouts.app')

@section('title', 'Precios de la propuesta')

@section('content')
<a class="back-link" href="{{ route('oficios.index') }}">← Nuevo oficio</a>
<div class="page-heading"><div><span class="eyebrow">{{ $catalog->tipo === 'cafeteria' ? 'Cafetería' : 'Fotocopiado' }} · Convocatoria #{{ $legacy->cat_id }}</span><h1>{{ $legacy->cat_nom }}</h1><p>Precios por producto de esta convocatoria.</p></div></div>
@if ($errors->any()) <div class="form-errors" role="alert"><strong>Revisa los precios:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
@unless ($canSave) <div class="info-strip">Vista previa del formulario. El guardado está disponible para las cuentas con permiso de Nuevo Oficio.</div> @endunless
<section class="panel price-panel"><div class="section-bar"><div><span class="eyebrow">Lista vigente</span><h2>Precios ofrecidos</h2></div><span class="pill pill-neutral">{{ $products->count() }} productos</span></div>
    @if ($products->isEmpty()) <div class="empty-state">Esta convocatoria no tiene productos activos. Pide que configuren el catálogo antes de capturar precios.</div>
    @else
        <form method="post" action="{{ route('oficios.save', $legacy->cat_id) }}">@csrf
            <div class="price-list">@foreach ($products as $product)
                <label class="price-row" for="price-{{ $product->id }}"><span><strong>{{ $product->nombre }}</strong><small>{{ $product->unidad ?: 'Precio unitario' }}</small></span><span class="price-input"><span>$</span><input id="price-{{ $product->id }}" name="precios[{{ $product->id }}]" type="number" inputmode="decimal" step="0.01" min="0" max="99999999.99" placeholder="0.00" value="{{ old('precios.'.$product->id, $saved->has($product->id) ? number_format((float) $saved->get($product->id), 2, '.', '') : '') }}" required @disabled(! $canSave)></span></label>
            @endforeach</div>
            @if ($canSave) <div class="form-footer"><p>Este guardado es un borrador de precios. El envío completo del oficio y sus documentos se incorporará en la siguiente etapa.</p><button class="button" type="submit">Guardar borrador de precios</button></div> @endif
        </form>
    @endif
</section>
@endsection
