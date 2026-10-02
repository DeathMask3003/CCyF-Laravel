# Despliegue de CCyF Laravel 12 en Windows Server 2022

## Alcance de datos acordado

- El código se publicará desde `https://github.com/DeathMask3003/CCyF-Laravel`.
- La instalación de ensayo en Windows Server utiliza `ccyflaravel`, una copia de la base original. El corte definitivo crea `ccyflaravel_prod` desde un respaldo nuevo de `ccyf`; las tablas históricas permanecen y las tablas `ccyf_*` y `migrations` se crean allí después de verificar el respaldo.
- No se trasladarán la base SQLite local, sus registros de prueba, archivos subidos durante las pruebas, cachés ni el `.env` local.
- Los expedientes y recursos históricos de producción sí se copiarán o conservarán en rutas protegidas del servidor y se configurarán mediante `CCYF_LEGACY_*_ROOT`.
- Git transporta el código. La base de datos y los archivos de expedientes requieren respaldo y traslado por separado.

## Preparación antes del corte

1. Instalar el checkout Laravel en `C:\xampp\htdocs\ccyf-laravel`, junto a la instalación PHP original. Si el original no está en `C:\xampp\htdocs\ccyf`, ajustar las rutas antes de copiar archivos.
2. Respaldar y comprobar la restauración de la base MySQL y de los archivos históricos. Ensayar el despliegue completo con una copia aislada de ambos.
3. En el ensayo, configurar `DB_CONNECTION=mysql` y `DB_*` con una cuenta capaz de crear y modificar las nuevas tablas; configurar `LEGACY_DB_*` hacia la misma base con una cuenta de solo lectura. La conexión `legacy` mantiene las consultas históricas separadas de las escrituras de Laravel.
4. Ejecutar las migraciones sobre la **copia aislada**. Después importar identidad, perfiles, catálogos, estructura, permisos y seguimiento mediante los comandos `ccyf:import-*`, verificando conteos y permisos. `ccyf:import-identity` solo importa si las tablas locales de identidad están vacías.
5. Configurar el `.env` del servidor con `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` HTTPS, `APP_KEY` propio, MySQL, correo real y rutas históricas. No copiar el `.env` local ni usar `MAIL_MAILER=log` para correos reales.
6. Verificar que las rutas de archivos históricos estén disponibles para la cuenta del servidor web. Los documentos nuevos se almacenarán en `storage/app/private`; esa carpeta debe permanecer fuera del directorio público y ser escribible.
7. Probar inicio de sesión, permisos por rol, consultas de expedientes, PDF, envío de correo y carga/descarga de archivos en el ensayo. Revisar registros y los nombres de archivo que se referencian en la base.
8. Acordar una ventana de corte para evitar escrituras simultáneas en el sistema antiguo y en Laravel. Repetir respaldo y ejecutar en producción solo la secuencia ya comprobada. Mantener respaldo y procedimiento de vuelta al sistema anterior.

## Preparación específica para XAMPP

Para una instalación nueva por Git, abrir PowerShell **en el Windows Server** y ejecutar:

```powershell
Set-Location 'C:\xampp\htdocs'
git clone --branch main https://github.com/DeathMask3003/CCyF-Laravel.git ccyf-laravel
Set-Location 'C:\xampp\htdocs\ccyf-laravel'
powershell.exe -NoProfile -ExecutionPolicy Bypass -File '.\deploy\windows-server2022\install-on-xampp.ps1' -UseExistingCheckout
```

El instalador verifica el repositorio y la rama, instala dependencias con PHP de XAMPP, crea `.env` si falta y genera `APP_KEY` solo si está vacía. Conserva un `.env` existente para reanudar una instalación interrumpida. Se detiene si hay cambios locales. También puede ejecutarse fuera del checkout sin `-UseExistingCheckout` para que haga el clon. No migra la base, no copia expedientes ni cambia Apache; esos pasos requieren verificar el estado real del servidor.

