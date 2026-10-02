# Despliegue de CCyF Laravel 12 en Windows Server 2022

## Alcance de datos acordado

- El código se publicará desde `https://github.com/DeathMask3003/CCyF-Laravel`.
- La instalación de ensayo en Windows Server utiliza `C:\xampp\htdocs\ccyf-laravel` y `ccyflaravel`. Producción usa un checkout separado en `C:\xampp\htdocs\ccyf-laravel-prod`, una copia separada de expedientes en `C:\xampp\ccyf-production-files` y `ccyflaravel_prod`, creada desde un respaldo nuevo de la base activa `ccyf`.
- No se trasladarán la base SQLite local, sus registros de prueba, archivos subidos durante las pruebas, cachés ni el `.env` local.
- Los expedientes y recursos históricos de producción sí se copiarán o conservarán en rutas protegidas del servidor y se configurarán mediante `CCYF_LEGACY_*_ROOT`.
- Git transporta el código. La base de datos y los archivos de expedientes requieren respaldo y traslado por separado.

## Preparación antes del corte

1. Instalar el checkout de producción en `C:\xampp\htdocs\ccyf-laravel-prod`, separado del checkout de pruebas y del sistema original. Si el original no está en `C:\xampp\htdocs\ccyf`, ajustar las rutas antes de copiar archivos.
2. Respaldar y comprobar la restauración de la base MySQL y de los archivos históricos. Ensayar el despliegue completo con una copia aislada de ambos.
3. En el ensayo, configurar `DB_CONNECTION=mysql` y `DB_*` con una cuenta capaz de crear y modificar las nuevas tablas; configurar `LEGACY_DB_*` hacia la misma base con una cuenta de solo lectura. La conexión `legacy` mantiene las consultas históricas separadas de las escrituras de Laravel.
4. Ejecutar las migraciones sobre la **copia aislada**. Después importar identidad, perfiles, catálogos, estructura, permisos y seguimiento mediante los comandos `ccyf:import-*`, verificando conteos y permisos. `ccyf:import-identity` solo importa si las tablas locales de identidad están vacías.
5. Configurar el `.env` del servidor con `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` HTTPS, `APP_KEY` propio, MySQL, correo real y rutas históricas. No copiar el `.env` local ni usar `MAIL_MAILER=log` para correos reales.
6. Verificar que las rutas de archivos históricos estén disponibles para la cuenta del servidor web. Los documentos nuevos se almacenarán en `storage/app/private`; esa carpeta debe permanecer fuera del directorio público y ser escribible.
7. Probar inicio de sesión, permisos por rol, consultas de expedientes, PDF, envío de correo y carga/descarga de archivos en el ensayo. Revisar registros y los nombres de archivo que se referencian en la base.
8. Acordar una ventana de corte para evitar escrituras simultáneas en el sistema antiguo y en Laravel. Repetir respaldo y ejecutar en producción solo la secuencia ya comprobada. Mantener respaldo y procedimiento de vuelta al sistema anterior.

## Preparación específica para XAMPP

Para el checkout de producción, abrir PowerShell **en el Windows Server** y ejecutar:

```powershell
Set-Location 'C:\xampp\htdocs'
git clone --branch main https://github.com/DeathMask3003/CCyF-Laravel.git ccyf-laravel-prod
Set-Location 'C:\xampp\htdocs\ccyf-laravel-prod'
powershell.exe -NoProfile -ExecutionPolicy Bypass -File '.\deploy\windows-server2022\install-on-xampp.ps1' -UseExistingCheckout -ProjectRoot 'C:\xampp\htdocs\ccyf-laravel-prod'
```

El instalador verifica el repositorio y la rama, instala dependencias con PHP de XAMPP, crea `.env` si falta y genera `APP_KEY` solo si está vacía. Conserva un `.env` existente para reanudar una instalación interrumpida. Se detiene si hay cambios locales. También puede ejecutarse fuera del checkout sin `-UseExistingCheckout` para que haga el clon. No migra la base, no copia expedientes ni cambia Apache; esos pasos requieren verificar el estado real del servidor.

El XAMPP de destino informó PHP 8.2.12. El archivo de dependencias fija ZipStream 3.1.2, compatible con PHP 8.2. Si el primer intento terminó durante Composer, no clonar de nuevo ni ejecutar `composer update` en el servidor: dentro de `C:\xampp\htdocs\ccyf-laravel`, ejecutar `git pull --ff-only` y repetir el instalador con `-UseExistingCheckout`.

