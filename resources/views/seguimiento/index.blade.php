@extends('layouts.app')

@section('title', 'Seguimiento de permisionarios')

@section('content')
<div class="page-heading review-heading">
    <div><span class="eyebrow">CCyF · Permisos designados</span><h1>Seguimiento de permisionarios</h1><p>Consulta contratos, captura el seguimiento y administra sus documentos.</p></div>
    <span class="pill pill-neutral">{{ $totalCafe + $totalFoto }} registros</span>
</div>

<section class="panel tracking-panel">
    <nav class="tracking-tabs" aria-label="Tipo de servicio">
        <a href="{{ route('seguimiento.index', ['servicio' => 'cafeteria']) }}" @class(['active' => $service === 'cafeteria']) aria-current="{{ $service === 'cafeteria' ? 'page' : 'false' }}"><span aria-hidden="true">☕</span> Cafetería <strong>{{ $totalCafe }}</strong></a>
        <a href="{{ route('seguimiento.index', ['servicio' => 'fotocopiado']) }}" @class(['active' => $service === 'fotocopiado']) aria-current="{{ $service === 'fotocopiado' ? 'page' : 'false' }}"><span aria-hidden="true">▤</span> Fotocopiado <strong>{{ $totalFoto }}</strong></a>
    </nav>

    <div class="tracking-topline"><div><span class="eyebrow">{{ $service === 'cafeteria' ? 'Servicio de cafetería' : 'Servicio de fotocopiado' }}</span><h2>{{ $service === 'cafeteria' ? 'Cafetería' : 'Fotocopiado' }}</h2></div><span class="muted">{{ $rows->total() }} resultados</span></div>

    <form method="get" action="{{ route('seguimiento.index') }}" class="tracking-filters">
        <input type="hidden" name="servicio" value="{{ $service }}">
        <div class="tracking-search"><label for="tracking-search">Buscar</label><input id="tracking-search" name="buscar" type="search" value="{{ request('buscar') }}" placeholder="Permisionario, plantel, CURP, convocatoria…" maxlength="150"></div>
        <div><label for="tracking-start">Mes de inicio</label><input id="tracking-start" name="mes_inicio" type="month" value="{{ request('mes_inicio') }}"></div>
        <div><label for="tracking-end">Mes de finalización</label><input id="tracking-end" name="mes_fin" type="month" value="{{ request('mes_fin') }}"></div>
        <div><label for="tracking-size">Mostrar</label><select id="tracking-size" name="por_pagina"><option value="10" @selected(request('por_pagina') == '10')>10</option><option value="25" @selected(request('por_pagina', '25') == '25')>25</option><option value="50" @selected(request('por_pagina') == '50')>50</option><option value="100" @selected(request('por_pagina') == '100')>100</option></select></div>
        <button class="button" type="submit">Filtrar</button><a class="outline-button button-link" href="{{ route('seguimiento.index', ['servicio' => $service]) }}">Limpiar</a>
    </form>

    <div class="tracking-export"><span>Exportar los {{ $rows->total() }} resultados del filtro</span><div><a class="outline-button button-link" href="{{ route('seguimiento.export', ['format' => 'xlsx', 'servicio' => $service] + request()->only(['buscar','mes_inicio','mes_fin'])) }}">Excel ↓</a><a class="outline-button button-link" href="{{ route('seguimiento.export', ['format' => 'pdf', 'servicio' => $service] + request()->only(['buscar','mes_inicio','mes_fin'])) }}">PDF ↓</a><a class="outline-button button-link" href="{{ route('seguimiento.export', ['format' => 'csv', 'servicio' => $service] + request()->only(['buscar','mes_inicio','mes_fin'])) }}">CSV ↓</a><button type="button" class="outline-button" onclick="window.print()">Imprimir</button></div></div>

    @if($errors->any())<div class="tracking-errors" role="alert"><strong>Revisa los datos del formulario.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="tracking-table-wrap">
        <table class="tracking-table">
            <thead><tr>
                @php($sortLabels = ['convocatoria' => 'Convocatoria', 'plantel' => 'Plantel', 'nombre' => 'Nombre del Permisionario'])
                @foreach($sortLabels as $sortKey => $label)<th><a href="{{ route('seguimiento.index', array_merge(request()->except(['page','orden','direccion']), ['servicio'=>$service,'orden'=>$sortKey,'direccion'=>request('orden') === $sortKey && request('direccion') === 'asc' ? 'desc' : 'asc'])) }}">{{ $label }} <span aria-hidden="true">↕</span></a></th>@endforeach
                <th>CURP del Permisionario</th><th>Dirección del Permisionario</th><th>Teléfono del Permisionario</th><th>Correo Electrónico del Permisionario</th>
                <th><a href="{{ route('seguimiento.index', array_merge(request()->except(['page','orden','direccion']), ['servicio'=>$service,'orden'=>'fecha_fin','direccion'=>request('orden') === 'fecha_fin' && request('direccion') === 'asc' ? 'desc' : 'asc'])) }}">Validez del Contrato ↕</a></th>
                <th>Espacio en m²</th><th><a href="{{ route('seguimiento.index', array_merge(request()->except(['page','orden','direccion']), ['servicio'=>$service,'orden'=>'monto','direccion'=>request('orden') === 'monto' && request('direccion') === 'asc' ? 'desc' : 'asc'])) }}">Monto Actual ↕</a></th>
                <th><a href="{{ route('seguimiento.index', array_merge(request()->except(['page','orden','direccion']), ['servicio'=>$service,'orden'=>'registro_at','direccion'=>request('orden') === 'registro_at' && request('direccion') === 'asc' ? 'desc' : 'asc'])) }}">Registro ↕</a></th><th>Estado</th>
            </tr></thead>
            <tbody>
            @forelse($rows as $row)
                @php($days = \Illuminate\Support\Carbon::parse($row->fecha_fin)->startOfDay()->diffInDays(today(), false) * -1)
                <tr class="tracking-row" data-detail="detail-{{ $row->key }}" tabindex="0" aria-label="Abrir seguimiento de {{ $row->nombre }}">
                    <td class="tracking-first"><button type="button" class="tracking-expand" aria-controls="detail-{{ $row->key }}" aria-expanded="false" title="Abrir seguimiento">+</button><span class="tracking-code">{{ $row->registro_id }} - {{ $row->convocatoria }}</span></td>
                    <td>{{ $row->plantel ?: 'Sin plantel' }}</td>
                    <td><strong>{{ $row->nombre ?: 'Sin nombre' }}</strong><a class="tracking-expedient" href="{{ route('seguimiento.zip', $row->key) }}" title="Descargar expediente completo">Expediente ↓</a></td>
                    <td>{{ mb_strtoupper((string) $row->curp) ?: '—' }}</td><td>{{ $row->direccion ?: '—' }}</td><td>{{ $row->telefono ?: '—' }}</td><td>{{ $row->correo ?: '—' }}</td>
                    <td><strong>{{ \Illuminate\Support\Carbon::parse($row->fecha_inicio)->format('d/m/Y') }} al {{ \Illuminate\Support\Carbon::parse($row->fecha_fin)->format('d/m/Y') }}</strong><span @class(['tracking-term','expired' => $days < 0, 'soon' => $days >= 0 && $days <= 30, 'valid' => $days > 30])>{{ $days < 0 ? 'Vencido' : ($days <= 30 ? 'Por vencer en '.$days.' días' : 'Vigente') }}</span></td>
                    <td>{{ $row->seguimiento?->metros_cuadrados ? number_format((float) $row->seguimiento->metros_cuadrados, 2) : 'N/A' }}</td>
                    <td><span class="tracking-money">{{ ($row->seguimiento?->monto ?? $row->monto_base ?? null) !== null ? '$'.number_format((float) ($row->seguimiento?->monto ?? $row->monto_base), 2) : 'N/A' }}</span></td>
                    <td>{{ $row->registro_at ? \Illuminate\Support\Carbon::parse($row->registro_at)->format('d/m/Y H:i:s') : 'Sin registro' }}</td>
                    <td><span @class(['pill', 'pill-ready' => $row->estado === 'Designado', 'pill-pending' => $row->estado !== 'Designado'])>{{ $row->estado }}</span></td>
                </tr>
                <tr id="detail-{{ $row->key }}" class="tracking-detail-row" hidden><td colspan="12">
                    <div class="tracking-detail">
                        <div class="tracking-detail-title"><div><span class="eyebrow">Expediente {{ $row->registro_id }}</span><h3>{{ $row->nombre }} · {{ $row->plantel }}</h3></div><button type="button" class="tracking-close outline-button" data-close="detail-{{ $row->key }}">Cerrar</button></div>
                        <form method="post" action="{{ route('seguimiento.save', $row->key) }}" enctype="multipart/form-data" class="tracking-edit-form">
                            @csrf @method('put')<input type="hidden" name="row_key" value="{{ $row->key }}">
                            @foreach(request()->only(['servicio','buscar','mes_inicio','mes_fin','por_pagina','page']) as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
                            <h4>Datos de seguimiento del permiso</h4>
                            <div class="tracking-fields">
                                <div><label>Metros cuadrados *</label><input name="metros_cuadrados" type="number" step="0.01" min="0.01" value="{{ old('row_key') === $row->key ? old('metros_cuadrados') : $row->seguimiento?->metros_cuadrados }}" required></div>
                                <div><label>Matrícula *</label><input name="matricula" maxlength="80" value="{{ old('row_key') === $row->key ? old('matricula') : $row->seguimiento?->matricula }}" required></div>
                                <div><label>Monto *</label><input name="monto" type="number" step="0.01" min="0" value="{{ old('row_key') === $row->key ? old('monto') : $row->seguimiento?->monto }}" required></div>
                                <div><label>1ª Convocatoria</label><input name="convocatoria1" type="date" value="{{ old('row_key') === $row->key ? old('convocatoria1') : $row->seguimiento?->convocatoria1 }}"></div>
                                <div><label>2ª Convocatoria</label><input name="convocatoria2" type="date" value="{{ old('row_key') === $row->key ? old('convocatoria2') : $row->seguimiento?->convocatoria2 }}"></div>
                                <div><label>3ª Convocatoria</label><input name="convocatoria3" type="date" value="{{ old('row_key') === $row->key ? old('convocatoria3') : $row->seguimiento?->convocatoria3 }}"></div>
                                <div><label>Pago al mes</label><select name="pagosalmes"><option value="">Seleccionar</option>@foreach(['Al corriente','Con adeudo','Sin Servicio'] as $option)<option value="{{ $option }}" @selected((old('row_key') === $row->key ? old('pagosalmes') : $row->seguimiento?->pagosalmes) === $option)>{{ $option }}</option>@endforeach</select></div>
                                <div><label>Construida por</label><select name="construidapor"><option value="">Seleccionar</option>@foreach(['Permisionario','Padres de familia','IMIFE','CoBaEM','Sin Datos','Ayuntamiento','Otro'] as $option)<option value="{{ $option }}" @selected((old('row_key') === $row->key ? old('construidapor') : $row->seguimiento?->construidapor) === $option)>{{ $option }}</option>@endforeach</select></div>
                                <div><label>Servicio de energía eléctrica</label><select name="servicioenergia"><option value="">Seleccionar</option>@foreach(['Plantel','Comisión Federal de Electricidad','Medidor','Toma Directa','Sin Servicio de Luz'] as $option)<option value="{{ $option }}" @selected((old('row_key') === $row->key ? old('servicioenergia') : $row->seguimiento?->servicioenergia) === $option)>{{ $option }}</option>@endforeach</select></div>
                                <div class="tracking-wide"><label>Observaciones</label><textarea name="observaciones" rows="2" maxlength="2000">{{ old('row_key') === $row->key ? old('observaciones') : $row->seguimiento?->observaciones }}</textarea></div>
                            </div>
                            <h4>Archivos adjuntos</h4><p class="field-help">Hasta cinco archivos PDF de 5 MB cada uno. Un archivo nuevo reemplaza el anterior de ese espacio.</p>
                            <div class="tracking-file-grid">@foreach(range(1,5) as $number)<div><label for="file-{{ $row->key }}-{{ $number }}">Archivo {{ $number }}</label><input id="file-{{ $row->key }}-{{ $number }}" name="archivo{{ $number }}" type="file" accept="application/pdf,.pdf">@if($file = $row->archivos->get($number))<small>Actual: {{ $file->nombre }}</small><a href="{{ route('seguimiento.file', ['key'=>$row->key,'number'=>$number]) }}" class="tracking-preview" data-title="Archivo {{ $number }} · {{ $row->nombre }}">Ver PDF ↗</a>@else<small>Sin archivo cargado</small>@endif</div>@endforeach</div>
                            <div class="tracking-form-actions"><button class="button" type="submit">Guardar cambios</button><button type="button" class="outline-button tracking-close" data-close="detail-{{ $row->key }}">Cancelar</button></div>
                        </form>
                        <form method="post" action="{{ route('seguimiento.renew', $row->key) }}" class="tracking-renew-form">
                            @csrf @method('put')<input type="hidden" name="row_key" value="{{ $row->key }}">
                            @foreach(request()->only(['servicio','buscar','mes_inicio','mes_fin','por_pagina','page']) as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
                            <h4>Renovación de contrato</h4><div class="tracking-renew-grid"><div><label>Inicio actual</label><input type="date" value="{{ $row->fecha_inicio }}" disabled></div><div><label>Fin actual</label><input type="date" value="{{ $row->fecha_fin }}" disabled></div><div><label>Nueva fecha de inicio *</label><input type="date" name="fecha_inicio" value="{{ $row->fecha_inicio }}" required></div><div><label>Nueva fecha de finalización *</label><input type="date" name="fecha_fin" value="{{ $row->fecha_fin }}" required></div><button type="submit" class="outline-button">Renovar contrato</button></div>
                        </form>
                    </div>
                </td></tr>
            @empty
                <tr><td colspan="12" class="tracking-empty">No se encontraron permisionarios con estos filtros.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($rows->hasPages())<nav class="pagination" aria-label="Páginas de permisionarios">@if($rows->onFirstPage())<span>← Anterior</span>@else<a href="{{ $rows->previousPageUrl() }}">← Anterior</a>@endif<strong>Página {{ $rows->currentPage() }} de {{ $rows->lastPage() }}</strong>@if($rows->hasMorePages())<a href="{{ $rows->nextPageUrl() }}">Siguiente →</a>@else<span>Siguiente →</span>@endif</nav>@endif
</section>
<dialog id="tracking-pdf-dialog" class="tracking-pdf-dialog"><div><strong id="tracking-pdf-title">Archivo PDF</strong><button type="button" id="tracking-pdf-close" class="outline-button">Cerrar</button></div><iframe id="tracking-pdf-frame" title="Visor de archivo PDF"></iframe></dialog>
@push('scripts')
<script>
(() => {
    const open = id => { const detail = document.getElementById(id); if (!detail) return; const row = document.querySelector(`[data-detail="${id}"]`); detail.hidden = false; row?.querySelector('.tracking-expand')?.setAttribute('aria-expanded','true'); row?.classList.add('expanded'); };
    const close = id => { const detail = document.getElementById(id); if (!detail) return; const row = document.querySelector(`[data-detail="${id}"]`); detail.hidden = true; row?.querySelector('.tracking-expand')?.setAttribute('aria-expanded','false'); row?.classList.remove('expanded'); };
    document.querySelectorAll('.tracking-row').forEach(row => { const toggle = () => document.getElementById(row.dataset.detail).hidden ? open(row.dataset.detail) : close(row.dataset.detail); row.addEventListener('click', e => { if (!e.target.closest('a,button,input,select,textarea')) toggle(); }); row.querySelector('.tracking-expand')?.addEventListener('click', toggle); row.addEventListener('keydown', e => { if (e.key === 'Enter' && e.target === row) toggle(); }); });
    document.querySelectorAll('.tracking-close').forEach(button => button.addEventListener('click', () => close(button.dataset.close)));
    const initial = {{ \Illuminate\Support\Js::from(old('row_key', request('abrir'))) }}; if (initial) open('detail-'+initial);
    document.querySelectorAll('.tracking-renew-form').forEach(form => form.addEventListener('submit', e => { if (!confirm('¿Renovar la vigencia del contrato con estas fechas?')) e.preventDefault(); }));
    document.querySelectorAll('.tracking-file-grid input[type=file]').forEach(input => input.addEventListener('change', () => { const file = input.files[0]; input.setCustomValidity(file && (file.size > 5*1024*1024 || !file.name.toLowerCase().endsWith('.pdf')) ? 'Solo se admiten PDF de hasta 5 MB.' : ''); input.reportValidity(); }));
    const dialog = document.getElementById('tracking-pdf-dialog'), frame = document.getElementById('tracking-pdf-frame');
    document.querySelectorAll('.tracking-preview').forEach(link => link.addEventListener('click', e => { e.preventDefault(); document.getElementById('tracking-pdf-title').textContent = link.dataset.title; frame.src = link.href; dialog.showModal(); }));
    document.getElementById('tracking-pdf-close').addEventListener('click', () => dialog.close()); dialog.addEventListener('close', () => { frame.src = 'about:blank'; });
})();
</script>
@endpush
@endsection
