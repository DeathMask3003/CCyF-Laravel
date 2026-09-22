@extends('layouts.app')

@push('head')<link rel="stylesheet" href="{{ asset('css/prevaluaciones.css') }}">@endpush

@section('title', 'Prevaluaciones')

@section('content')
<div class="page-heading review-heading">
    <div><span class="eyebrow">CCyF · Revisión documental</span><h1>Prevaluaciones</h1><p>Revisa documentos y precios antes de la evaluación definitiva.</p></div>
    <span class="pill pill-neutral">{{ $totalCafe + $totalFoto }} expedientes</span>
</div>

<section class="panel preval-panel">
    <nav class="tracking-tabs" aria-label="Tipo de servicio">
        <a href="{{ route('prevaluaciones.index', ['servicio'=>'cafeteria']) }}" @class(['active' => $service === 'cafeteria']) aria-current="{{ $service === 'cafeteria' ? 'page' : 'false' }}">☕ Cafetería <strong>{{ $totalCafe }}</strong></a>
        <a href="{{ route('prevaluaciones.index', ['servicio'=>'fotocopiado']) }}" @class(['active' => $service === 'fotocopiado']) aria-current="{{ $service === 'fotocopiado' ? 'page' : 'false' }}">▤ Fotocopiado <strong>{{ $totalFoto }}</strong></a>
    </nav>

    <div class="preval-intro"><div><span class="eyebrow">{{ $service === 'cafeteria' ? 'Servicio de cafetería' : 'Servicio de fotocopiado' }}</span><h2>{{ $service === 'cafeteria' ? 'Cafetería' : 'Fotocopiado' }}</h2></div><span class="muted">{{ $rows->total() }} resultados</span></div>
    <form class="preval-filters" method="get" action="{{ route('prevaluaciones.index') }}">
        <input type="hidden" name="servicio" value="{{ $service }}">
        <div><label for="preval-convocatoria">Convocatoria</label><select id="preval-convocatoria" name="convocatoria"><option value="">Todas</option>@foreach($convocations as $convocation)<option value="{{ $convocation->convocatoria_id }}" @selected(request('convocatoria') == $convocation->convocatoria_id)>{{ $convocation->convocatoria }}</option>@endforeach</select></div>
        <div><label for="preval-plantel">Plantel</label><select id="preval-plantel" name="plantel"><option value="">Todos los planteles</option>@foreach($campuses as $campus)<option value="{{ $campus }}" @selected(request('plantel') === $campus)>{{ $campus }}</option>@endforeach</select></div>
        <div><label for="preval-estado">Seguimiento</label><select id="preval-estado" name="estado"><option value="">Todos</option><option value="pendiente" @selected(request('estado') === 'pendiente')>Pendientes</option><option value="evaluado" @selected(request('estado') === 'evaluado')>Prevaluados</option></select></div>
        <div class="preval-search"><label for="preval-buscar">Buscar</label><input id="preval-buscar" type="search" name="buscar" maxlength="150" value="{{ request('buscar') }}" placeholder="Nombre, CURP, plantel…"></div>
        <button class="button" type="submit">Buscar</button><a class="outline-button button-link" href="{{ route('prevaluaciones.index', ['servicio'=>$service]) }}">Limpiar</a>
    </form>

    <div class="preval-table-wrap"><table class="preval-table"><thead><tr><th>Permisionario</th><th>CURP</th><th>Concurso para</th><th>Teléfono</th><th>Acción</th></tr></thead><tbody>
        @forelse($rows as $row)
            <tr><td><strong>{{ $row->nombre ?: 'Sin nombre' }}</strong><small>{{ $row->convocatoria }} · {{ $row->origen === 'historico' ? 'Histórico' : 'Nuevo' }}</small></td>
                <td>{{ mb_strtoupper((string) $row->curp) ?: '—' }}</td><td>{{ $row->plantel ?: 'Sin plantel' }}</td><td>{{ $row->telefono ?: '—' }}</td>
                <td><div class="preval-table-action"><span @class(['preval-state', 'reviewed' => $row->evaluado])>{{ $row->evaluado ? (match((int) $row->resultado) {1=>'Viable',2=>'Probable',3=>'No viable',default=>'Prevaluado'}) : 'Pendiente' }}</span><a class="button button-link" href="{{ route('prevaluaciones.index', array_merge(request()->except('registro'), ['servicio'=>$service,'registro'=>$row->key])) }}">Revisar expediente</a></div></td></tr>
        @empty
            <tr><td colspan="5" class="tracking-empty">No hay expedientes con estos filtros.</td></tr>
        @endforelse
    </tbody></table></div>
    @if($rows->hasPages())<nav class="pagination" aria-label="Páginas de expedientes">@if($rows->onFirstPage())<span>← Anterior</span>@else<a href="{{ $rows->previousPageUrl() }}">← Anterior</a>@endif<strong>Página {{ $rows->currentPage() }} de {{ $rows->lastPage() }}</strong>@if($rows->hasMorePages())<a href="{{ $rows->nextPageUrl() }}">Siguiente →</a>@else<span>Siguiente →</span>@endif</nav>@endif
