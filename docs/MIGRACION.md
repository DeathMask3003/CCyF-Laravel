# Migración de CCyF a Laravel 12

## Alcance observado

La copia heredada se encuentra en `D:\wamp64\www\ccyf`. Contiene 61 áreas de interfaz bajo `alba`, 49 controladores PHP, 52 modelos PHP y un volcado SQL con 84 tablas. El proyecto Laravel se mantiene separado en `D:\wamp64\www\ccyf-laravel` y está fijado a Laravel 12.

Los flujos centrales identificados son acceso y roles, convocatorias, registro de concursantes, documentación, preevaluación, evaluación, calificación, asignación, contratos, seguimiento, quejas, reportes PDF y bitácora. También existen módulos compartidos de WIDI y servicios externos. La paridad de cada flujo requiere contrastar formularios, permisos, consultas, archivos y resultados con la copia heredada.

## Límites de datos

- `ccyf.sql` contiene datos personales y credenciales cifradas. Permanece fuera del repositorio Laravel.
- La carpeta heredada permanece sin cambios.
- No ejecutar migraciones de Laravel sobre la base `ccyf` existente. La conexión `legacy` usa una cuenta local limitada a `SELECT` durante esta fase.
- Antes de probar escrituras, restaurar una copia aislada con nombre propio y configurar credenciales específicas para ella.
- Conservar archivos adjuntos y PDFs heredados fuera de `public`; exponerlos después mediante rutas autorizadas por usuario y rol.
- Apache debe apuntar exclusivamente a `D:\wamp64\www\ccyf-laravel\public`. La raíz del proyecto contiene un `.htaccess` que niega acceso web directo, incluido `.env`.

## Comprobación inicial

Configurar `CCYF_LEGACY_ROOT` en `.env` y ejecutar:

```powershell
php artisan ccyf:preflight
```

La opción `--check-db` consulta únicamente la existencia de tablas cuando `LEGACY_DB_*` apunta a una copia aislada. No realiza operaciones de escritura.

## Estado de esta entrega

- Laravel 12.69.2 instalado y conexión local al esquema de 84 tablas comprobada.
- Inicio de sesión compatible con contraseñas heredadas, seguido de un código por correo. La sesión autenticada comienza únicamente después del código; hay vencimiento y límite de intentos.
- El correo local usa `MAIL_MAILER=log`, por lo que no se envían mensajes reales durante las pruebas actuales.
- El panel inicial permite validar el acceso. Los módulos de negocio aún no están disponibles en Laravel.
- Las pruebas automatizadas usan una base SQLite ficticia y no alteran la copia `ccyf`.

## Orden de trabajo

1. Separar la base Laravel de la base heredada y comprobar esquema y archivos.
2. Migrar acceso, recuperación, segundo factor y permisos por rol sin rebajar la protección actual.
3. Migrar convocatorias, concursantes y documentación.
4. Migrar preevaluación, evaluación, selección, contratos y seguimiento.
5. Migrar reportes, bitácora, quejas y servicios externos.
6. Validar paridad funcional y permisos con una copia aislada de producción antes de publicar.
