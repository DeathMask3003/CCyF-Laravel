@extends('layouts.app')

@section('title', 'Nuevo registro')
@push('head')
<link rel="stylesheet" href="{{ asset('css/new-registration.css') }}?v=20260927-3">
<link rel="stylesheet" href="{{ asset('css/new-registration-submit.css') }}?v=20260928-1">
@endpush

@section('content')
<div class="newreg-page newreg-form-page" data-new-registration>
    <a class="back-link" href="{{ route('oficios.index') }}">← Volver a convocatorias</a>
    <header class="newreg-hero newreg-hero-form">
        <div class="newreg-hero-copy"><span class="newreg-kicker"><span class="newreg-kicker-dot"></span> Convocatoria {{ str_pad((string) $legacy->cat_id, 2, '0', STR_PAD_LEFT) }} · {{ $catalog->servicio_nombre }}</span><h1>{{ $legacy->cat_nom }}</h1><p>Prepara tu propuesta, registra los precios y reúne los documentos para participar. Cuando completes los precios podrás guardarlos como borrador antes de enviar.</p><div class="newreg-hero-meta"><span>{{ $campuses->count() }} {{ $campuses->count() === 1 ? 'plantel disponible' : 'planteles disponibles' }}</span><span>{{ $products->count() }} {{ $products->count() === 1 ? 'producto' : 'productos' }}</span><span>{{ $requiredDocuments->count() }} PDF solicitados</span></div></div>
        <div class="newreg-hero-seal" aria-hidden="true"><span>{{ str_pad((string) $legacy->cat_id, 2, '0', STR_PAD_LEFT) }}</span><small>VIGENTE</small></div>
    </header>

    @if ($errors->any()) <div class="form-errors newreg-errors" role="alert"><strong>Revisa estos datos antes de continuar:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
    @unless ($canSave) <div class="info-strip">Vista previa de administración. Los campos permanecen bloqueados porque esta cuenta no tiene el permiso Nuevo Registro.</div> @endunless

    <nav class="newreg-step-nav" aria-label="Secciones del registro"><a href="#registro-datos"><span>01</span><strong>Datos del registro</strong></a><a href="#registro-precios"><span>02</span><strong>Oferta económica</strong></a><a href="#registro-documentos"><span>03</span><strong>Documentación</strong></a><a href="#registro-envio"><span>04</span><strong>Envío</strong></a></nav>

    <form class="registration-form newreg-form" method="post" enctype="multipart/form-data" action="{{ route('oficios.submit', $legacy->cat_id) }}" data-registration-form>@csrf
        <fieldset @disabled(! $canSave)>
            <div class="newreg-layout">
                <div class="newreg-form-main">
                    <section id="registro-datos" class="panel newreg-panel"><div class="newreg-panel-head"><span class="newreg-panel-number">01</span><div><span class="eyebrow">Tu participación</span><h2>Datos del registro</h2><p>Selecciona el plantel y el tipo de invitación para esta propuesta.</p></div></div>
                        <div class="registration-fields newreg-fields"><div><label for="tipo-documento">Tipo de invitación <span class="newreg-required">*</span></label><select id="tipo-documento" name="tipo_documento_id" required><option value="">Selecciona un tipo</option>@foreach ($documentTypes as $type)<option value="{{ $type->id }}" @selected((string) old('tipo_documento_id', $draft?->tipo_documento_id) === (string) $type->id)>{{ $type->nombre }}</option>@endforeach</select></div><div><label for="plantel">Participa para <span class="newreg-required">*</span></label><select id="plantel" name="plantel_id" required><option value="">Selecciona un plantel</option>@foreach ($campuses as $campus)<option value="{{ $campus->id }}" @selected((string) old('plantel_id', $draft?->plantel_id) === (string) $campus->id) @disabled(in_array($campus->id, $submittedCampusIds, true))>{{ $campus->nombre }}{{ in_array($campus->id, $submittedCampusIds, true) ? ' · registro enviado' : '' }}</option>@endforeach</select><p class="field-help">Solo aparecen los planteles de esta convocatoria.</p></div><div><label>Servicio</label><div class="readonly-value">{{ $catalog->servicio_nombre }}</div></div><div><label>Dirigido a</label><div class="readonly-value">{{ $recipient }}</div></div><div class="full-field"><div class="newreg-label-line"><label for="comentarios">Comentarios de la propuesta <span class="newreg-required">*</span></label><span data-comments-count>0 / 5,000</span></div><textarea id="comentarios" name="comentarios" rows="4" maxlength="5000" required placeholder="Cuéntanos lo más importante de tu propuesta...">{{ old('comentarios') }}</textarea><p class="field-help">Puedes añadir una presentación breve o una aclaración relevante.</p></div></div>
                        @if ($campuses->isEmpty()) <div class="form-errors" role="alert">Esta convocatoria no tiene planteles activos enlazados.</div> @endif
                        @if ($documentTypes->isEmpty()) <div class="form-errors" role="alert">No hay tipos de documento activos.</div> @endif
                    </section>

                    <section id="registro-precios" class="panel newreg-panel"><div class="newreg-panel-head"><span class="newreg-panel-number">02</span><div><span class="eyebrow">Oferta económica</span><h2>Precios por producto</h2><p>Captura el precio unitario de cada concepto vigente.</p></div><span class="newreg-panel-count">{{ $products->count() }} productos</span></div>
                        @if ($products->isEmpty()) <div class="empty-state">Esta convocatoria no tiene productos activos.</div>
                        @else <div class="newreg-price-toolbar"><label for="buscar-producto">Buscar producto</label><div class="newreg-search"><span aria-hidden="true">⌕</span><input id="buscar-producto" type="search" placeholder="Escribe un nombre para localizarlo..." autocomplete="off" data-product-search><button type="button" aria-label="Limpiar búsqueda" data-clear-search hidden>×</button></div><span data-product-search-count>{{ $products->count() }} productos</span></div><div class="newreg-price-grid" data-price-list>@foreach ($products as $product)<label class="newreg-price-row" for="price-{{ $product->id }}" data-product-name="{{ mb_strtolower($product->nombre) }}"><span class="newreg-price-copy"><strong>{{ $product->nombre }}</strong><small>{{ $product->unidad ?: 'Precio unitario' }}</small></span><span class="newreg-price-input"><span>$</span><input id="price-{{ $product->id }}" name="precios[{{ $product->id }}]" type="number" inputmode="decimal" step="0.01" min="0" max="99999999.99" placeholder="0.00" value="{{ old('precios.'.$product->id, $saved->has($product->id) ? number_format((float) $saved->get($product->id), 2, '.', '') : '') }}" required></span><span class="newreg-price-tooltip" role="status" aria-live="polite" hidden><span>Precio capturado</span><strong data-price-confirmation></strong></span></label>@endforeach</div><p class="newreg-no-results" data-product-empty hidden>No hay productos con ese nombre. Prueba con otro término.</p>@endif
                    </section>

                    <section id="registro-documentos" class="panel newreg-panel"><div class="newreg-panel-head"><span class="newreg-panel-number">03</span><div><span class="eyebrow">Expediente digital</span><h2>Documentos PDF</h2><p>Adjunta un archivo PDF por requisito. Cada archivo puede pesar hasta 3 MB.</p></div><span class="newreg-panel-count">{{ $requiredDocuments->count() }} documentos</span></div>
                        @if ($requiredDocuments->isEmpty()) <div class="empty-state">Este servicio todavía no tiene requisitos documentales configurados.</div>
                        @else <div class="newreg-document-grid">@foreach ($requiredDocuments as $requirement)<label class="newreg-upload-card" for="doc-{{ $requirement->clave }}"><span class="newreg-upload-icon" aria-hidden="true">PDF</span><span class="newreg-upload-copy"><strong>{{ $requirement->nombre }}</strong><small data-file-label>Seleccionar PDF · máximo 3 MB</small></span><span class="newreg-upload-status" aria-hidden="true">＋</span><input id="doc-{{ $requirement->clave }}" name="documentos[{{ $requirement->clave }}]" type="file" accept="application/pdf,.pdf" @required($requirement->requerido)></label>@endforeach</div>@endif
                    </section>
                </div>

                <aside class="newreg-rail" aria-label="Resumen del registro"><div class="newreg-rail-card"><span class="eyebrow">En preparación</span><h2>Tu registro</h2><p>Revisa el avance antes de enviarlo.</p><div class="newreg-progress-track" role="progressbar" aria-label="Avance del registro" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-progress-track><span data-progress-fill></span></div><strong class="newreg-progress-label" data-progress-label>Comienza seleccionando un plantel</strong><dl class="newreg-rail-facts"><div><dt>Servicio</dt><dd>{{ $catalog->servicio_nombre }}</dd></div><div><dt>Plantel</dt><dd data-campus-summary>Por seleccionar</dd></div><div><dt>Precios capturados</dt><dd data-prices-summary>0 de {{ $products->count() }}</dd></div><div><dt>PDF adjuntos</dt><dd data-docs-summary>0 de {{ $requiredDocuments->count() }}</dd></div></dl><div class="newreg-rail-note"><span aria-hidden="true">✓</span><p>Al enviar recibirás un folio para consultar el estado de tu propuesta.</p></div></div></aside>
            </div>
        </fieldset>

        @if ($canSave)<div id="registro-envio" class="registration-actions newreg-actions"><div><span class="eyebrow">Último paso</span><strong>¿Todo listo para enviar?</strong><p>Verifica el plantel, los precios y cada PDF. También puedes guardar los precios como borrador.</p></div><x-turnstile action="submit_registration" /><button class="outline-button" type="submit" formaction="{{ route('oficios.save', $legacy->cat_id) }}" formnovalidate>Guardar borrador</button><button class="button" type="submit" @disabled($products->isEmpty() || $requiredDocuments->isEmpty() || $campuses->isEmpty() || $documentTypes->isEmpty())>Enviar registro <span aria-hidden="true">→</span></button></div>@endif
    </form>
    @if ($canSave)
        <dialog class="newreg-submit-dialog" data-submission-dialog aria-labelledby="newreg-submit-title" aria-describedby="newreg-submit-message">
            <div class="newreg-submit-spinner" aria-hidden="true"></div>
            <span class="newreg-submit-kicker">CCyF · Nuevo registro</span>
            <h2 id="newreg-submit-title" data-submission-title>Subiendo documentos</h2>
            <p id="newreg-submit-message" data-submission-message>Estamos preparando tu expediente.</p>
            <div class="newreg-submit-note" data-submission-note>Conserva esta ventana abierta hasta recibir tu folio.</div>
        </dialog>
    @endif
</div>
@push('scripts')<script src="{{ asset('js/new-registration.js') }}?v=20260928-1" defer></script>@endpush
@endsection
