# CCyF Laravel en pruebas.cobaemex.edu.mx

Esta instalación usa la copia `ccyflaravel` y los expedientes históricos ya preparados en `C:\xampp\htdocs\ccyf-laravel`. El dominio público `ccyf.cobaemex.edu.mx` continúa con el sistema original. Una copia de la base tomada antes de crear registros nuevos no incluirá esos registros.

## Publicar la instalación de pruebas por HTTPS

1. En PowerShell del Windows Server, dentro de `C:\xampp\htdocs\ccyf-laravel`, ejecutar `git pull --ff-only`. Conservar `.env` y los archivos copiados fuera de Git.
2. En `.env`, establecer `APP_ENV=staging`, `APP_URL=https://pruebas.cobaemex.edu.mx`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true` y `SESSION_COOKIE=ccyf_laravel_staging_session`. Conservar `DB_DATABASE=ccyflaravel`, `MAIL_MAILER=log`, `CCYF_TURNSTILE_ENABLED=false` y `CCYF_GOOGLE_LOGIN_ENABLED=false` mientras no se configure Google. Mantener las claves y contraseñas únicamente en el servidor.
3. Ejecutar `C:\xampp\php\php.exe artisan optimize:clear` después de guardar `.env`.
4. Ejecutar `powershell.exe -NoProfile -ExecutionPolicy Bypass -File '.\deploy\windows-server2022\stage-pruebas-vhost.ps1'`. El script conserva el certificado, cabeceras y demás dominios; cambia únicamente los bloques de `pruebas.cobaemex.edu.mx`, redirige `www` al host principal, apunta a `ccyf-laravel/public`, deja acceso público al formulario de CCyF, respalda `httpd-vhosts.conf` y valida la sintaxis. No reinicia Apache.
5. Revisar la salida del script y reiniciar Apache desde XAMPP cuando se pueda interrumpir brevemente el servicio. Confirmar con `C:\xampp\apache\bin\httpd.exe -S` que el dominio de pruebas apunta al bloque esperado. Abrir `https://pruebas.cobaemex.edu.mx/acceso` en una ventana privada: debe mostrar directamente el acceso de CCyF. Probar una cuenta administrativa, un permisionario y un PDF.

El dominio de pruebas quedará accesible por Internet. La aplicación sigue exigiendo una cuenta activa para abrir el panel y los expedientes. El formulario público de registro permite crear datos de ensayo en la copia `ccyflaravel`; estos no se trasladan al sistema original.

## Activar Google en pruebas

El código permite Google en `staging` solo cuando el sitio usa HTTPS, hay credenciales y `GOOGLE_REDIRECT_URI` coincide exactamente con `APP_URL` más `/acceso/google/callback`. El entorno `local` continúa sin Google. El captcha permanece exclusivo de `production`.

1. Registrar en el cliente OAuth de Google la URL de retorno exacta `https://pruebas.cobaemex.edu.mx/acceso/google/callback`. Google exige coincidencia exacta del esquema, host y ruta: [documentación de Google OAuth](https://developers.google.com/identity/protocols/oauth2/web-server).
2. Colocar `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` y `GOOGLE_REDIRECT_URI` en el `.env` del servidor. Pueden utilizarse credenciales de un cliente OAuth autorizado para este dominio; no incluirlas en Git ni enviarlas por chat. Poner `CCYF_GOOGLE_LOGIN_ENABLED=true` y ejecutar `C:\xampp\php\php.exe artisan optimize:clear`.
3. Abrir el dominio principal de pruebas y probar Google con una cuenta ya existente de CCyF. La aplicación vincula únicamente cuentas activas con correo verificado y coincidencia única. Si se usa una pantalla de consentimiento en modo de pruebas, incluir allí a las cuentas que participarán en el ensayo.

## Preparar correo y Turnstile sin avisar a los usuarios de la copia

El CCyF original usa `smtp.office365.com`, puerto `587` y TLS. En el `.env` de este servidor se pueden completar `MAIL_SCHEME=smtp`, `MAIL_HOST=smtp.office365.com`, `MAIL_PORT=587`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` y `MAIL_FROM_NAME="CCyF"`. El remitente debe ser la misma cuenta autenticada o una dirección para la que esa cuenta tenga permiso de envío. La cuenta y su clave se configuran únicamente en el servidor. Mantener **`MAIL_MAILER=log`** durante el ensayo: los avisos automáticos generados desde la copia de producción no llegarán a sus destinatarios reales.

Una vez configurado SMTP, comprobarlo con un único destinatario de control, elegido expresamente:

```powershell
& 'C:\xampp\php\php.exe' artisan optimize:clear
& 'C:\xampp\php\php.exe' artisan ccyf:mail-test 'correo-de-control@dominio.example'
```

El comando usa SMTP solo para ese mensaje; no cambia `MAIL_MAILER`. Un resultado satisfactorio confirma que el servidor de correo aceptó el mensaje. Hay que verificar también que llegó al buzón. Si falla, revisar `storage/logs/laravel.log` sin compartir contraseñas ni el `.env`. Antes del corte, revisar en el `.env` los destinatarios `CCYF_MAIL_LEGAL`, `CCYF_MAIL_MANAGEMENT` y `CCYF_MAIL_CCYF` para los avisos de resolución.

Para comprobar la recuperación de contraseña con una cuenta de pruebas ya registrada, conservar `MAIL_MAILER=log` y agregar el correo **exacto de esa cuenta** a `CCYF_STAGING_RECOVERY_SMTP_EMAILS`. Se admiten varias direcciones separadas por comas. Solo los mensajes de recuperación destinados a esa lista saldrán por SMTP en `staging`; los de otras cuentas permanecerán en el registro. Confirmar primero que `ccyf:mail-test` llega al buzón y limpiar la caché de configuración después de editar `.env`. No incluir correos de usuarios reales de la copia sin necesidad de prueba.

Para ensayar Turnstile, crear en Cloudflare un widget para `pruebas.cobaemex.edu.mx` y otro para `ccyf.cobaemex.edu.mx`. Colocar **solo las claves del primero** en el `.env` de pruebas:

```dotenv
CCYF_TURNSTILE_ENABLED=true
TURNSTILE_SITE_KEY=clave_publica_de_pruebas
TURNSTILE_SECRET_KEY=clave_secreta_de_pruebas
```

Ejecutar `artisan optimize:clear` y comprobar acceso con contraseña, registro de cuenta y envío final de un expediente. Turnstile permanece apagado en `local`; en `staging` requiere HTTPS y la activación explícita. La validación del servidor exige el hostname y la acción correctos. **No usar claves de prueba universales de Cloudflare para esta prueba funcional**, ya que la aplicación comprueba también esos campos. Para desactivarlo, establecer `CCYF_TURNSTILE_ENABLED=false` y limpiar la caché. Guardar las claves del widget de producción para el cambio final.