El XAMPP de destino informó PHP 8.2.12. El archivo de dependencias fija ZipStream 3.1.2, compatible con PHP 8.2. Si el primer intento terminó durante Composer, no clonar de nuevo ni ejecutar `composer update` en el servidor: dentro de `C:\xampp\htdocs\ccyf-laravel`, ejecutar `git pull --ff-only` y repetir el instalador con `-UseExistingCheckout`.

1. En Windows Server verificar que el PHP usado por Apache coincide con `C:\xampp\php\php.exe`. Ejecutar `deploy/windows-server2022/check-server.ps1` una vez creados `.env` y la carpeta de expedientes. Confirmar los requisitos de plataforma con `composer check-platform-reqs --no-dev`.
2. En el VirtualHost HTTPS existente, establecer `DocumentRoot "C:/xampp/htdocs/ccyf-laravel/public"` y un bloque `<Directory "C:/xampp/htdocs/ccyf-laravel/public">` con `AllowOverride All` y `Require all granted`. Habilitar `mod_rewrite`. Mantener la carpeta `ccyf-laravel` y su `.env` fuera de la raíz pública; el archivo `.htaccess` de la raíz del proyecto también deniega acceso directo.
3. Conservar el código PHP original sin sobrescribirlo. Copiar los expedientes de ese sistema a `C:\xampp\ccyf-legacy-files`, fuera de `htdocs`, usando `deploy/windows-server2022/copy-legacy-files.ps1 -SourceRoot 'C:\xampp\htdocs\ccyf' -DestinationRoot 'C:\xampp\ccyf-legacy-files'`. El script no borra el origen ni archivos del destino. Verificar permisos de lectura del usuario de Apache y restringir el acceso a firmas y expedientes.
4. Copiar `deploy/windows-server2022/env.production.example` a `.env` **solo en el servidor**; completar credenciales, clave heredada, remitente de correo y rutas reales. Tras instalar Composer, generar `APP_KEY` una sola vez con `php artisan key:generate --force` y conservarlo en futuras versiones. Las conexiones `DB_*` y `LEGACY_DB_*` apuntan ambas a `ccyflaravel_prod`, con usuarios de escritura y lectura respectivamente.
5. Instalar dependencias con `composer install --no-dev --prefer-dist --optimize-autoloader`. No se requiere trasladar `vendor`, `node_modules`, SQLite ni `storage/app/private` desde desarrollo. Los recursos de interfaz usados actualmente están versionados en `public`.
6. Dar escritura al usuario de Apache en `storage` y `bootstrap/cache`. Mantener `storage/app/private` fuera del acceso web. No ejecutar `php artisan storage:link` para los expedientes privados.
7. Confirmar primero que `ccyflaravel_prod` se creó desde un respaldo nuevo de `ccyf`, contiene las tablas históricas esperadas y no contiene todavía tablas `ccyf_*` ni `migrations`. Ejecutar `php artisan optimize:clear`, `php artisan ccyf:preflight --check-db` y `php artisan migrate --force`. Después importar, en este orden: `ccyf:import-identity`, `ccyf:import-service-types`, `ccyf:import-document-types`, `ccyf:import-structure`, `ccyf:import-profiles-locations`, `ccyf:import-documentacion-permisos`, `ccyf:import-prevaluacion-permisos`, `ccyf:import-contratos-permisos`, `ccyf:import-permisionarios`. Comprobar conteos y abrir expedientes antes de habilitar el sitio al público.
8. Activar cachés de configuración y vistas una vez comprobado `.env`. Antes de habilitar avisos automáticos, verificar el SMTP con `php artisan ccyf:mail-test correo-de-control@dominio.example` y confirmar la recepción. En pruebas conservar `MAIL_MAILER=log`; cambiar a `smtp` solo en el corte de producción. Verificar PDF, autenticación, Turnstile y Google con las claves y URL del dominio definitivo.

## Corte de `pruebas.cobaemex.edu.mx` a producción

