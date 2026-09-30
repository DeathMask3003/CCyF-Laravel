<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Registro recibido · CCyF</title></head>
<body style="margin:0;background:#f5f2f0;color:#302a2d;font-family:Arial,Helvetica,sans-serif">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent">Registro {{ $record->folio }} recibido para {{ $record->servicio_nombre }} en {{ $record->plantel_nombre }}.</div>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;background:#f5f2f0"><tr><td align="center" style="padding:28px 16px 40px">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="640" style="width:100%;max-width:640px;border-collapse:separate;background:#fff;border:1px solid #e9e0e2;border-radius:16px">
        <tr><td style="padding:22px 28px 18px"><img src="{{ $institutionalHeaderCid }}" width="584" alt="Gobierno del Estado de México · Secretaría de Educación · COBAEM" style="display:block;width:100%;height:auto;border:0"></td></tr>
        <tr><td style="height:4px;line-height:4px;font-size:0;background:#611232">&nbsp;</td></tr>
        <tr><td style="padding:28px 34px 10px">
            <p style="margin:0 0 10px;color:#946d7e;font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase">Concurso de Cafetería y Fotocopiado</p>
            <h1 style="margin:0;color:#4b102a;font-size:26px;line-height:1.25">{{ $audience === 'participant' ? 'Recibimos tu propuesta' : 'Nuevo registro recibido' }}</h1>
            <p style="margin:16px 0 0;color:#554b50;font-size:15px;line-height:1.6">{{ $audience === 'participant' ? 'Hola, '.$record->solicitante.'. Tu propuesta quedó registrada y está lista para revisión.' : 'Se registró una nueva propuesta en el portal CCyF. Estos son los datos del expediente:' }}</p>
        </td></tr>
        <tr><td style="padding:8px 34px 20px">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;background:#faf7f8;border:1px solid #eee4e8;border-radius:10px">
                @foreach (['Folio' => $record->folio, 'Participante' => $record->solicitante, 'Convocatoria' => $record->convocatoria_nombre, 'Servicio' => $record->servicio_nombre, 'Plantel' => $record->plantel_nombre, 'Documentos recibidos' => $record->document_count] as $label => $value)
                <tr><td style="padding:9px 12px;border-bottom:1px solid #eee4e8;color:#806875;font-size:13px">{{ $label }}</td><td style="padding:9px 12px;border-bottom:1px solid #eee4e8;color:#332930;font-size:13px;font-weight:700">{{ $value }}</td></tr>
                @endforeach
            </table>
        </td></tr>
        <tr><td style="padding:0 34px 28px">
            <p style="margin:0 0 18px;color:#685b62;font-size:13px;line-height:1.6">{{ $audience === 'participant' ? 'Conserva este folio para consultar el estado de tu participación.' : 'Consulta el expediente y la documentación desde el sistema.' }}</p>
            <a href="{{ $audience === 'participant' ? route('oficios.receipt', $record->id) : route('revision.show', $record->id) }}" style="display:inline-block;padding:13px 20px;border-radius:9px;background:#611232;color:#fff;font-size:13px;font-weight:700;text-decoration:none">{{ $audience === 'participant' ? 'Ver mi registro' : 'Revisar expediente' }}</a>
        </td></tr>
        <tr><td style="padding:18px 34px 22px;background:#fbf8f9;border-top:1px solid #eee6e9;color:#6b5c64;font-size:12px;line-height:1.5">CCyF · Colegio de Bachilleres del Estado de México<br>Este mensaje se generó automáticamente.</td></tr>
    </table>
</td></tr></table>
</body></html>
