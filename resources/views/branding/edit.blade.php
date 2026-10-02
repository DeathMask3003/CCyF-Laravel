@extends('layouts.app')

@section('title', 'Identidad del portal')

@section('content')
<div class="page-heading identity-heading"><div><span class="eyebrow">Administración · CCyF</span><h1>Identidad del portal</h1><p>Administra la imagen, los textos y el documento destacado que verán los participantes.</p></div></div>
@if($errors->any())<div class="form-errors" role="alert">Revisa los campos marcados para guardar los cambios.</div>@endif
<div class="brand-editor">
    <section class="panel" aria-labelledby="brand-form-title">
        <h2 id="brand-form-title">Imagen y mensaje</h2>
        <p class="muted">El logo aparece encima del formulario de acceso y en el encabezado del sistema.</p>
        <form method="post" action="{{ route('branding.update') }}" enctype="multipart/form-data" data-branding-form data-current-logo="{{ $logoUrl }}" data-default-logo="{{ asset('images/ccyf-default.svg') }}">
            @csrf @method('PUT')
            <div><label for="brand-title">Título del portal</label><input id="brand-title" name="title" type="text" maxlength="120" value="{{ old('title', $branding->title) }}" required><small class="field-help">Se muestra junto al logo. Usa un nombre breve y reconocible.</small>@error('title')<span class="field-error" role="alert">{{ $message }}</span>@enderror</div>
            <div><label for="brand-motto">Lema principal</label><input id="brand-motto" name="motto" type="text" maxlength="160" value="{{ old('motto', $branding->motto) }}" required><small class="field-help">Aparece de forma destacada en la pantalla de acceso.</small>@error('motto')<span class="field-error" role="alert">{{ $message }}</span>@enderror</div>
            <div><label for="brand-logo">Logo o imagen institucional</label><div class="brand-upload-note"><img id="brand-upload-preview" src="{{ $logoUrl }}" alt="Vista previa del logo"><div><strong>Vista previa de la imagen</strong><small>PNG, JPG o WebP · máximo 4 MB · entre 80 × 80 y 4000 × 4000 píxeles.</small></div></div><input id="brand-logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" aria-describedby="brand-logo-help brand-logo-status"><small class="field-help" id="brand-logo-help">Al elegir una imagen, la verás aquí y en la tarjeta de acceso. El cambio se aplica al guardar.</small><div class="brand-file-status" id="brand-logo-status" role="status" aria-live="polite" data-state="neutral"><strong>Imagen actual</strong><small>Selecciona un archivo para comprobarlo antes de guardar.</small></div>@error('logo')<span class="field-error" role="alert">{{ $message }}</span>@enderror</div>
            @if($branding->logo_path)<label class="brand-remove" for="brand-remove"><input id="brand-remove" type="checkbox" name="remove_logo" value="1" @checked(old('remove_logo'))>Quitar el logo cargado y usar la marca predeterminada de CCyF</label>@endif
            <div class="brand-form-actions"><button class="button" type="submit" data-branding-submit>Guardar identidad</button></div>
        </form>
    </section>
    <aside class="panel brand-preview" aria-label="Vista previa del acceso">
        <div class="brand-preview-header"><img id="brand-preview-logo" src="{{ $logoUrl }}" alt=""><div><strong>CCyF</strong><small>COBAEM · Estado de México</small></div></div>
        <div class="brand-preview-body"><span>Vista previa</span><h2 id="brand-preview-motto">{{ old('motto', $branding->motto) }}</h2><p id="brand-preview-title">{{ old('title', $branding->title) }}</p></div>
    </aside>
</div>
<section class="panel brand-document-panel" aria-labelledby="brand-document-title">
    <div class="brand-document-intro"><div><span class="eyebrow">Inicio del permisionario</span><h2 id="brand-document-title">Documento destacado</h2><p>Publica un PDF para que los concursantes lo consulten desde una tarjeta en Inicio. Se abrirá en un visor de varias páginas, también en tabletas.</p></div><span class="pill {{ $documentAvailable ? 'pill-ready' : 'pill-neutral' }}">{{ $documentAvailable ? 'Publicado' : 'Sin documento' }}</span></div>
    <form method="post" action="{{ route('branding.document.update') }}" enctype="multipart/form-data" data-portal-document-form data-current-document="{{ $documentAvailable ? route('branding.document') : '' }}">
        @csrf @method('PUT')
        <div class="brand-document-fields">
            <div><label for="document-title">Título de la tarjeta</label><input id="document-title" name="document_title" type="text" maxlength="120" value="{{ old('document_title', $branding->document_title) }}" required><small class="field-help">Ejemplo: Guía para participar en la convocatoria.</small>@error('document_title')<span class="field-error" role="alert">{{ $message }}</span>@enderror</div>
            <div><label for="document-description">Descripción breve</label><input id="document-description" name="document_description" type="text" maxlength="240" value="{{ old('document_description', $branding->document_description) }}"><small class="field-help">Aparecerá debajo del título en Inicio.</small>@error('document_description')<span class="field-error" role="alert">{{ $message }}</span>@enderror</div>
        </div>
        <div class="brand-document-upload"><div><label for="document-file">Archivo PDF</label><input id="document-file" name="document" type="file" accept="application/pdf,.pdf" aria-describedby="document-file-status"><small class="field-help">Máximo 15 MB. Al reemplazarlo, el PDF anterior se elimina después de guardar.</small>@error('document')<span class="field-error" role="alert">{{ $message }}</span>@enderror</div><div class="brand-file-status" id="document-file-status" role="status" aria-live="polite" data-state="neutral"><strong>{{ $documentAvailable ? 'PDF publicado' : 'Aún no hay PDF publicado' }}</strong><small>{{ $documentAvailable ? 'Puedes consultarlo o elegir uno nuevo.' : 'Selecciona un archivo para mostrarlo a los permisionarios.' }}</small></div></div>
        <div class="brand-document-actions">@if($documentAvailable)<label class="brand-remove" for="remove-document"><input id="remove-document" type="checkbox" name="remove_document" value="1" @checked(old('remove_document'))>Retirar PDF del inicio</label>@endif<div><a id="document-preview-link" class="outline-button button-link" href="{{ $documentAvailable ? route('branding.document') : '#' }}" data-review-document data-document-name="{{ $branding->document_title }}" data-document-mime="application/pdf" @if(! $documentAvailable) hidden @endif>Vista previa PDF ↗</a><button class="button" type="submit" data-document-submit>Guardar documento</button></div></div>
    </form>
</section>
@include('revision.document-viewer', ['viewerEyebrow' => 'Documento para participantes'])
<script src="{{ asset('js/branding-preview.js') }}?v=20261002-1" defer></script>
@endsection
