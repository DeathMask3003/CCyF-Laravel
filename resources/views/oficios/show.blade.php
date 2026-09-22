@extends('layouts.app')

@section('title', 'Nuevo registro')

@section('content')
<a class="back-link" href="{{ route('oficios.index') }}">← Nuevo registro</a>
<div class="page-heading registration-heading"><div><span class="eyebrow">{{ $catalog->servicio_nombre }} · Convocatoria #{{ $legacy->cat_id }}</span><h1>{{ $legacy->cat_nom }}</h1><p>Completa la información, captura todos los precios y adjunta los documentos solicitados.</p></div><span class="pill pill-ready">Convocatoria activa</span></div>
@if ($errors->any()) <div class="form-errors" role="alert"><strong>Hay información pendiente por revisar:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
@unless ($canSave) <div class="info-strip">Vista previa de administración. Los campos permanecen bloqueados porque esta cuenta no tiene el permiso Nuevo Registro.</div> @endunless
<div class="registration-progress" aria-label="Etapas del registro"><div class="active"><span>1</span><strong>Datos</strong></div><div><span>2</span><strong>Precios</strong></div><div><span>3</span><strong>Documentos</strong></div><div><span>4</span><strong>Envío</strong></div></div>

<form class="registration-form" method="post" enctype="multipart/form-data" action="{{ route('oficios.submit', $legacy->cat_id) }}">@csrf
<fieldset @disabled(! $canSave)>
    <section class="panel registration-section"><div class="section-number">01</div><div class="section-bar"><div><span class="eyebrow">Información de la propuesta</span><h2>Datos del registro</h2><p class="muted">La convocatoria y el servicio ya están vinculados.</p></div></div>
        <div class="registration-fields"><div><label>Servicio</label><div class="readonly-value">{{ $catalog->servicio_nombre }}</div></div><div><label for="tipo-documento">Tipo de invitación</label><select id="tipo-documento" name="tipo_documento_id" required><option value="">Selecciona un tipo</option>@foreach ($documentTypes as $type)<option value="{{ $type->id }}" @selected((string) old('tipo_documento_id', $draft?->tipo_documento_id) === (string) $type->id)>{{ $type->nombre }}</option>@endforeach</select></div><div><label for="plantel">Participa para</label><select id="plantel" name="plantel_id" required><option value="">Selecciona un plantel</option>@foreach ($campuses as $campus)<option value="{{ $campus->id }}" @selected((string) old('plantel_id', $draft?->plantel_id) === (string) $campus->id) @disabled(in_array($campus->id, $submittedCampusIds, true))>{{ $campus->nombre }}{{ in_array($campus->id, $submittedCampusIds, true) ? ' · registro enviado' : '' }}</option>@endforeach</select><p class="field-help">Solo aparecen los planteles enlazados a esta convocatoria.</p></div><div><label>Dirigido a</label><div class="readonly-value">{{ $recipient }}</div></div><div class="full-field"><label for="comentarios">Comentarios de la propuesta</label><textarea id="comentarios" name="comentarios" rows="5" maxlength="5000" required placeholder="Escribe una presentación breve o cualquier aclaración relevante...">{{ old('comentarios') }}</textarea><p class="field-help">Texto simple, máximo 5,000 caracteres.</p></div></div>
        @if ($campuses->isEmpty()) <div class="form-errors" role="alert">Esta convocatoria no tiene planteles activos enlazados.</div> @endif
        @if ($documentTypes->isEmpty()) <div class="form-errors" role="alert">No hay tipos de documento activos.</div> @endif
    </section>

    <section class="panel registration-section"><div class="section-number">02</div><div class="section-bar"><div><span class="eyebrow">Oferta económica</span><h2>Precios por producto</h2><p class="muted">Captura el precio unitario de todos los conceptos vigentes.</p></div><span class="pill pill-neutral">{{ $products->count() }} productos</span></div>
        @if ($products->isEmpty()) <div class="empty-state">Esta convocatoria no tiene productos activos.</div>
        @else <div class="price-list">@foreach ($products as $product)<label class="price-row" for="price-{{ $product->id }}"><span><strong>{{ $product->nombre }}</strong><small>{{ $product->unidad ?: 'Precio unitario' }}</small></span><span class="price-input"><span>$</span><input id="price-{{ $product->id }}" name="precios[{{ $product->id }}]" type="number" inputmode="decimal" step="0.01" min="0" max="99999999.99" placeholder="0.00" value="{{ old('precios.'.$product->id, $saved->has($product->id) ? number_format((float) $saved->get($product->id), 2, '.', '') : '') }}" required></span></label>@endforeach</div> @endif
    </section>

    <section class="panel registration-section"><div class="section-number">03</div><div class="section-bar"><div><span class="eyebrow">Expediente digital</span><h2>Documentos PDF</h2><p class="muted">Cada archivo debe estar en formato PDF y pesar máximo 3 MB.</p></div><span class="pill pill-neutral">{{ $requiredDocuments->count() }} documentos</span></div>
        @if ($requiredDocuments->isEmpty()) <div class="empty-state">Este servicio todavía no tiene requisitos documentales configurados.</div>
        @else <div class="document-upload-grid">@foreach ($requiredDocuments as $requirement)<label class="upload-card" for="doc-{{ $requirement->clave }}"><span class="upload-number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><span class="upload-copy"><strong>{{ $requirement->nombre }}</strong><small>PDF · máximo 3 MB</small></span><input id="doc-{{ $requirement->clave }}" name="documentos[{{ $requirement->clave }}]" type="file" accept="application/pdf,.pdf" @required($requirement->requerido)><span class="file-name">Seleccionar archivo</span></label>@endforeach</div> @endif
    </section>
</fieldset>
@if ($canSave)<div class="registration-actions"><div><strong>Antes de enviar</strong><p>Verifica los precios y documentos. Al enviar recibirás un folio de confirmación.</p></div><button class="outline-button" type="submit" formaction="{{ route('oficios.save', $legacy->cat_id) }}" formnovalidate>Guardar borrador</button><button class="button" type="submit" @disabled($products->isEmpty() || $requiredDocuments->isEmpty() || $campuses->isEmpty() || $documentTypes->isEmpty())>Enviar registro</button></div>@endif
</form>
<script>document.querySelectorAll('.upload-card input').forEach(function(input){input.addEventListener('change',function(){const label=this.closest('.upload-card').querySelector('.file-name');label.textContent=this.files.length?this.files[0].name:'Seleccionar archivo';this.closest('.upload-card').classList.toggle('has-file',this.files.length>0);});});</script>
@endsection
