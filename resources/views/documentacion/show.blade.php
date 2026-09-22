@extends('layouts.app')

@push('head')<link rel="stylesheet" href="{{ asset('css/documentacion.css') }}">@endpush
@section('title', 'Documentación del expediente')

@section('content')
<div class="doc-back"><a href="{{ route('documentacion.index', ['servicio'=>$record->service]) }}">← Volver a expedientes</a></div>
<div class="page-heading doc-heading"><div><span class="eyebrow">{{ $record->service_name }} · {{ $record->convocation }}</span><h1>Documentación del expediente {{ $record->registration_id }}</h1><p>{{ $record->name }} · {{ $record->campus }}</p></div><span class="pill pill-neutral">{{ $record->origin === 'historico' ? 'Expediente histórico' : 'Registro nuevo' }}</span></div>

<section class="doc-summary panel"><div><small>Permisionario</small><strong>{{ $record->name }}</strong></div><div><small>Correo</small><strong>{{ $record->email ?: '—' }}</strong></div><div><small>Servicio</small><strong>{{ $record->service_name }}</strong></div><div><small>Plantel</small><strong>{{ $record->campus ?: '—' }}</strong></div></section>

@if($errors->any())<div class="doc-errors" role="alert"><strong>Revisa los archivos antes de guardar.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<form method="post" action="{{ route('documentacion.update', $record->key) }}" enctype="multipart/form-data" class="doc-update-form" id="doc-update-form">@csrf
    <div class="doc-section-head"><div><span class="eyebrow">Archivos del expediente</span><h2>Actualizar documentación</h2><p>Selecciona únicamente los archivos que cambiarán. Se aceptan PDF, imágenes y documentos de Office de hasta 10 MB cada uno.</p></div><div class="doc-counter"><strong id="doc-selected-count">0</strong><span>archivos seleccionados</span></div></div>
    <div class="doc-file-grid">
        @foreach($fields as $field)
        @php($history = $versions->get($field->key, collect()))
        <article class="doc-file-card"><div class="doc-file-title"><span class="doc-file-icon">{{ $field->current ? '✓' : '+' }}</span><div><h3>{{ $field->name }}</h3><small>{{ $field->current ? ($field->updated ? 'Versión actualizada' : 'Archivo original') : 'Sin archivo' }}</small></div></div>
            @if($field->current)<a class="doc-current-link" href="{{ route('documentacion.file', ['key'=>$record->key,'field'=>$field->key]) }}" target="_blank" rel="noopener">Ver archivo actual <span aria-hidden="true">↗</span></a>@endif
            <label class="doc-upload-label" for="doc-file-{{ $loop->index }}">{{ $field->current ? 'Reemplazar con una nueva versión' : 'Agregar archivo' }}</label>
            <input id="doc-file-{{ $loop->index }}" name="files[{{ $field->key }}]" type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" data-doc-file>
            <small class="doc-file-picked" aria-live="polite">Ningún archivo seleccionado</small>
            @if($history->isNotEmpty())<details class="doc-history"><summary>Historial · {{ $history->count() + ($field->original ? 1 : 0) }} {{ $history->count() + ($field->original ? 1 : 0) === 1 ? 'versión' : 'versiones' }}</summary><ol>@foreach($history as $version)<li><a href="{{ route('documentacion.version', ['key'=>$record->key,'version'=>$version->id]) }}" target="_blank" rel="noopener">{{ $version->original_name }}</a><small>{{ \Illuminate\Support\Carbon::parse($version->created_at)->format('d/m/Y H:i') }} · {{ number_format($version->bytes / 1024, 0) }} KB · {{ $version->uploader ?: 'Usuario '.$version->uploaded_by }}</small></li>@endforeach @if($field->original)<li><a href="{{ route('documentacion.original', ['key'=>$record->key,'field'=>$field->key]) }}" target="_blank" rel="noopener">Archivo original</a><small>Versión inicial conservada</small></li>@endif</ol></details>@endif
        </article>
        @endforeach
    </div>
    <div class="doc-save-bar"><p><strong>Conservamos las versiones anteriores.</strong><br>{{ $record->origin === 'historico' ? 'Los archivos de la copia histórica no se modifican.' : 'El archivo inicial permanece disponible en el historial.' }}</p><button class="button" type="submit">Guardar {{ $fields->count() > 1 ? 'documentos' : 'documento' }}</button></div>
</form>
@push('scripts')<script>
(() => {
    const inputs = [...document.querySelectorAll('[data-doc-file]')];
    const count = document.getElementById('doc-selected-count');
    const refresh = () => { count.textContent = inputs.filter(input => input.files.length).length; };
    inputs.forEach(input => input.addEventListener('change', () => {
        const file = input.files[0];
        input.nextElementSibling.textContent = file ? `${file.name} · ${(file.size / 1048576).toFixed(1)} MB` : 'Ningún archivo seleccionado';
        refresh();
    }));
})();
</script>@endpush
@endsection
