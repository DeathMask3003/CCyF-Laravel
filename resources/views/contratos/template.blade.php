@extends('layouts.app')
@push('head')<link rel="stylesheet" href="{{ asset('css/contratos.css') }}">@endpush
@section('title', 'Plantilla de contrato')
@section('content')
<a class="back-link" href="{{ route('contratos.index', ['servicio'=>$service]) }}">← Volver a contratos</a>
<div class="page-heading contract-heading"><div><span class="eyebrow">CCyF · Plantilla jurídica</span><h1>Contrato de {{ $service === 'cafeteria' ? 'cafetería' : 'fotocopiado' }}</h1><p>Edita el texto y conserva cada versión. Los campos entre llaves se llenan con los datos del expediente.</p></div><span class="contract-mini-badge">{{ $current ? ($current->source === 'local' ? 'Versión local' : 'Plantilla original') : 'Sin plantilla' }}</span></div>
@if($errors->any())<div class="form-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="contract-editor-layout"><form class="panel contract-editor-card" method="post" action="{{ route('contratos.template.save', $service) }}">@csrf<div class="contract-editor-head"><div><span class="eyebrow">Texto del documento</span><h2>Editar plantilla</h2></div><span>{{ $current ? 'Último cambio: '.$current->updated_at : 'Nueva plantilla' }}</span></div>
    @if($viewingVersion)<div class="contract-version-banner">Estás consultando la versión #{{ $viewingVersion->id }}. Si guardas, se creará una nueva versión sin borrar las anteriores. <a href="{{ route('contratos.template', $service) }}">Volver a la versión vigente</a></div>@endif
    <p class="contract-help">Guarda una nueva versión cuando termines. Revisa después el PDF de un expediente antes de enviarlo.</p>
    <div class="contract-type-controls"><div><label for="contract-font-family">Letra del PDF</label><select id="contract-font-family" name="font_family" required>@foreach($fontFamilies as $value => $label)<option value="{{ $value }}" @selected(old('font_family', $editor?->font_family ?? 'dejavusans') === $value)>{{ $label }}</option>@endforeach<option disabled>Gotham Book · requiere archivo TrueType compatible</option></select></div><div><label for="contract-font-size">Tamaño (puntos)</label><input id="contract-font-size" name="font_size" type="number" min="7" max="14" step="0.5" required value="{{ old('font_size', $editor?->font_size ?? 9) }}"></div></div>
    <p class="contract-help">El tamaño inicial es 9 puntos. Cambia la fuente y revisa el borrador PDF. Gotham Book instalada en este equipo no es compatible con el generador del servidor; se requiere su versión TrueType para habilitarla.</p>
    <label id="contract-body-label" for="contract-rich-editor">Contenido del contrato</label>
    <div class="contract-editor-toolbar" role="toolbar" aria-label="Formato del contrato"><button type="button" data-format="bold" title="Negritas"><strong>N</strong></button><button type="button" data-format="italic" title="Cursivas"><em>C</em></button><button type="button" data-format="underline" title="Subrayado"><u>S</u></button><button type="button" data-format="formatBlock" data-value="h2" title="Título">Título</button><button type="button" data-format="insertUnorderedList" title="Lista">• Lista</button></div>
    <div id="contract-rich-editor" class="contract-rich-editor" contenteditable="true" role="textbox" aria-labelledby="contract-body-label" aria-multiline="true" spellcheck="true">{!! $editorHtml !!}</div>
    <textarea id="contract-body" name="body" hidden>{{ old('body', $editorHtml) }}</textarea>
    <details id="contract-source-details" class="contract-source-details"><summary>Edición avanzada: ver HTML</summary><p>Si modificas el HTML, se guardará ese contenido. Vuelve a abrir el editor para revisar la versión visual.</p><textarea id="contract-source" spellcheck="false"></textarea></details>
    <div class="contract-signer-head"><span class="eyebrow">Firmas del documento</span><h3>Personas que suscriben</h3><p>Verifica los cargos vigentes antes de guardar. Estos nombres aparecerán en el PDF.</p></div>
    <div class="contract-signer-fields"><div><label for="institution-signer">Representante del COBAEM</label><input id="institution-signer" name="institution_signer" maxlength="180" required value="{{ old('institution_signer', $editor?->institution_signer) }}"></div>
        <div><label for="institution-role">Cargo</label><input id="institution-role" name="institution_role" maxlength="180" required value="{{ old('institution_role', $editor?->institution_role) }}"></div>
        <div><label for="witness-signer">Testigo</label><input id="witness-signer" name="witness_signer" maxlength="180" required value="{{ old('witness_signer', $editor?->witness_signer) }}"></div>
        <div><label for="witness-role">Cargo del testigo</label><input id="witness-role" name="witness_role" maxlength="180" required value="{{ old('witness_role', $editor?->witness_role) }}"></div></div>
    <div class="contract-review-confirm"><label><input type="checkbox" name="confirmacion" value="1" required> Revisé el contenido jurídico, los nombres de firmantes, las fechas fijas y los datos bancarios; eliminé los textos pendientes con asteriscos.</label></div>
    <div class="contract-editor-footer"><span>Campos obligatorios: {permisionario}, {plantel}, {monto}, {fecha_ini}, {fecha_fin}.</span><button class="button" type="submit">Guardar versión revisada</button></div>
