# CCyF · Laravel 12

Aplicación del Concurso de Cafetería y Fotocopiado de COBAEM. El proyecto Laravel se desarrolla en `D:\wamp64\www\ccyf-laravel`; la copia del sistema PHP original está en `D:\wamp64\www\ccyf`.

## Desarrollo local

La instalación local usa SQLite y archivos de prueba. Su `.env`, la base SQLite y `storage/app/private` no se publican por Git.

```powershell
php artisan ccyf:preflight --check-db
php artisan test
```

El correo local usa `MAIL_MAILER=log`, por lo que las pruebas no envían correos reales. Apache sirve la carpeta `public` en `http://localhost:8082`.

## Servidor de pruebas y producción

La instalación de pruebas en Windows Server usa `ccyflaravel`, una copia de la base de producción, y los expedientes históricos copiados desde el servidor original. **No se trasladan los registros ni archivos de prueba locales.** Git publica únicamente el código; la base y los archivos se respaldan y preparan por separado. El acceso HTTPS de pruebas se prepara en [la guía del servidor de pruebas](docs/PRUEBAS_WINDOWS_SERVER.md). El eventual cambio del dominio público se describe en [la guía de despliegue](docs/DESPLIEGUE_PRODUCCION.md).

El [registro de migración](docs/MIGRACION.md) describe decisiones de la fase local inicial; no sustituye la guía de despliegue en producción.
