# CCyF · Laravel 12

Nueva aplicación para el Concurso de Cafetería y Fotocopiado de CoBaEMex. La copia PHP original está en `D:\wamp64\www\ccyf`; este proyecto permanece separado para migrar y comprobar cada flujo.

## Estado

Funcionan el acceso con código por correo, los catálogos de CCyF, planteles, convocatorias, nuevo registro, revisión de propuestas, resultados y exportaciones PDF/Excel. La gestión de usuarios y roles ya usa tablas locales de Laravel. La evaluación especializada, contratos y reportes restantes siguen pendientes. Véase [el registro de migración](docs/MIGRACION.md).

## Verificación local

```powershell
php artisan ccyf:preflight --check-db
php artisan migrate --force
php artisan ccyf:import-identity
php artisan test
```

`ccyf:import-identity` se ejecuta una sola vez: copia usuarios, roles y permisos de CCyF a SQLite conservando los ID y las contraseñas existentes. Si ya hay cuentas locales, termina sin sobrescribirlas. Las ediciones posteriores se guardan solo en SQLite. La conexión `legacy` tiene permisos de lectura; no ejecutar `migrate` sobre `ccyf`.

El correo usa `MAIL_MAILER=log` durante las pruebas locales. El código se registra en `storage/logs/laravel.log`; no se entrega por correo real.

Apache tiene un host local en `127.0.0.1:8082` cuyo `DocumentRoot` es la carpeta `public` de este proyecto. El `.htaccess` de la raíz deniega acceso directo al código y a `.env`.
