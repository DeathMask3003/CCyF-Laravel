@php
    $label = $record->servicio === 'cafeteria' ? 'CAFETERÍA' : 'FOTOCOPIADO';
    $state = static fn ($value) => $value === null ? 'PENDIENTE' : ((int) $value === 1 ? 'CUMPLE' : 'NO CUMPLE');
    $result = match ((int) $detail['result']) { 1 => 'VIABLE', 2 => 'PROBABLE', 3 => 'NO VIABLE', default => 'PENDIENTE DE EVALUAR' };
    $priceCounts = ['CUMPLE' => 0, 'NO CUMPLE' => 0, 'PENDIENTE' => 0];
    foreach ($detail['prices'] as $price) $priceCounts[$state($detail['items']->get($price->clave)?->cumple)]++;
@endphp
<style>
    body { font-family: dejavusans; color: #2c2529; font-size: 8pt; }
    h1 { color: #7e2039; text-align: center; font-size: 12pt; margin: 0 0 1px; }
    .subtitle { color: #7e2039; text-align: center; font-weight: bold; font-size: 9pt; margin: 0 0 11px; }
    h2 { background: #8c2236; color: white; font-size: 8pt; text-align: center; padding: 6px; margin: 11px 0 0; }
    table { border-collapse: collapse; width: 100%; table-layout: fixed; }
    th { background: #8c2236; color: white; padding: 5px; border: 1px solid #8c2236; font-size: 7pt; }
    td { border: 1px solid #d7cdd0; padding: 5px; vertical-align: top; word-wrap: break-word; font-size: 7pt; }
    tr:nth-child(even) td { background: #f8f4f5; }
    .info-label { background: #f5f2f3; font-weight: bold; width: 29%; }
    .price { text-align: right; white-space: nowrap; }
    .state { text-align: center; font-weight: bold; }
    .counts { text-align: center; margin: 5px 0; padding: 5px; border: 1px solid #ded4d7; }
    .result { text-align: center; color: white; background: #8c2236; font-weight: bold; padding: 8px; font-size: 10pt; }
    .signature { text-align: center; padding-top: 38px; }
    .signature-line { border-top: 1px solid #777; width: 48%; margin: auto; padding-top: 4px; }
    .note { color: #675e62; font-size: 7pt; text-align: center; margin-top: 10px; }
    @if($record->servicio === 'fotocopiado')
    h2 { padding: 5px; margin-top: 8px; }
    th, td { padding: 4px 5px; }
    .counts { margin: 4px 0; padding: 4px; }
    .result { padding: 6px; }
    .signature { padding-top: 22px; }
    .note { margin-top: 5px; }
    @endif
</style>
<h1>REPORTE DE PREVALUACIÓN DE DOCUMENTACIÓN Y PRECIOS</h1>
<div class="subtitle">SERVICIO DE {{ $label }}</div>

<h2>INFORMACIÓN GENERAL</h2>
<table>
    <tr><td class="info-label">Convocatoria</td><td>{{ $record->convocatoria }}</td></tr>
    <tr><td class="info-label">Plantel / Centro EMSAD</td><td>{{ $record->plantel }}</td></tr>
    <tr><td class="info-label">Nombre del interesado</td><td>{{ $record->nombre ?: 'Sin nombre' }}</td></tr>
    <tr><td class="info-label">Fecha de generación</td><td>{{ now()->format('d/m/Y H:i') }}</td></tr>
</table>

<h2>EVALUACIÓN DE DOCUMENTOS</h2>
<table>
    <thead><tr><th width="5%">N.º</th><th width="34%">Documento requerido</th><th width="15%">Estado</th><th width="46%">Observaciones</th></tr></thead>
    <tbody>
    @foreach($detail['documents'] as $document)
        @php($item = $detail['items']->get($document->clave))
        <tr><td>{{ $loop->iteration }}</td><td>{{ $document->nombre }}</td><td class="state">{{ $state($item?->cumple) }}</td><td>{{ $item?->comentario ?: '—' }}</td></tr>
    @endforeach
    </tbody>
</table>

<h2>EVALUACIÓN DE PRECIOS DE {{ $record->servicio === 'cafeteria' ? 'ALIMENTOS' : 'FOTOCOPIADO' }}</h2>
@if ($record->servicio === 'cafeteria')
    @include('prevaluaciones.price-columns', ['prices' => $detail['prices'], 'items' => $detail['items'], 'rating' => $state])
@else
<table>
    <thead><tr><th width="5%">N.º</th><th width="32%">{{ $record->servicio === 'cafeteria' ? 'Alimento' : 'Servicio' }}</th><th width="13%">Precio</th><th width="15%">Estado</th><th width="35%">Observaciones</th></tr></thead>
    <tbody>
    @forelse($detail['prices'] as $price)
        @php($item = $detail['items']->get($price->clave))
        <tr><td>{{ $loop->iteration }}</td><td>{{ $price->nombre }}{{ $price->unidad ? ' · '.$price->unidad : '' }}</td><td class="price">${{ number_format((float) $price->precio, 2) }}</td><td class="state">{{ $state($item?->cumple) }}</td><td>{{ $item?->comentario ?: '—' }}</td></tr>
    @empty
        <tr><td colspan="5">No hay precios capturados en esta propuesta.</td></tr>
    @endforelse
    </tbody>
</table>
@endif
<div class="counts">Precios que cumplen: {{ $priceCounts['CUMPLE'] }} &nbsp; · &nbsp; No cumplen: {{ $priceCounts['NO CUMPLE'] }} &nbsp; · &nbsp; Pendientes: {{ $priceCounts['PENDIENTE'] }}</div>

<h2>RESULTADO DE VIABILIDAD</h2>
<div class="result">{{ $result }}</div>
@if($detail['adminNote'])<p><strong>Observaciones del administrador:</strong> {{ $detail['adminNote'] }}</p>@endif
<div class="signature"><div class="signature-line"><strong>{{ $evaluator ?: 'Prevaluador pendiente de asignación' }}</strong><br>Prevaluador de {{ $label }}</div></div>
<p class="note">Este reporte de prevaluación tiene carácter informativo. La evaluación final corresponde a las autoridades competentes.</p>
