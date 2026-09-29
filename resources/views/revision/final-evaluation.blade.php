@php
    $service = $record->servicio === 'cafeteria' ? 'CAFETERÍA' : 'FOTOCOPIADO';
    $status = static fn ($value) => $value === null ? 'SIN EVALUAR' : ((int) $value === 1 ? 'CUMPLE' : 'NO CUMPLE');
    $result = match ($source->result) { 1 => 'VIABLE', 2 => 'PROBABLE', 3 => 'NO VIABLE' };
    $evaluatedAt = $source->evaluated_at ? \Illuminate\Support\Carbon::parse($source->evaluated_at)->format('d/m/Y H:i') : 'Sin fecha registrada';
@endphp
<style>
    body { font-family: dejavusans; color: #302930; font-size: 8pt; }
    h1 { color: #7e2039; text-align: center; font-size: 12pt; margin: 0 0 1px; }
    .subtitle { color: #7e2039; text-align: center; font-weight: bold; font-size: 9pt; margin: 0 0 11px; }
    h2 { background: #8c2236; color: white; font-size: 8pt; padding: 6px; margin: 12px 0 0; }
    table { border-collapse: collapse; width: 100%; table-layout: fixed; }
    thead { display: table-header-group; }
    th { background: #8c2236; color: white; padding: 5px; border: 1px solid #8c2236; font-size: 7pt; }
    td { border: 1px solid #d7cdd0; padding: 5px; vertical-align: top; font-size: 7pt; word-wrap: break-word; }
    tr:nth-child(even) td { background: #f8f4f5; }
    .info-label { background: #f5f2f3; font-weight: bold; width: 29%; }
    .state { text-align: center; font-weight: bold; }
    .price { text-align: right; white-space: nowrap; }
    .result { text-align: center; color: white; background: #8c2236; font-weight: bold; padding: 9px; font-size: 10pt; }
    .note { border: 1px solid #ded4d7; background: #fbf8f9; padding: 9px; line-height: 1.45; }
    .source { color: #6e6469; font-size: 7pt; margin-top: 13px; }
    .result-block { page-break-inside: avoid; }
    @if ($record->servicio === 'fotocopiado')
    .subtitle { margin-bottom: 6px; }
    h2 { padding: 4px 6px; margin-top: 7px; }
    th, td { padding: 3px 5px; }
    .result { padding: 5px; }
    .source { margin-top: 5px; }
    @endif
</style>
<h1>HOJA FINAL DE EVALUACIÓN DE DOCUMENTACIÓN</h1>
<div class="subtitle">SERVICIO DE {{ $service }}</div>

<h2>INFORMACIÓN GENERAL</h2>
<table>
    <tr><td class="info-label">Convocatoria</td><td>{{ $record->convocatoria }}</td></tr>
    <tr><td class="info-label">Plantel / Centro EMSAD</td><td>{{ $record->plantel }}</td></tr>
    <tr><td class="info-label">Nombre del interesado</td><td>{{ $record->nombre ?: 'Sin nombre registrado' }}</td></tr>
    <tr><td class="info-label">Prevaluador</td><td>{{ $evaluator ?: 'Sin nombre registrado' }}</td></tr>
    <tr><td class="info-label">Fecha de evaluación</td><td>{{ $evaluatedAt }}</td></tr>
</table>

<h2>EVALUACIÓN DE DOCUMENTOS</h2>
<table>
    <thead><tr><th width="5%">N.º</th><th width="36%">Documento requerido</th><th width="16%">Estado</th><th width="43%">Observaciones</th></tr></thead>
    <tbody>
    @foreach ($detail['documents'] as $document)
        @php($item = $detail['items']->get($document->clave))
        <tr><td>{{ $loop->iteration }}</td><td>{{ $document->nombre }}</td><td class="state">{{ $status($item?->cumple) }}</td><td>{{ $item?->comentario ?: '—' }}</td></tr>
    @endforeach
    </tbody>
</table>

<h2>EVALUACIÓN DE PRECIOS DE {{ $record->servicio === 'cafeteria' ? 'ALIMENTOS' : 'FOTOCOPIADO' }}</h2>
@if ($record->servicio === 'cafeteria')
    @include('prevaluaciones.price-columns', ['prices' => $detail['prices'], 'items' => $detail['items'], 'rating' => $status])
@else
<table>
    <thead><tr><th width="5%">N.º</th><th width="33%">{{ $record->servicio === 'cafeteria' ? 'Producto' : 'Servicio' }}</th><th width="14%">Precio</th><th width="16%">Estado</th><th width="32%">Observaciones</th></tr></thead>
    <tbody>
    @forelse ($detail['prices'] as $price)
        @php($item = $detail['items']->get($price->clave))
        <tr><td>{{ $loop->iteration }}</td><td>{{ $price->nombre }}{{ $price->unidad ? ' · '.$price->unidad : '' }}</td><td class="price">${{ number_format((float) $price->precio, 2) }}</td><td class="state">{{ $status($item?->cumple) }}</td><td>{{ $item?->comentario ?: '—' }}</td></tr>
    @empty
        <tr><td colspan="5">No hay precios capturados para este expediente.</td></tr>
    @endforelse
    </tbody>
</table>
@endif

<div class="result-block">
    <h2>RESULTADO DE VIABILIDAD</h2>
    <div class="result">{{ $result }}</div>
    @if ($detail['adminNote'])
        <p class="note"><strong>Observaciones del administrador:</strong> {{ $detail['adminNote'] }}</p>
    @endif
    <p class="source">Reporte generado desde la prevaluación registrada.</p>
</div>