</section>

@if($selected)
<dialog id="preval-dialog" class="preval-dialog" aria-labelledby="preval-dialog-title" data-open="true">
    <div class="preval-modal">
        <header class="preval-modal-head"><div><span class="preval-modal-kicker">{{ $selected->servicio === 'cafeteria' ? '☕ Cafetería' : '▤ Fotocopiado' }} · {{ $selected->convocatoria }}</span><h2 id="preval-dialog-title">{{ $selected->nombre ?: 'Permisionario' }}</h2><p>{{ $selected->plantel }} · Expediente {{ $selected->registro_id }}</p></div><button type="button" class="preval-dialog-close" aria-label="Cerrar expediente" data-close-preval>×</button></header>
        <div class="preval-modal-body">
            <aside class="preval-docs" aria-label="Documentos enviados"><div class="preval-section-head"><span class="eyebrow">01 · Documentación</span><h3>Documentos enviados</h3><small>{{ $detail['documents']->where('available', true)->count() }} de {{ $detail['documents']->count() }} disponibles</small></div>
                <div class="preval-doc-list">@foreach($detail['documents'] as $document)
                    @if($document->available)<a class="preval-doc-link" href="{{ route('prevaluaciones.file', ['key'=>$selected->key,'field'=>$document->clave]) }}" target="_blank" rel="noopener" data-preval-pdf data-pdf-name="{{ $document->nombre }}"><span class="preval-pdf-icon">PDF</span><span>{{ $document->nombre }}</span><span aria-hidden="true">↗</span></a>
                    @else<span class="preval-doc-missing"><span class="preval-pdf-icon">—</span><span>{{ $document->nombre }}</span><small>Sin archivo</small></span>@endif
                @endforeach</div>
            </aside>

            <section class="preval-viewer" aria-label="Visualizador PDF"><div class="preval-viewer-head"><div><span class="eyebrow">02 · Visualización</span><h3 id="preval-pdf-name">Selecciona un documento</h3></div><a id="preval-pdf-open" class="preval-open-link" href="#" target="_blank" rel="noopener" hidden>Abrir PDF ↗</a></div>
                <div id="preval-pdf-empty" class="preval-pdf-empty"><span aria-hidden="true">▤</span><strong>Selecciona un documento</strong><p>El PDF aparecerá aquí. En iPad también puedes abrirlo en una pestaña para usar el visor del dispositivo.</p></div>
                <div id="preval-pdf-error" class="preval-pdf-error" role="alert" hidden>No fue posible mostrar este PDF aquí. Usa «Abrir PDF» para verlo en el visor del dispositivo.</div>
                <div id="preval-pdf-stage" class="preval-pdf-stage" hidden><div id="preval-pdf-canvas-wrap" class="preval-pdf-canvas-wrap"><canvas id="preval-pdf-canvas" aria-label="Página del documento PDF"></canvas></div><div class="preval-pdf-toolbar"><button type="button" id="preval-page-prev" aria-label="Página anterior">←</button><span id="preval-page-count">Página 1 de 1</span><button type="button" id="preval-page-next" aria-label="Página siguiente">→</button><span class="preval-toolbar-separator"></span><button type="button" id="preval-zoom-out" aria-label="Reducir zoom">−</button><span id="preval-zoom-label">100 %</span><button type="button" id="preval-zoom-in" aria-label="Aumentar zoom">+</button></div></div>
            </section>

            <section class="preval-checklist" aria-label="Prevaluación"><div class="preval-section-head"><span class="eyebrow">03 · Dictamen</span><h3>Revisión documental</h3><small>{{ $canEvaluate ? 'Puedes guardar esta prevaluación' : ($isAdmin ? 'Vista del administrador' : 'Consulta de evaluación') }}</small></div>
                @if($errors->any())<div class="tracking-errors" role="alert"><strong>Revisa el formulario.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                @if($detail['owner'] && ! $canEvaluate && ! $isAdmin)<div class="preval-owner">Este expediente ya fue tomado por otro prevaluador. Puedes consultar los documentos y el dictamen.</div>@endif
                <form method="post" action="{{ route('prevaluaciones.save', $selected->key) }}" class="preval-evaluation-form">@csrf @method('put')
                    @foreach(request()->only(['servicio','convocatoria','plantel','buscar','estado','page']) as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
                    <div class="preval-checklist-scroll"><h4>Documentos</h4>
                    @foreach($detail['documents'] as $document)
                        @php($item = $detail['items']->get($document->clave))
                        <fieldset class="preval-criterion"><legend>{{ $document->nombre }}</legend><span @class(['preval-file-status', 'missing' => ! $document->available])>{{ $document->available ? 'PDF disponible' : 'Sin archivo' }}</span><div class="preval-choice"><label><input type="radio" name="items[{{ $document->clave }}][cumple]" value="1" @checked(old('items.'.$document->clave.'.cumple', $item?->cumple) !== null && (string) old('items.'.$document->clave.'.cumple', $item?->cumple) === '1') @disabled(! $canEvaluate)> Cumple</label><label><input type="radio" name="items[{{ $document->clave }}][cumple]" value="0" @checked(old('items.'.$document->clave.'.cumple', $item?->cumple) !== null && (string) old('items.'.$document->clave.'.cumple', $item?->cumple) === '0') @disabled(! $canEvaluate)> No cumple</label></div><input type="text" name="items[{{ $document->clave }}][comentario]" maxlength="1000" placeholder="Comentario sobre este documento" value="{{ old('items.'.$document->clave.'.comentario', $item?->comentario) }}" @disabled(! $canEvaluate)></fieldset>
                    @endforeach
                    @if($detail['prices']->isNotEmpty())<h4>Precios propuestos</h4><p class="preval-help">Los mínimos corresponden al mismo plantel, convocatoria y servicio.</p>
                        @foreach($detail['prices'] as $price)
                            @php($item = $detail['items']->get($price->clave))
                            <fieldset class="preval-criterion"><legend>{{ $price->nombre }} <span class="preval-price">${{ number_format((float) $price->precio, 2) }}</span></legend><span class="preval-file-status">Menor propuesta: ${{ number_format((float) ($comparison[$price->nombre] ?? $price->precio), 2) }}</span><div class="preval-choice"><label><input type="radio" name="items[{{ $price->clave }}][cumple]" value="1" @checked((string) old('items.'.$price->clave.'.cumple', $item?->cumple) === '1') @disabled(! $canEvaluate)> Cumple</label><label><input type="radio" name="items[{{ $price->clave }}][cumple]" value="0" @checked($item?->cumple !== null && (string) old('items.'.$price->clave.'.cumple', $item?->cumple) === '0') @disabled(! $canEvaluate)> No cumple</label></div><input type="text" name="items[{{ $price->clave }}][comentario]" maxlength="1000" placeholder="Comentario sobre el precio" value="{{ old('items.'.$price->clave.'.comentario', $item?->comentario) }}" @disabled(! $canEvaluate)></fieldset>
                        @endforeach
                    @endif</div>
                    <div class="preval-decision"><label for="preval-result">Resultado de la prevaluación</label><select id="preval-result" name="resultado" @disabled(! $canEvaluate)><option value="">Sin dictamen aún</option><option value="1" @selected(old('resultado', $detail['result']) == 1)>Viable</option><option value="2" @selected(old('resultado', $detail['result']) == 2)>Probable</option><option value="3" @selected(old('resultado', $detail['result']) == 3)>No viable</option></select>@if($canEvaluate)<button type="submit" class="button">Guardar prevaluación</button>@endif</div>
                </form>
                @if($isAdmin)<form method="post" action="{{ route('prevaluaciones.note', $selected->key) }}" class="preval-admin-form">@csrf @method('put')@foreach(request()->only(['servicio','convocatoria','plantel','buscar','estado','page']) as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach<label for="preval-admin-note">Observaciones del administrador</label><textarea id="preval-admin-note" name="observaciones" rows="3" maxlength="3000" required placeholder="Registra las observaciones finales del expediente">{{ old('observaciones', $detail['adminNote']) }}</textarea><button type="submit" class="outline-button">Guardar observaciones</button></form>
                @elseif($detail['adminNote'])<div class="preval-admin-readonly"><strong>Observaciones del administrador</strong><p>{{ $detail['adminNote'] }}</p></div>@endif
            </section>
        </div>
    </div>
</dialog>
@endif
@push('scripts')
<script>
(() => {
    const dialog = document.getElementById('preval-dialog');
    if (!dialog) return;
    dialog.showModal();
    dialog.querySelector('[data-close-preval]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
        const url = new URL(window.location.href);
        url.searchParams.delete('registro');
        history.replaceState(null, '', url);
    });
})();
</script>
<script type="module" src="{{ asset('js/prevaluation-pdf.js') }}?v=20260922-3"></script>
@endpush
@endsection
