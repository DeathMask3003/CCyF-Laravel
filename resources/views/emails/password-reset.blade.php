<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Restablece tu contraseña de CCyF</title>
</head>
<body style="margin:0;padding:0;background:#f5f2f0;color:#302a2d;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">Usa este enlace seguro para restablecer tu contraseña de CCyF. Vence en {{ $expiresInMinutes }} minutos.</div>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;background:#f5f2f0;">
        <tr><td align="center" style="padding:28px 16px 40px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="640" style="width:100%;max-width:640px;border-collapse:separate;background:#ffffff;border:1px solid #e9e0e2;border-radius:16px;">
                <tr><td style="padding:22px 28px 18px;background:#ffffff;border-radius:16px 16px 0 0;">
                    <img src="{{ $institutionalHeaderCid }}" width="584" alt="Gobierno del Estado de México · Secretaría de Educación · COBAEM" style="display:block;width:100%;height:auto;border:0;outline:none;text-decoration:none;">
                </td></tr>
                <tr><td style="height:4px;line-height:4px;font-size:0;background:#6a1739;">&nbsp;</td></tr>
                <tr><td style="padding:30px 34px 12px;">
                    <p style="margin:0 0 10px;color:#946d7e;font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;">Concurso de Cafetería y Fotocopiado</p>
                    <h1 style="margin:0;color:#4b102a;font-size:27px;line-height:1.25;font-weight:700;">Restablece tu contraseña</h1>
                    <p style="margin:17px 0 0;color:#302a2d;font-size:16px;line-height:1.6;">Hola, <strong>{{ $recipientName }}</strong>:</p>
                    <p style="margin:13px 0 0;color:#554b50;font-size:15px;line-height:1.65;">Recibimos una solicitud para restablecer la contraseña de tu cuenta en CCyF. Para continuar, usa el siguiente botón:</p>
                </td></tr>
                <tr><td style="padding:13px 34px 24px;">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:separate;">
                        <tr><td align="center" bgcolor="#611232" style="border-radius:9px;background:#611232;">
                            <a href="{{ $resetUrl }}" style="display:inline-block;padding:15px 24px;color:#ffffff;font-size:15px;font-weight:700;line-height:1.2;text-decoration:none;">Restablecer contraseña</a>
                        </td></tr>
                    </table>
                </td></tr>
                <tr><td style="padding:0 34px 28px;">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;background:#faf4ec;border-left:4px solid #b88b50;">
                        <tr><td style="padding:14px 16px;color:#5a4333;font-size:13px;line-height:1.55;"><strong>Enlace válido por {{ $expiresInMinutes }} minutos.</strong><br>Si no solicitaste este cambio, ignora este mensaje. Tu contraseña seguirá igual.</td></tr>
                    </table>
                    <p style="margin:24px 0 8px;color:#776d72;font-size:12px;line-height:1.55;">Si el botón no abre la página, copia este enlace y pégalo en tu navegador:</p>
                    <p style="margin:0;word-break:break-all;overflow-wrap:anywhere;font-size:12px;line-height:1.6;"><a href="{{ $resetUrl }}" style="color:#611232;text-decoration:underline;">{{ $resetUrl }}</a></p>
                </td></tr>
                <tr><td style="padding:18px 34px 22px;background:#fbf8f9;border-top:1px solid #eee6e9;border-radius:0 0 16px 16px;">
                    <p style="margin:0;color:#6b5c64;font-size:12px;line-height:1.5;"><strong>CCyF · COBAEM</strong><br>Este mensaje se generó automáticamente. No compartas el enlace de recuperación.</p>
                </td></tr>
            </table>
            <p style="margin:18px 0 0;color:#9a8d93;font-size:11px;line-height:1.5;">Colegio de Bachilleres del Estado de México</p>
        </td></tr>
    </table>
</body>
</html>
