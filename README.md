# CCyF · Laravel 12

Nueva aplicación para el Concurso de Cafetería y Fotocopiado de CoBaEMex. La copia PHP original está en `D:\wamp64\www\ccyf`; este proyecto permanece separado para migrar y comprobar cada flujo.

## Estado

Funciona el acceso inicial con código por correo y un panel básico. Los módulos de convocatorias, documentos, evaluación, contratos y reportes siguen pendientes de migración. Véase [el registro de migración](docs/MIGRACION.md).

## Verificación local

```powershell
php artisan ccyf:preflight --check-db
php artisan test
php artisan serve --host=127.0.0.1
```

El correo usa `MAIL_MAILER=log` durante las pruebas locales. El código se registra en `storage/logs/laravel.log`; no se entrega por correo real. La conexión `legacy` tiene permisos de lectura. No ejecutar `migrate` sobre `ccyf`.

Si se configura Apache, su `DocumentRoot` debe ser la carpeta `public` de este proyecto. El `.htaccess` de la raíz deniega acceso directo al código y a `.env`.
