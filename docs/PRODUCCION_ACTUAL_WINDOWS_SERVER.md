# Estado actual de CCyF en Windows Server 2022

Al 2 de octubre de 2026, Apache sirve `https://ccyf.cobaemex.edu.mx` desde `C:\xampp\htdocs\ccyf-laravel\public`. El `.env` de ese checkout usa `APP_ENV=production`, `APP_URL=https://ccyf.cobaemex.edu.mx`, la base `ccyflaravel_prod` y SMTP. `pruebas.cobaemex.edu.mx` sirve el mismo checkout y, por tanto, la misma base; no es un entorno aislado.

El checkout `C:\xampp\htdocs\ccyf-laravel-prod` creado durante la preparación no es el que sirve Apache. No se necesita para actualizar el sitio actual. **No ejecutes `promote-production-vhost.ps1` en este servidor.**

## Completar el PDF destacado de inicio

La migración `2026_10_02_000001_add_home_document_to_ccyf_branding` añade tres columnas opcionales a `ccyf_branding`. Antes de aplicarla, el script guarda un respaldo SQL de `ccyflaravel_prod` fuera de `htdocs`. Comprueba la base y URL del `.env` sin imprimir credenciales. Después ejecuta solo esa migración y verifica que quedó marcada como `Ran`.

En PowerShell del servidor:

```powershell
Set-Location 'C:\xampp\htdocs\ccyf-laravel'
git pull --ff-only origin main
powershell.exe -NoProfile -ExecutionPolicy Bypass -File '.\deploy\windows-server2022\apply-home-document-update.ps1'
```

Conserva la ruta y el tamaño del respaldo que imprime el script. Si falla el respaldo, la migración no se inicia. Si falla la migración, revisa el error antes de intentar otra acción. La presencia del respaldo no equivale a una prueba de restauración.

## Redirigir el dominio de pruebas

El usuario eligió redirigir `pruebas.cobaemex.edu.mx` a producción. Este cambio conserva los certificados y las demás directivas de Apache, hace un respaldo de `httpd-vhosts.conf`, añade redirecciones temporales HTTP 302 para los dos VirtualHost de pruebas y valida `httpd.exe -t`. No modifica el VirtualHost de producción ni reinicia Apache.

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File '.\deploy\windows-server2022\redirect-pruebas-to-production.ps1'
```

Después de revisar la ruta del respaldo y reiniciar Apache desde XAMPP, verificar:

```powershell
& 'C:\xampp\apache\bin\httpd.exe' -t
curl.exe -I https://pruebas.cobaemex.edu.mx/acceso
curl.exe -I https://ccyf.cobaemex.edu.mx/acceso
```

El primer resultado debe tener `Location: https://ccyf.cobaemex.edu.mx/acceso`. El segundo debe responder desde Laravel sin redirección al dominio de pruebas. La redirección 302 facilita revertirla mientras se comprueba; puede cambiarse a 301 cuando se decida mantenerla de forma permanente.
