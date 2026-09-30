# CCyF Laravel en pruebas.cobaemex.edu.mx

Esta instalación usa la copia `ccyflaravel` y los expedientes históricos ya preparados en `C:\xampp\htdocs\ccyf-laravel`. El dominio público `ccyf.cobaemex.edu.mx` continúa con el sistema original. Una copia de la base tomada antes de crear registros nuevos no incluirá esos registros.

## Publicar la instalación de pruebas por HTTPS

1. En PowerShell del Windows Server, dentro de `C:\xampp\htdocs\ccyf-laravel`, ejecutar `git pull --ff-only`. Conservar `.env` y los archivos copiados fuera de Git.
2. En `.env`, establecer `APP_ENV=staging`, `APP_URL=https://pruebas.cobaemex.edu.mx`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true` y `SESSION_COOKIE=ccyf_laravel_staging_session`. Conservar `DB_DATABASE=ccyflaravel`, `MAIL_MAILER=log`, `CCYF_TURNSTILE_ENABLED=false` y `CCYF_GOOGLE_LOGIN_ENABLED=false` mientras no se configure Google. Mantener las claves y contraseñas únicamente en el servidor.
3. Ejecutar `C:\xampp\php\php.exe artisan optimize:clear` después de guardar `.env`.
4. Ejecutar `powershell.exe -NoProfile -ExecutionPolicy Bypass -File '.\deploy\windows-server2022\stage-pruebas-vhost.ps1'`. La primera ejecución pide una contraseña adicional de al menos 12 caracteres y crea `C:\xampp\ccyf-staging-auth\users.htpasswd` con el usuario `ccyf-pruebas`. El script conserva el certificado, cabeceras y demás dominios; cambia únicamente los bloques de `pruebas.cobaemex.edu.mx`, redirige `www` al host principal, apunta a `ccyf-laravel/public`, exige la contraseña adicional, respalda `httpd-vhosts.conf` y valida la sintaxis. No reinicia Apache.
5. Revisar la salida del script y reiniciar Apache desde XAMPP cuando se pueda interrumpir brevemente el servicio. Confirmar con `C:\xampp\apache\bin\httpd.exe -S` que el dominio de pruebas apunta al bloque esperado. Abrir `https://pruebas.cobaemex.edu.mx/acceso` en una ventana privada: primero debe pedir el usuario `ccyf-pruebas` y su contraseña adicional; luego mostrar el acceso de CCyF. Probar una cuenta administrativa, un permisionario y un PDF.

La contraseña adicional no se guarda en Git. No ejecutar `htpasswd -c` sobre un archivo ya existente, porque lo reemplaza. Para cambiarla, usar `htpasswd -i -B` sin `-c` y entregar la contraseña por la entrada estándar; no ponerla como argumento de la consola.

## Activar Google en pruebas

El código permite Google en `staging` solo cuando el sitio usa HTTPS, hay credenciales y `GOOGLE_REDIRECT_URI` coincide exactamente con `APP_URL` más `/acceso/google/callback`. El entorno `local` continúa sin Google. El captcha permanece exclusivo de `production`.

1. Registrar en el cliente OAuth de Google la URL de retorno exacta `https://pruebas.cobaemex.edu.mx/acceso/google/callback`. Google exige coincidencia exacta del esquema, host y ruta: [documentación de Google OAuth](https://developers.google.com/identity/protocols/oauth2/web-server).
2. Colocar `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` y `GOOGLE_REDIRECT_URI` en el `.env` del servidor. Pueden utilizarse credenciales de un cliente OAuth autorizado para este dominio; no incluirlas en Git ni enviarlas por chat. Poner `CCYF_GOOGLE_LOGIN_ENABLED=true` y ejecutar `C:\xampp\php\php.exe artisan optimize:clear`.
3. Abrir el dominio principal de pruebas, autenticarse primero en Apache y probar Google con una cuenta ya existente de CCyF. La aplicación vincula únicamente cuentas activas con correo verificado y coincidencia única. Si se usa una pantalla de consentimiento en modo de pruebas, incluir allí a las cuentas que participarán en el ensayo.

El correo continúa en `MAIL_MAILER=log`: ninguna prueba debe enviar notificaciones reales a permisionarios. La contraseña adicional protege también la URL de retorno de Google; el navegador puede volver a solicitarla durante el ensayo.
