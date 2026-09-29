@extends('layouts.app')

@section('title', 'Identidad del portal')

@section('content')
<div class="page-heading identity-heading"><div><span class="eyebrow">Administración · CCyF</span><h1>Identidad del portal</h1><p>Define la imagen y los textos que verán los participantes al entrar a CCyF.</p></div></div>
@if($errors->any())<div class="form-errors" role="alert">Revisa los campos marcados para guardar los cambios.</div>@endif
<div class="brand-editor">
    <section class="panel" aria-labelledby="brand-form-title">
        <h2 id="brand-form-title">Imagen y mensaje</h2>
        <p class="muted">El logo aparece encima del formulario de acceso y en el encabezado del sistema.</p>
        <form method="post" action="{{ route('branding.update') }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div><label for="brand-title">Título del portal</label><input id="brand-title" name="title" type="text" maxlength="120" value="{{ old('title', $branding->title) }}" required><small class="field-help">Se muestra junto al logo. Usa un nombre breve y reconocible.</small>@error('title')<span class="field-error" role="alert">{{ $message }}</span>@enderror</div>
            <div><label for="brand-motto">Lema principal</label><input id="brand-motto" name="motto" type="text" maxlength="160" value="{{ old('motto', $branding->motto) }}" required><small class="field-help">Aparece de forma destacada en la pantalla de acceso.</small>@error('motto')<span class="field-error" role="alert">{{ $message }}</span>@enderror</div>
            <div><label for="brand-logo">Logo o imagen institucional</label><div class="brand-upload-note"><img id="brand-upload-preview" src="{{ $logoUrl }}" alt="Vista previa del logo"><div><strong>Selecciona una imagen</strong><small>PNG, JPG o WebP · hasta 4 MB · recomendado: fondo transparente y formato cuadrado.</small></div></div><input id="brand-logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" aria-describedby="brand-logo-help"><small class="field-help" id="brand-logo-help">Si no seleccionas otra imagen, se conserva el logo actual.</small>@error('logo')<span class="field-error" role="alert">{{ $message }}</span>@enderror</div>
            @if($branding->logo_path)<label class="brand-remove" for="brand-remove"><input id="brand-remove" type="checkbox" name="remove_logo" value="1" @checked(old('remove_logo'))>Quitar el logo cargado y usar la marca predeterminada de CCyF</label>@endif
            <div class="brand-form-actions"><button class="button" type="submit">Guardar identidad</button></div>
        </form>
    </section>
    <aside class="panel brand-preview" aria-label="Vista previa del acceso">
        <div class="brand-preview-header"><img id="brand-preview-logo" src="{{ $logoUrl }}" alt=""><div><strong>CCyF</strong><small>COBAEM · Estado de México</small></div></div>
        <div class="brand-preview-body"><span>Vista previa</span><h2 id="brand-preview-motto">{{ old('motto', $branding->motto) }}</h2><p id="brand-preview-title">{{ old('title', $branding->title) }}</p></div>
    </aside>
</div>
<script>
const titleInput = document.getElementById('brand-title');
const mottoInput = document.getElementById('brand-motto');
const fileInput = document.getElementById('brand-logo');
const uploadPreview = document.getElementById('brand-upload-preview');
const brandPreview = document.getElementById('brand-preview-logo');
const defaultPreview = uploadPreview.src;
let temporaryUrl;
titleInput.addEventListener('input', () => document.getElementById('brand-preview-title').textContent = titleInput.value);
mottoInput.addEventListener('input', () => document.getElementById('brand-preview-motto').textContent = mottoInput.value);
fileInput.addEventListener('change', () => {
    if (temporaryUrl) URL.revokeObjectURL(temporaryUrl);
    temporaryUrl = fileInput.files[0] ? URL.createObjectURL(fileInput.files[0]) : null;
    uploadPreview.src = brandPreview.src = temporaryUrl || defaultPreview;
});
</script>
@endsection
