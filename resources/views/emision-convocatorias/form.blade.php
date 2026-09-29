@extends('layouts.app')
@section('title', $document ? 'Editar convocatoria' : 'Nueva convocatoria')
@push('head')<link rel="stylesheet" href="{{ asset('css/emision-convocatorias.css') }}?v=13">@endpush
@section('content')
@php
    $requested = old('planteles');
    $selectedIds = is_array($requested)
        ? collect($requested)->filter(fn ($row) => is_array($row) && !empty($row['seleccionado']))->keys()->map(fn ($id) => (int) $id)->all()
        : $snapshots->keys()->map(fn ($id) => (int) $id)->all();
    $serviceSlug = $call && str_contains(mb_strtolower(\Illuminate\Support\Str::ascii($call->servicio_nombre)), 'fotocopi') ? 'fotocopiado' : 'cafeteria';
@endphp
<div class="emision-shell">
    <a class="emision-back" href="{{ route('emision.index') }}">← Historial de convocatorias</a>
    <section class="emision-hero emision-hero-compact"><div><span class="emision-kicker">{{ $document ? 'Editar documento #'.$document->id : 'Nuevo documento' }}</span><h1>{{ $document ? 'Editar convocatoria' : 'Preparar convocatoria' }}</h1><p>Selecciona los planteles participantes, revisa las bases y genera la convocatoria.</p></div>@if ($document)<a class="emision-primary" href="{{ route('emision.pdf', $document->id) }}" target="_blank" rel="noopener">Abrir PDF actual ↗</a>@endif</section>
    @if ($errors->any())<div class="emision-errors" role="alert"><strong>Revisa estos datos:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if (! $call)
        <section class="emision-panel emision-empty"><h2>No hay convocatorias activas con servicio</h2><p>Configura un número de convocatoria y sus planteles para preparar el documento.</p><a class="emision-secondary" href="{{ route('convocatorias.index') }}">Ir a número de convocatoria</a></section>
    @else
        <section class="emision-panel"><div class="emision-step"><span>01</span><div><span class="emision-kicker">Identidad</span><h2>Número de convocatoria</h2></div></div>
            @if (! $document)<form id="call-switcher" method="get" action="{{ route('emision.create') }}"><label for="convocatoria-selector">Convocatoria activa</label><select id="convocatoria-selector" name="convocatoria" onchange="this.form.submit()">@foreach ($calls as $option)<option value="{{ $option->id }}" @selected($call->id == $option->id)>{{ $option->numero }} · {{ $option->servicio_nombre }}</option>@endforeach</select></form>@else<div class="emision-readonly"><span>Convocatoria</span><strong>{{ $call->numero }}</strong><small>{{ $call->servicio_nombre }}</small></div>@endif
        </section>
        <form id="emision-form" method="post" action="{{ $document ? route('emision.update', $document->id) : route('emision.store') }}">@csrf @if ($document) @method('PUT') @endif<input type="hidden" name="convocatoria_id" value="{{ $call->id }}">
            <section class="emision-panel"><div class="emision-step"><span>02</span><div><span class="emision-kicker">Participantes · Anexo I</span><h2>Planteles participantes</h2></div></div>
                <p class="emision-hint">Busca entre todos los planteles activos, selecciónalo en las sugerencias y agrégalo a esta convocatoria.</p>
                @if ($campuses->isEmpty())<div class="emision-no-campus">No hay planteles activos disponibles en el catálogo de áreas.</div>@endif
                <div class="emision-combobox"><label for="emision-campus-search">Buscar plantel</label><div class="emision-combobox-line"><div class="emision-combobox-input"><input id="emision-campus-search" type="search" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="emision-suggestions" autocomplete="off" placeholder="Escribe nombre o número del plantel"><div id="emision-suggestions" class="emision-suggestions" role="listbox" hidden></div></div><button id="emision-add-campus" class="emision-primary" type="button" disabled>+ Agregar plantel</button></div></div>
                <div class="emision-table-tools"><span><strong id="emision-count">{{ count($selectedIds) }}</strong> planteles agregados</span><div><label for="emision-bulk-date">Fecha para los agregados</label><input id="emision-bulk-date" type="date"><button id="emision-apply-date" class="emision-secondary" type="button">Aplicar</button></div></div>
                <div class="emision-table-wrap"><table class="emision-table"><thead><tr><th>Plantel</th><th>Dirección</th><th>Espacio *</th><th>Matrícula</th><th>Monto mensual *</th><th>Garantía</th><th>Fecha de inicio *</th><th>Acción</th></tr></thead><tbody id="emision-table-body">
                    @foreach ($campuses as $campus) @if (in_array((int) $campus->id, $selectedIds, true)) @include('emision-convocatorias.partials.campus-row', ['campus' => $campus, 'snapshot' => $snapshots->get($campus->id)]) @endif @endforeach
                    <tr id="emision-empty-row" @if(count($selectedIds)) hidden @endif><td colspan="8" class="emision-table-empty">Todavía no has agregado planteles. Búscalos arriba para formar el Anexo I.</td></tr>
                </tbody></table></div>
                <div id="emision-data-notice" class="emision-errors" role="status" hidden></div>
                @foreach ($campuses as $campus)<template id="emision-campus-row-{{ $campus->id }}">@include('emision-convocatorias.partials.campus-row', ['campus' => $campus, 'snapshot' => $snapshots->get($campus->id)])</template>@endforeach
                <script type="application/json" id="emision-campus-options">@json($campuses->map(fn ($campus) => ['id' => (int) $campus->id, 'nombre' => $campus->nombre])->values())</script>
            </section>
            <section class="emision-panel"><div class="emision-step"><span>03</span><div><span class="emision-kicker">Documento</span><h2>Plantilla y presentación</h2></div></div>
                <div class="emision-template-intro"><p>Se carga la plantilla de <strong>{{ $call->servicio_nombre }}</strong>. Puedes ajustar este documento sin cambiar la plantilla general.</p><a class="emision-secondary" href="{{ route('emision.template', $serviceSlug) }}" target="_blank" rel="noopener">Editar plantilla del servicio ↗</a></div>
                <div class="emision-field"><label for="emision-title">Título del documento *</label><input id="emision-title" name="titulo" maxlength="200" required value="{{ old('titulo', $document?->titulo ?? 'Convocatoria '.$call->numero.' · '.$call->servicio_nombre) }}"></div>
                <div class="emision-field"><label for="emision-signer">Nombre del firmante *</label><input id="emision-signer" name="firmante_nombre" maxlength="180" required value="{{ old('firmante_nombre', $document?->firmante_nombre) }}" placeholder="Nombre completo de quien firma la convocatoria"><small>Se mostrará debajo de la línea de firma en el PDF. Confirma el nombre antes de guardar.</small></div>
                <div class="emision-font-controls"><div><label for="emision-font-family">Letra general del PDF</label><select id="emision-font-family" name="font_family" required>@foreach (\App\Services\ConvocationDocuments::FONT_FAMILIES as $value => $label)<option value="{{ $value }}" @selected(old('font_family', $document?->font_family ?? $template->font_family) === $value)>{{ $label }}</option>@endforeach<option disabled>Gotham Book · pendiente de archivo .ttf</option></select></div><div><label for="emision-font-size">Tamaño general (puntos)</label><input id="emision-font-size" name="font_size" type="number" min="7" max="14" step="0.5" required value="{{ old('font_size', $document?->font_size ?? $template->font_size) }}"></div></div>
                <p class="emision-hint">El PDF conserva esta letra y tamaño aunque la plantilla general cambie. Gotham Book estará disponible cuando se incorpore un archivo .ttf compatible.</p>
                <div class="emision-field"><label for="emision-details">Contenido de la convocatoria *</label><textarea id="emision-details" name="detalles_html" class="emision-tinymce" rows="24" data-tiny-base="{{ asset('vendor/tinymce') }}" data-upload-url="{{ route('emision.images.upload') }}" aria-label="Contenido de la convocatoria">{{ old('detalles_html', $document?->detalles_html ?? $template->cuerpo_html) }}</textarea><small>Da formato al texto seleccionado, inserta tablas e imágenes PNG/JPG de hasta 2 MB. El PDF conservará el formato compatible con la plantilla.</small></div>
            </section>
            <div class="emision-actions"><span>Revisa el PDF antes de guardar. Los datos faltantes aparecerán como pendientes.</span><div><button class="emision-secondary" type="submit" formaction="{{ route('emision.preview-draft') }}" formtarget="_blank" formnovalidate>Vista previa PDF ↗</button><button class="emision-primary" type="submit">{{ $document ? 'Guardar cambios' : 'Guardar convocatoria' }}</button></div></div>
        </form>
    @endif
</div>
@endsection
@push('scripts')<script src="{{ asset('vendor/tinymce/tinymce.min.js') }}?v=8.9.2" defer></script><script src="{{ asset('js/emision-tinymce.js') }}?v=3" defer></script><script src="{{ asset('js/emision-convocatorias.js') }}?v=14" defer></script>@endpush