</form><aside class="contract-editor-aside"><section class="panel"><span class="eyebrow">Ayuda de edición</span><h2>Campos disponibles</h2><p>Selecciona un campo para insertarlo donde esté el cursor.</p><div class="contract-variables">@foreach($variables as $key => $label)<button type="button" data-token="{{ '{'.$key.'}' }}" title="Insertar {{ $label }}">{{ $label }} <code>{{ '{'.$key.'}' }}</code></button>@endforeach</div></section>
    <section class="panel"><span class="eyebrow">Historial</span><h2>Versiones guardadas</h2>@if($versions->isEmpty())<p>Aún se usa la plantilla original. Al guardar se crea la primera versión local.</p>@else<ol class="contract-versions">@foreach($versions as $version)<li><a href="{{ route('contratos.template', ['service'=>$service,'version'=>$version->id]) }}">Versión #{{ $version->id }} ↗</a><small>{{ $version->editor ?: 'Usuario' }} · {{ \Carbon\Carbon::parse($version->created_at)->format('d/m/Y H:i') }}</small></li>@endforeach</ol>@endif</section></aside></div>
@push('scripts')<script>
(() => {
    const rich=document.getElementById('contract-rich-editor'), body=document.getElementById('contract-body'), details=document.getElementById('contract-source-details'), source=document.getElementById('contract-source'), font=document.getElementById('contract-font-family'), size=document.getElementById('contract-font-size');
    const previewType=()=>{rich.style.fontFamily=font.value==='dejavuserif'?'Georgia, serif':'Arial, sans-serif';rich.style.fontSize=`${size.value||9}pt`;};
    font.addEventListener('change',previewType);size.addEventListener('input',previewType);previewType();
    let sourceDirty=false;
    details.addEventListener('toggle',()=>{if(details.open&&!sourceDirty)source.value=rich.innerHTML;});
    source.addEventListener('input',()=>{sourceDirty=true;});
    rich.addEventListener('input',()=>{sourceDirty=false;});
    document.querySelectorAll('[data-format]').forEach(button=>{button.addEventListener('mousedown',event=>event.preventDefault());button.addEventListener('click',()=>{rich.focus();document.execCommand(button.dataset.format,false,button.dataset.value||null);});});
    document.querySelectorAll('[data-token]').forEach(button=>button.addEventListener('click',()=>{const token=button.dataset.token;if(details.open){const start=source.selectionStart,end=source.selectionEnd;source.setRangeText(token,start,end,'end');source.focus();}else{rich.focus();document.execCommand('insertText',false,token);}}));
    document.querySelector('.contract-editor-card').addEventListener('submit',()=>{body.value=sourceDirty?source.value:rich.innerHTML;});
})();
</script>@endpush
@endsection