No basta con cambiar `APP_URL`: la base `ccyflaravel` usada durante el ensayo contiene datos de prueba y no se promociona. Antes de publicar, detener las escrituras en el sistema anterior, respaldar `ccyf` y `ccyflaravel`, crear `ccyflaravel_prod` desde el respaldo nuevo de `ccyf` y repetir las migraciones e importaciones ya comprobadas. Confirmar también que los directorios históricos están completos y que Apache puede leerlos.

1. Acordar la ventana de corte y conservar accesible el sistema anterior para una vuelta atrás. Tomar un respaldo nuevo de la base activa `ccyf`, de la base de ensayo `ccyflaravel`, de `C:\xampp\ccyf-legacy-files`, del `.env` y de `httpd-vhosts.conf`. Verificar que los respaldos se pueden abrir y anotar sus rutas. No borrar ni sobrescribir `ccyf` o `ccyflaravel`.
2. En `C:\xampp\htdocs\ccyf-laravel`, comprobar que Git está limpio y ejecutar `git pull --ff-only`, `composer install --no-dev --prefer-dist --optimize-autoloader`, `php artisan migrate:status`, `php artisan migrate --force` y `php artisan ccyf:preflight --check-db`. No continuar si cualquier comando falla.
3. Cambiar el `.env` a `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://ccyf.cobaemex.edu.mx`, `SESSION_COOKIE=ccyf_laravel_session`, `SESSION_SECURE_COOKIE=true` y `MAIL_MAILER=smtp`. Usar las claves de Turnstile del widget de producción, activar `CCYF_TURNSTILE_ENABLED=true`, activar `CCYF_GOOGLE_LOGIN_ENABLED=true` y establecer `GOOGLE_REDIRECT_URI=https://ccyf.cobaemex.edu.mx/acceso/google/callback`. No reutilizar las claves del widget de pruebas.
4. Confirmar los destinatarios `CCYF_MAIL_LEGAL`, `CCYF_MAIL_MANAGEMENT`, `CCYF_MAIL_CCYF` y `CCYF_MAIL_ACCOUNTING`. Ejecutar `php artisan optimize:clear`, `deploy\windows-server2022\check-server.ps1` y un `ccyf:mail-test` dirigido únicamente al buzón de control; verificar la recepción antes de continuar.
5. Ejecutar `deploy\windows-server2022\promote-production-vhost.ps1`. El script exige la configuración completa de producción, modifica únicamente los bloques `:80` y `:443` de `ccyf.cobaemex.edu.mx`, crea un respaldo de `httpd-vhosts.conf`, valida la sintaxis y no reinicia Apache.
6. Revisar la ruta del respaldo indicada por el script y ejecutar `C:\xampp\apache\bin\httpd.exe -S`. Reiniciar Apache desde XAMPP únicamente dentro de la ventana acordada.
7. En una ventana privada, verificar `https://ccyf.cobaemex.edu.mx/acceso`, acceso con contraseña, Google, alta de cuenta con Turnstile, un usuario administrativo, un permisionario, un PDF histórico, carga/descarga privada y un envío controlado. Revisar `storage\logs\laravel.log` y confirmar que el correo llegó; aceptación SMTP por sí sola no prueba entrega.
8. Cuando producción quede confirmada, redirigir `pruebas.cobaemex.edu.mx` a `https://ccyf.cobaemex.edu.mx` o retirarlo. No dejar la misma instalación de producción accesible de forma permanente bajo el dominio de pruebas porque Google y Turnstile validan el host definitivo.

Si una comprobación crítica falla, restaurar el `.env` y el `httpd-vhosts.conf` respaldados, ejecutar `php artisan optimize:clear`, validar con `httpd.exe -t`, reiniciar Apache y comprobar que el sistema anterior volvió a responder. Restaurar la base solo con el procedimiento de recuperación acordado; no sobrescribirla improvisadamente durante el incidente.

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
