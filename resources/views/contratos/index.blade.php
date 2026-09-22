@extends('layouts.app')
@push('head')<link rel="stylesheet" href="{{ asset('css/contratos.css') }}">@endpush
@section('title', 'Contratos de permisionarios')
@section('content')
<div class="page-heading contract-heading">
    <div><span class="eyebrow">CCyF · Unidad Jurídica e Igualdad de Género</span><h1>Permisionarios y propuestas aceptadas</h1><p>Revisa los datos de la designación, prepara el contrato y consulta el estado de entrega.</p></div>
    <a class="contract-outline" href="{{ route('contratos.template', $service) }}">Editar plantilla de {{ $service === 'cafeteria' ? 'cafetería' : 'fotocopiado' }} <span aria-hidden="true">↗</span></a>
</div>
<div class="contract-metrics" aria-label="Resumen del servicio">
    <div><small>Propuestas aceptadas</small><strong>{{ $service === 'cafeteria' ? $totalCafe : $totalFoto }}</strong><span>En este servicio</span></div>
    <div><small>Listos para envío</small><strong>{{ $ready }}</strong><span>Datos y plantilla revisados</span></div>
    <div><small>Contratos enviados</small><strong>{{ $sent }}</strong><span>Según historial disponible</span></div>
    <div><small>Plantilla</small><strong class="contract-metric-word">{{ $template ? 'Disponible' : 'Pendiente' }}</strong><span>{{ $template ? ($template->source === 'local' ? 'Versión editada en CCyF' : 'Versión original recuperada') : 'Configura el texto del contrato' }}</span></div>
