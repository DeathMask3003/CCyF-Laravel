# Despliegue de CCyF Laravel 12 en Windows Server 2022

## Alcance de datos acordado

- El código se publicará desde `https://github.com/DeathMask3003/CCyF-Laravel`.
- Laravel utilizará la base MySQL **de producción existente**. Las tablas históricas permanecen; las nuevas tablas `ccyf_*` y la tabla `migrations` se crearán mediante migraciones tras un ensayo sobre una copia aislada.
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

1. En Windows Server verificar que el PHP usado por Apache coincide con `C:\xampp\php\php.exe`. Ejecutar `deploy/windows-server2022/check-server.ps1` una vez creados `.env` y la carpeta de expedientes. Confirmar los requisitos de plataforma con `composer check-platform-reqs --no-dev`.
2. En el VirtualHost HTTPS existente, establecer `DocumentRoot "C:/xampp/htdocs/ccyf-laravel/public"` y un bloque `<Directory "C:/xampp/htdocs/ccyf-laravel/public">` con `AllowOverride All` y `Require all granted`. Habilitar `mod_rewrite`. Mantener la carpeta `ccyf-laravel` y su `.env` fuera de la raíz pública; el archivo `.htaccess` de la raíz del proyecto también deniega acceso directo.
3. Conservar el código PHP original sin sobrescribirlo. Copiar los expedientes de ese sistema a `C:\xampp\ccyf-legacy-files`, fuera de `htdocs`, usando `deploy/windows-server2022/copy-legacy-files.ps1 -SourceRoot 'C:\xampp\htdocs\ccyf' -DestinationRoot 'C:\xampp\ccyf-legacy-files'`. El script no borra el origen ni archivos del destino. Verificar permisos de lectura del usuario de Apache y restringir el acceso a firmas y expedientes.
4. Copiar `deploy/windows-server2022/env.production.example` a `.env` **solo en el servidor**; completar credenciales, clave heredada, remitente de correo y rutas reales. Tras instalar Composer, generar `APP_KEY` una sola vez con `php artisan key:generate --force` y conservarlo en futuras versiones. Las conexiones `DB_*` y `LEGACY_DB_*` apuntan a la misma base de producción con usuarios de escritura y lectura respectivamente.
5. Instalar dependencias con `composer install --no-dev --prefer-dist --optimize-autoloader`. No se requiere trasladar `vendor`, `node_modules`, SQLite ni `storage/app/private` desde desarrollo. Los recursos de interfaz usados actualmente están versionados en `public`.
6. Dar escritura al usuario de Apache en `storage` y `bootstrap/cache`. Mantener `storage/app/private` fuera del acceso web. No ejecutar `php artisan storage:link` para los expedientes privados.
7. Tras el ensayo y respaldo, ejecutar `php artisan optimize:clear`, `php artisan ccyf:preflight --check-db` y `php artisan migrate --force`. Después importar, en este orden: `ccyf:import-identity`, `ccyf:import-service-types`, `ccyf:import-document-types`, `ccyf:import-structure`, `ccyf:import-profiles-locations`, `ccyf:import-documentacion-permisos`, `ccyf:import-prevaluacion-permisos`, `ccyf:import-contratos-permisos`, `ccyf:import-permisionarios`. Comprobar conteos y abrir expedientes antes de habilitar el sitio al público.
8. Activar cachés de configuración y vistas una vez comprobado `.env`. Verificar correo SMTP, PDF, autenticación, captcha y Google solo cuando estén configurados los servicios correspondientes.

La ruta concreta del código original y las credenciales del Windows Server aún deben confirmarse allí. El repositorio remoto debe contener el commit preparado localmente antes de clonar en el servidor.

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

- El proyecto local sigue configurado con SQLite para desarrollo. La base SQLite y `storage/app/private` están excluidos de Git.
- La copia local de la base histórica tiene 84 tablas y no contiene `ccyf_*`, `migrations` ni las tablas internas `cache`, `jobs` y `sessions`.
- El usuario `legacy` local tiene únicamente permiso `SELECT` sobre `ccyf`. Un esquema MySQL 8.3 aislado recibió todas las migraciones con InnoDB y los importadores iniciales. El ensayo importó 398 usuarios, 83 planteles, 9 convocatorias y 2 seguimientos históricos. Quedaron cero registros nuevos y cero archivos de prueba. Se corrigieron un índice de nombre excesivo y fechas cero heredadas durante el ensayo.
- El ensayo es sobre la copia local de datos históricos. Falta verificar PHP, XAMPP, SMTP, rutas y permisos en el Windows Server real.
- El esquema temporal del ensayo se eliminó al terminar; la base histórica `ccyf` permanece disponible.
- El repositorio remoto responde por Git y no publica referencias todavía. El código quedó en un commit local; no se ha subido ni se ha modificado el servidor de producción.