1. En Windows Server verificar que el PHP usado por Apache coincide con `C:\xampp\php\php.exe`. Ejecutar `deploy/windows-server2022/check-server.ps1 -ProjectRoot 'C:\xampp\htdocs\ccyf-laravel-prod' -LegacyFilesRoot 'C:\xampp\ccyf-production-files'` una vez creados `.env` y la carpeta de expedientes. Confirmar los requisitos de plataforma con `composer check-platform-reqs --no-dev`.
2. Al llegar al corte, el script `promote-production-vhost.ps1` establecerá `DocumentRoot "C:/xampp/htdocs/ccyf-laravel-prod/public"` y el bloque `<Directory>` correspondiente. Mantener el `.env` fuera de la raíz pública; no modificar el VirtualHost manualmente antes de preparar la base.
3. Conservar el código PHP original sin sobrescribirlo. Copiar los expedientes a `C:\xampp\ccyf-production-files`, fuera de `htdocs`, usando `deploy/windows-server2022/copy-legacy-files.ps1 -SourceRoot 'C:\xampp\htdocs\ccyf' -DestinationRoot 'C:\xampp\ccyf-production-files'`. El script no borra el origen ni archivos del destino. Verificar permisos de lectura de Apache.
4. Copiar `deploy/windows-server2022/env.production.example` a `.env` **solo en el servidor**; completar credenciales, clave heredada, remitente de correo y rutas reales. Tras instalar Composer, generar `APP_KEY` una sola vez con `php artisan key:generate --force` y conservarlo en futuras versiones. Las conexiones `DB_*` y `LEGACY_DB_*` apuntan ambas a `ccyflaravel_prod`, con usuarios de escritura y lectura respectivamente.
5. Instalar dependencias con `composer install --no-dev --prefer-dist --optimize-autoloader`. No se requiere trasladar `vendor`, `node_modules`, SQLite ni `storage/app/private` desde desarrollo. Los recursos de interfaz usados actualmente están versionados en `public`.
6. Dar escritura al usuario de Apache en `storage` y `bootstrap/cache`. Mantener `storage/app/private` fuera del acceso web. No ejecutar `php artisan storage:link` para los expedientes privados.
7. Confirmar primero que `ccyflaravel_prod` se creó desde un respaldo nuevo de `ccyf`, contiene las tablas históricas esperadas y no contiene todavía tablas `ccyf_*` ni `migrations`. Ejecutar `php artisan optimize:clear`, `php artisan ccyf:preflight --check-db` y `php artisan migrate --force`. Después importar, en este orden: `ccyf:import-identity`, `ccyf:import-service-types`, `ccyf:import-document-types`, `ccyf:import-structure`, `ccyf:import-profiles-locations`, `ccyf:import-documentacion-permisos`, `ccyf:import-prevaluacion-permisos`, `ccyf:import-contratos-permisos`, `ccyf:import-permisionarios`. Comprobar conteos y abrir expedientes antes de habilitar el sitio al público.
8. Activar cachés de configuración y vistas una vez comprobado `.env`. Antes de habilitar avisos automáticos, verificar el SMTP con `php artisan ccyf:mail-test correo-de-control@dominio.example` y confirmar la recepción. En pruebas conservar `MAIL_MAILER=log`; cambiar a `smtp` solo en el corte de producción. Verificar PDF, autenticación, Turnstile y Google con las claves y URL del dominio definitivo.

## Corte del dominio público a Laravel

`ccyflaravel` y `C:\xampp\htdocs\ccyf-laravel` contienen pruebas. No se cambian a producción ni se usan como origen de datos. El CCyF original sigue en `C:\xampp\htdocs\ccyf` hasta el cambio de VirtualHost.

### Preparar sin afectar el sitio público

1. Crear un checkout independiente con `install-on-xampp.ps1 -ProjectRoot 'C:\xampp\htdocs\ccyf-laravel-prod'`. Este instala el código y crea el `.env` de ejemplo; no cambia Apache ni migra bases.
2. Configurar ese `.env` únicamente en el servidor: `APP_ENV=production`, `APP_URL=https://ccyf.cobaemex.edu.mx`, `APP_DEBUG=false`, `DB_DATABASE=ccyflaravel_prod`, `LEGACY_DB_DATABASE=ccyflaravel_prod`, `MAIL_MAILER=smtp` y cookie propia. Usar credenciales, `APP_KEY` y clave heredada de producción. No copiar el `.env`, `storage/app/private` ni cargas de `pruebas.cobaemex.edu.mx`.
3. Registrar `https://ccyf.cobaemex.edu.mx/acceso/google/callback` en el cliente OAuth. Preparar las claves del widget Turnstile para `ccyf.cobaemex.edu.mx`; no usar las de pruebas. Confirmar SMTP, remitente y destinatarios reales. Mantener las claves solo en el `.env` del servidor.
4. Preparar `C:\xampp\ccyf-production-files` con `copy-legacy-files.ps1 -SourceRoot 'C:\xampp\htdocs\ccyf' -DestinationRoot 'C:\xampp\ccyf-production-files'`. Repetir la copia durante el corte para incorporar archivos recientes. Apache debe poder leer esa carpeta; `storage` y `bootstrap/cache` del nuevo checkout requieren escritura.
5. Comprobar el espacio libre para un respaldo reciente de `ccyf`, su restauración en `ccyflaravel_prod`, la copia de expedientes y una vuelta atrás. Verificar que la base nueva no existe o está vacía antes de restaurar. No sobrescribir `ccyf` ni `ccyflaravel`.

