@php
    $label = $service === 'cafeteria' ? 'CAFETERÍA' : 'FOTOCOPIADO';
    $resultLabel = static fn ($value) => match ((int) $value) {1 => 'VIABLE', 2 => 'PROBABLE', 3 => 'NO VIABLE', default => 'SIN DEFINIR'};
@endphp
<style>
    body { font-family: dejavusans; font-size: 8.5pt; color: #332b2e; line-height: 1.45; }
    h1 { text-align: center; color: #81233d; font-size: 14pt; margin: 0 0 3px; }
    .subtitle { text-align: center; color: #81233d; font-size: 9pt; font-weight: bold; margin-bottom: 12px; }
    .intro { border: 1px solid #e3d5d9; background: #faf6f7; padding: 8px 11px; margin-bottom: 15px; }
    .campus { font-size: 10pt; color: #74203b; border-bottom: 2px solid #a27351; padding-bottom: 4px; margin: 17px 0 8px; page-break-after: avoid; }
    .campus small { font-size: 7.5pt; color: #6c6065; font-weight: normal; }
    .proposal { border: 1px solid #dfd3d7; margin: 0 0 10px; padding: 8px 10px; }
    .proposal-head { margin: 0 0 5px; color: #4d2536; font-size: 9pt; font-weight: bold; page-break-after: avoid; }
    .status { color: #7c2140; font-weight: bold; }
    .meta { color: #655b60; font-size: 7.5pt; margin: 0 0 5px; }
    .observations { margin: 5px 0 0 14px; padding: 0; }
    .observations li { margin: 0 0 3px; }
    .admin { border-top: 1px solid #e9e1e4; margin-top: 7px; padding-top: 5px; }
    .muted { color: #777075; }
</style>
<h1>ANEXO 1 · RESUMEN DE PREVALUACIONES</h1>
<div class="subtitle">SERVICIO DE {{ $label }} · {{ mb_strtoupper((string) $current->numero) }}</div>
<div class="intro">Relación de {{ $rows->count() }} propuestas recibidas en {{ $groups->count() }} planteles. Se muestra el resultado de la prevaluación, las observaciones registradas y el seguimiento administrativo.</div>

@forelse($groups as $campus => $proposals)
    <h2 class="campus">{{ $campus }} <small>· {{ $proposals->count() }} {{ $proposals->count() === 1 ? 'propuesta' : 'propuestas' }}</small></h2>
    @foreach($proposals as $summary)
        @php
            $record = $summary->record;
            $detail = $summary->detail;
            $names = $detail['documents']->concat($detail['prices'])->keyBy('clave');
            $observations = $detail['items']->filter(fn ($item) => trim((string) $item->comentario) !== '');
        @endphp
        <div class="proposal">
            <p class="proposal-head">{{ $loop->iteration }}. {{ $record->nombre ?: 'Permisionario sin nombre' }} <span class="status">· {{ $resultLabel($record->resultado) }}</span></p>
            <p class="meta">Expediente {{ $record->registro_id }} · Prevaluador: {{ $summary->evaluator ?: 'Sin asignar' }} · Campos evaluados: {{ $summary->evaluatedFields }}{{ $summary->date ? ' · Última evaluación: '.\Illuminate\Support\Carbon::parse($summary->date)->format('d/m/Y H:i') : '' }}</p>
            @if($observations->isNotEmpty())
                <strong>Observaciones de la prevaluación:</strong>
                <ul class="observations">@foreach($observations as $key => $item)<li><strong>{{ $names->get($key)?->nombre ?: $key }}:</strong> {{ $item->comentario }}</li>@endforeach</ul>
            @else
                <span class="muted">Sin observaciones de prevaluación registradas.</span>
            @endif
            <div class="admin"><strong>Observaciones del administrador:</strong> {{ $detail['adminNote'] ?: 'Sin registro.' }}</div>
        </div>
    @endforeach
@empty
    <p class="muted">No hay propuestas registradas en esta convocatoria.</p>
@endforelse
