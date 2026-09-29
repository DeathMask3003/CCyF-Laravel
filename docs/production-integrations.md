# Activación de Turnstile y Google en producción

Ambas integraciones están apagadas de forma predeterminada. El código exige `APP_ENV=production` además del interruptor individual. En desarrollo y pruebas locales no se muestra el widget de Turnstile ni el acceso con Google, aunque se configuren claves por accidente.

## Turnstile

1. Crear un widget para el dominio público de CCyF en Cloudflare y obtener su clave pública y secreta.
2. Configurar `APP_URL` con la URL HTTPS pública exacta. El servidor compara el `hostname` de la respuesta de Cloudflare con ese dominio y comprueba la acción `login`, `register` o `submit_registration`.
3. Establecer `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` y `CCYF_TURNSTILE_ENABLED=true` en el entorno de producción.
4. Limpiar la caché de configuración y probar el inicio de sesión, alta de cuenta y envío final de un registro. Guardar un borrador no exige captcha.

La validación es obligatoria en el servidor. Si Cloudflare no responde, falta una clave, o la acción o dominio no coinciden, el envío se rechaza. Nunca publicar la clave secreta.

## Google

1. Crear un cliente OAuth web en Google Cloud, configurar la pantalla de consentimiento y autorizar el origen HTTPS público.
2. Registrar como URI de redirección exacta `https://DOMINIO_PUBLICO/acceso/google/callback`.
3. Establecer `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` y `CCYF_GOOGLE_LOGIN_ENABLED=true` en producción. Limpiar la caché de configuración.
4. Probar con una cuenta activa existente. Un correo `@gmail.com` verificado, o un dominio de Google Workspace confirmado por Google, puede vincularse automáticamente cuando existe una sola cuenta local con ese correo. Los demás usuarios deben entrar con contraseña y vincular Google desde **Mi perfil** usando el mismo correo.

La aplicación guarda únicamente el identificador estable de Google; no guarda el token OAuth. No se crea ninguna cuenta nueva desde el botón de Google. Si la cuenta local o su rol están inactivos, el acceso se rechaza.