### Durante la ventana de corte

1. Detener la creación y edición de registros en el CCyF original. Conservar su código y VirtualHost intactos. Respaldar la base activa `ccyf`, los expedientes originales y `httpd-vhosts.conf`. Comprobar que el respaldo SQL es legible y registrar su ruta y hora.
2. Restaurar **ese respaldo reciente**, no la base de pruebas, en `ccyflaravel_prod`. Confirmar las tablas históricas y que aún no existen `migrations` ni `ccyf_usuarios`. Si la base ya contiene tablas Laravel, detenerse y revisar el origen de los datos.
3. Sin abrir todavía el dominio público, ejecutar en `C:\xampp\htdocs\ccyf-laravel-prod`: `php artisan optimize:clear`, `php artisan ccyf:preflight --check-db`, `php artisan migrate --force` y, en este orden, `ccyf:import-identity`, `ccyf:import-service-types`, `ccyf:import-document-types`, `ccyf:import-structure`, `ccyf:import-profiles-locations`, `ccyf:import-documentacion-permisos`, `ccyf:import-prevaluacion-permisos`, `ccyf:import-contratos-permisos`, `ccyf:import-permisionarios`. Verificar usuarios, convocatorias, permisos, PDFs y expedientes históricos.
4. Sincronizar de nuevo los archivos del original a `C:\xampp\ccyf-production-files`. Verificar el correo con `ccyf:mail-test` enviado solo a un buzón de control y comprobar recepción. Comprobar que Google, Turnstile y las cuatro direcciones institucionales están configurados para producción.
5. Ejecutar `promote-production-vhost.ps1 -CheckOnly` desde el checkout nuevo. Solo si indica la ruta `ccyf-laravel-prod/public`, ejecutar el mismo script sin `-CheckOnly`. Este respalda `httpd-vhosts.conf`, cambia solo los VirtualHost de `ccyf.cobaemex.edu.mx`, valida Apache y no lo reinicia.
6. Reiniciar Apache desde XAMPP dentro de la ventana. Comprobar `httpd.exe -S` y `https://ccyf.cobaemex.edu.mx/acceso` en una ventana privada. Probar acceso administrativo y de permisionario, Google, Turnstile, un PDF histórico, una carga y descarga privadas y un envío controlado. Revisar `storage\logs\laravel.log` sin compartir credenciales.

Si una comprobación crítica falla, restaurar el `httpd-vhosts.conf` que informó el script, validar con `httpd.exe -t`, reiniciar Apache y comprobar que vuelve el sistema original. Mantener la base `ccyflaravel_prod` separada para diagnóstico; no sobrescribir la base `ccyf` durante la vuelta atrás.

La instalación de pruebas puede permanecer en su propio dominio y base mientras se revisa producción. No se debe apuntar `pruebas.cobaemex.edu.mx` al checkout de producción.

El código original está en `C:\xampp\htdocs\ccyf`. El ensayo con DNS y certificado propio se prepara en [la guía de pruebas](PRUEBAS_WINDOWS_SERVER.md). El dominio de producción continúa dirigido al sistema original mientras se valida la instalación Laravel.

## Directorios históricos usados por Laravel

Los valores siguientes son configurables y deben apuntar a archivos **de producción**, no a la copia de pruebas:

| Variable | Contenido histórico |
| --- | --- |
| `CCYF_LEGACY_FILES_ROOT` | `assets/documents` del sistema original |
| `CCYF_LEGACY_REPORTS_ROOT` | `reportes` del sistema original |
| `CCYF_LEGACY_SIGNATURES_ROOT` | `ccyf/e-signs` del sistema original |
| `CCYF_LEGACY_PROFILE_PHOTOS_ROOT` | `assets/images/users` del sistema original |
| `CCYF_LEGACY_COMPLAINTS_ROOT` | `assets/quejas` del sistema original |

## Estado de verificación (29 de septiembre de 2026)

- Git ya instaló Laravel 12.69.2 en `C:\xampp\htdocs\ccyf-laravel` con PHP 8.2.12 de XAMPP. El entorno del servidor es `staging`, usa `MAIL_MAILER=log` y apunta a la copia `ccyflaravel`.
- En la copia del servidor se importaron 398 usuarios, 83 planteles, 11 convocatorias y 2 seguimientos históricos. Se confirmó el acceso con una cuenta administrativa y una de permisionario de prueba, además de la apertura de un PDF.
- La cuenta de permisionario de prueba no tiene registros nuevos en esa copia. Los registros creados solo en desarrollo no se transportan por Git.
- Queda por confirmar la copia completa de los directorios históricos y los permisos de escritura de Apache. La publicación HTTPS en `pruebas.cobaemex.edu.mx` y Google tienen su propia secuencia de verificación antes de cualquier cambio del dominio de producción.
