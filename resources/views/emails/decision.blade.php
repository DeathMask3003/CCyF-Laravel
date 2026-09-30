<!doctype html>
<html lang="es"><head><meta charset="utf-8"></head>
<body style="margin:0;background:#f7f4f5;color:#382b32;font-family:Arial,sans-serif">
<div style="max-width:640px;margin:24px auto;background:#fff;border:1px solid #e8dde1;border-radius:14px;overflow:hidden">
    <div style="padding:22px 28px;background:#611232;color:#fff"><strong style="font-size:18px">CCyF · CoBaEMex</strong><div style="margin-top:5px;color:#ead1db;font-size:13px">Resultado del concurso de cafetería y fotocopiado</div></div>
    <div style="padding:28px">
        <p style="margin-top:0">{{ match ($audience) { 'internal' => 'A la Unidad Jurídica y áreas involucradas:', 'accounting' => 'Al Departamento de Presupuesto y Contabilidad:', default => 'Estimada persona participante:' } }}</p>
        <p>{{ match ($audience) { 'internal' => 'Se remite la carta de designación y la documentación del expediente indicado.', 'accounting' => 'Se remite la carta de designación del expediente indicado para su conocimiento.', default => 'Ya se registró el resultado de su participación en la convocatoria.' } }}</p>
        <table role="presentation" style="width:100%;border-collapse:collapse;margin:18px 0">
            <tr><td style="padding:8px;border-bottom:1px solid #eee4e8;color:#806875">Folio</td><td style="padding:8px;border-bottom:1px solid #eee4e8"><strong>{{ $record->folio }}</strong></td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #eee4e8;color:#806875">Permisionario</td><td style="padding:8px;border-bottom:1px solid #eee4e8">{{ $record->solicitante }}</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #eee4e8;color:#806875">Convocatoria</td><td style="padding:8px;border-bottom:1px solid #eee4e8">{{ $record->convocatoria_nombre }}</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #eee4e8;color:#806875">Servicio</td><td style="padding:8px;border-bottom:1px solid #eee4e8">{{ $record->servicio_nombre }}</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #eee4e8;color:#806875">Plantel</td><td style="padding:8px;border-bottom:1px solid #eee4e8">{{ $record->plantel_nombre }}</td></tr>
        </table>
        <p style="padding:13px 16px;background:#f8eff3;border-left:4px solid #611232"><strong>Resultado: {{ match ($record->decision) { 'designado' => 'Designado', 'no_aceptado' => 'No aceptado', default => 'No designado' } }}</strong><br>{{ $record->respuesta }}</p>
        <p style="font-size:13px;color:#695c63">Se adjunta la carta PDF del resultado@if($documents). Esta es la parte {{ $part }} de {{ $totalParts }} de la documentación del expediente ({{ count($documents) }} {{ count($documents) === 1 ? 'archivo' : 'archivos' }} en este mensaje)@endif.</p>
        <p style="font-size:12px;color:#8b7d84;margin-bottom:0">Conserve este correo y consulte el expediente en el sistema CCyF.</p>
    </div>
</div>
</body></html>