</div>
@if(!$mailReady)<div class="contract-notice" role="note"><strong>Envío desactivado en esta copia local.</strong> El correo está configurado para pruebas. Puedes revisar las propuestas y abrir borradores PDF; los contratos no se marcarán como enviados.</div>@endif
@if($errors->any())<div class="form-errors" role="alert"><strong>Revisa los datos:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<section class="panel contract-panel">
    <nav class="tracking-tabs" aria-label="Tipo de servicio">
        <a href="{{ route('contratos.index', ['servicio'=>'fotocopiado']) }}" @class(['active' => $service === 'fotocopiado']) aria-current="{{ $service === 'fotocopiado' ? 'page' : 'false' }}">▤ Fotocopiado <strong>{{ $totalFoto }}</strong></a>
        <a href="{{ route('contratos.index', ['servicio'=>'cafeteria']) }}" @class(['active' => $service === 'cafeteria']) aria-current="{{ $service === 'cafeteria' ? 'page' : 'false' }}">☕ Cafetería <strong>{{ $totalCafe }}</strong></a>
    </nav>
    <div class="contract-panel-head"><div><span class="eyebrow">Expedientes designados</span><h2>{{ $service === 'cafeteria' ? 'Cafetería' : 'Fotocopiado' }}</h2><p>{{ $rows->total() }} {{ $rows->total() === 1 ? 'registro encontrado' : 'registros encontrados' }}</p></div><span class="contract-mini-badge">{{ $template ? 'Plantilla disponible' : 'Sin plantilla' }}</span></div>
    <form method="get" action="{{ route('contratos.index') }}" class="contract-filters">
        <input type="hidden" name="servicio" value="{{ $service }}">
        <div class="contract-search"><label for="contract-search">Buscar</label><input id="contract-search" type="search" name="buscar" maxlength="150" value="{{ request('buscar') }}" placeholder="Folio, nombre, correo o plantel"></div>
        <div><label for="contract-call">Convocatoria</label><select id="contract-call" name="convocatoria"><option value="">Todas las convocatorias</option>@foreach($calls as $call)<option value="{{ $call }}" @selected(request('convocatoria') === (string)$call)>{{ $call }}</option>@endforeach</select></div>
        <div><label for="contract-state">Estado</label><select id="contract-state" name="estado"><option value="">Todos</option><option value="listo" @selected(request('estado') === 'listo')>Listos</option><option value="incompleto" @selected(request('estado') === 'incompleto')>Requieren revisión</option><option value="enviado" @selected(request('estado') === 'enviado')>Enviados</option></select></div>
        <button class="button" type="submit">Filtrar</button><a class="contract-clear" href="{{ route('contratos.index', ['servicio'=>$service]) }}">Limpiar</a>
    </form>
    <form method="post" action="{{ route('contratos.send-bulk') }}" id="contract-bulk-form">
        @csrf
        <input type="hidden" name="servicio" value="{{ $service }}">
        <input type="hidden" name="convocatoria" value="{{ request('convocatoria') }}">
        <input type="hidden" name="estado" value="{{ request('estado') }}">
        <input type="hidden" name="buscar" value="{{ request('buscar') }}">
        <div class="contract-actionbar"><span>Selecciona hasta 20 contratos listos de esta página para enviarlos juntos.</span><button type="submit" class="contract-send" id="contract-bulk-button" disabled>Enviar seleccionados <strong id="contract-selected">0</strong></button></div>
        <div class="contract-table-wrap"><table class="contract-table"><thead><tr><th><input type="checkbox" id="contract-all" aria-label="Seleccionar todos los contratos listos de esta página" @disabled(!$mailReady || !$template)></th><th>Contrato</th><th>Permisionario</th><th>Plantel y convocatoria</th><th>Contacto</th><th>Vigencia</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
            @forelse($rows as $row)
                @php($missing = array_merge($proposals->missing($row, true), $contracts->issues($row)))
                @php($selectable = $mailReady && $template && !$row->sent && !$missing && !$row->delivery)
                <tr><td><input type="checkbox" name="keys[]" value="{{ $row->key }}" class="contract-select" aria-label="Seleccionar contrato de {{ $row->name }}" @disabled(!$selectable)></td>
                    <td><strong>{{ $row->folio }}</strong><small>{{ $row->origin === 'historico' ? 'Registro histórico' : 'Registro nuevo' }}</small></td>
                    <td><strong>{{ $row->name ?: 'Sin nombre' }}</strong><small>Expediente #{{ $row->id }}</small></td>
                    <td><strong>{{ $row->campus ?: 'Sin plantel' }}</strong><small>{{ $row->convocation ?: 'Sin convocatoria' }}</small></td>
                    <td><span>{{ $row->email ?: 'Sin correo' }}</span><small>{{ $row->phone ?: 'Sin teléfono' }}</small></td>
                    <td><span>{{ $row->starts ? \Carbon\Carbon::parse($row->starts)->format('d/m/Y') : '—' }}</span><small>al {{ $row->ends ? \Carbon\Carbon::parse($row->ends)->format('d/m/Y') : '—' }}</small></td>
                    <td>@if($row->sent)<span class="contract-status sent">Enviado</span><small>{{ $row->sent_at ? \Carbon\Carbon::parse($row->sent_at)->format('d/m/Y H:i') : 'Fecha no disponible' }}</small>@elseif($row->delivery)<span class="contract-status pending">Requiere revisión</span>@elseif($missing)<span class="contract-status pending">Requiere revisión</span><small>{{ implode(', ', array_slice($missing, 0, 2)) }}</small>@else<span class="contract-status ready">Listo para revisar</span>@endif</td>
                    <td><a class="contract-row-link" href="{{ route('contratos.show', $row->key) }}">Abrir expediente <span aria-hidden="true">→</span></a></td></tr>
            @empty<tr><td colspan="8" class="contract-empty">No hay propuestas aceptadas con estos filtros.</td></tr>@endforelse
        </tbody></table></div>
    </form>
    @if($rows->hasPages())<nav class="pagination contract-pagination" aria-label="Páginas de contratos">@if($rows->onFirstPage())<span>← Anterior</span>@else<a href="{{ $rows->previousPageUrl() }}">← Anterior</a>@endif<strong>Página {{ $rows->currentPage() }} de {{ $rows->lastPage() }}</strong>@if($rows->hasMorePages())<a href="{{ $rows->nextPageUrl() }}">Siguiente →</a>@else<span>Siguiente →</span>@endif</nav>@endif
</section>
@push('scripts')<script>
(() => {
 const checks=[...document.querySelectorAll('.contract-select:not(:disabled)')], all=document.getElementById('contract-all'), button=document.getElementById('contract-bulk-button'), count=document.getElementById('contract-selected');
 function sync(){const selected=checks.filter(x=>x.checked).length;count.textContent=selected;button.disabled=!selected;all.checked=checks.length>0&&selected===checks.length;all.indeterminate=selected>0&&selected<checks.length;}
 all?.addEventListener('change',()=>{checks.forEach((x,i)=>x.checked=all.checked&&i<20);sync();});
 checks.forEach(x=>x.addEventListener('change',()=>{if(checks.filter(y=>y.checked).length>20){x.checked=false;alert('Puedes enviar hasta 20 contratos por lote.');}sync();}));
 document.getElementById('contract-bulk-form')?.addEventListener('submit',e=>{if(!confirm('¿Enviar los contratos seleccionados a los correos indicados?'))e.preventDefault();});
})();
</script>@endpush
@endsection
