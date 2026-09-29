@extends('layouts.app')
@section('title', 'Plantilla de convocatoria')
@push('head')<link rel="stylesheet" href="{{ asset('css/emision-convocatorias.css') }}?v=13">@endpush
@section('content')
<div class="emision-shell">
    <a class="emision-back" href="{{ route('emision.index') }}">← Convocatorias</a>
    <section class="emision-hero emision-hero-compact"><div><span class="emision-kicker">Plantillas de convocatoria</span><h1>{{ $serviceRecord->nombre }}</h1><p>Define el texto y la presentación que recibirán las nuevas convocatorias de este servicio.</p></div></section>
    <div class="emision-tabs"><a @class(['active' => $service === 'cafeteria']) href="{{ route('emision.template', 'cafeteria') }}">Cafetería</a><a @class(['active' => $service === 'fotocopiado']) href="{{ route('emision.template', 'fotocopiado') }}">Fotocopiado</a></div>
    @if ($errors->any())<div class="emision-errors" role="alert"><strong>Revisa estos datos:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form id="emision-form" method="post" action="{{ route('emision.template.save', $service) }}">@csrf
        <section class="emision-panel">
            <div class="emision-panel-head"><div><span class="emision-kicker">Texto base editable</span><h2>Plantilla de {{ $serviceRecord->nombre }}</h2><p>Los documentos ya guardados conservan su propio texto y formato.</p></div><a class="emision-secondary" href="{{ route('emision.template', ['service' => $service, 'base' => 1]) }}">Cargar texto original</a></div>
            <div class="emision-font-controls"><div><label for="emision-font-family">Letra general del PDF</label><select id="emision-font-family" name="font_family" required>@foreach(\App\Services\ConvocationDocuments::FONT_FAMILIES as $value => $label)<option value="{{ $value }}" @selected(old('font_family', $template->font_family) === $value)>{{ $label }}</option>@endforeach<option disabled>Gotham Book · pendiente de archivo .ttf</option></select></div><div><label for="emision-font-size">Tamaño general (puntos)</label><input id="emision-font-size" name="font_size" type="number" min="7" max="14" step="0.5" required value="{{ old('font_size', $template->font_size) }}"></div></div>
            <p class="emision-hint">Predeterminado: DejaVu Sans, 9 puntos. Para habilitar Gotham Book en el PDF se necesita una versión TrueType compatible (.ttf).</p>
            <div class="emision-field"><label for="emision-details">Contenido de la convocatoria *</label><textarea id="emision-details" name="cuerpo_html" class="emision-tinymce" rows="24" data-tiny-base="{{ asset('vendor/tinymce') }}" data-upload-url="{{ route('emision.images.upload') }}" aria-label="Contenido de la convocatoria">{{ old('cuerpo_html', $template->cuerpo_html) }}</textarea><small>Da formato al texto seleccionado, inserta tablas e imágenes PNG/JPG de hasta 2 MB. El PDF conservará el formato compatible con la plantilla.</small></div>
        </section>
        <div class="emision-actions"><span>La plantilla guardada se aplicará a documentos nuevos.</span><div><a class="emision-secondary" href="{{ route('emision.index') }}">Cancelar</a><button class="emision-primary" type="submit">Guardar plantilla</button></div></div>
    </form>
</div>
@endsection
@push('scripts')<script src="{{ asset('vendor/tinymce/tinymce.min.js') }}?v=8.9.2" defer></script><script src="{{ asset('js/emision-tinymce.js') }}?v=3" defer></script><script src="{{ asset('js/emision-convocatorias.js') }}?v=14" defer></script>@endpush
