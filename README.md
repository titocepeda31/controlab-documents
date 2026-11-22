# Controlab — Mecánica de Suelo (piloto MVC)

Aplicación de prueba con **Slim PHP 4 + MySQL**, formulario web por etapas, dictado por voz, historial de informes y borradores sin conexión.

**ATENCIÓN:** prototipo no validado para emisión de certificados oficiales. El documento impreso sigue siendo provisional. Utilizar datos de ensayo ficticios hasta la validación técnica y de seguridad.

## Estructura

- `mvc/frontend/`: aplicación pública (`formulario.html`, `sw.js`, `index.php`, `.htaccess`).
- `mvc/backend/`: aplicación Slim, rutas, controladores y modelos. **No subir a `public_html`**.
- `mvc/database/`: migración necesaria para sincronización idempotente.
- `scripts/cpanel_mvc_deploy.sh`: despliegue controlado desde cPanel con respaldo previo.
- `.cpanel.yml`: tarea de despliegue para Git Version Control.

## Configuración privada: se reutiliza la que YA existe en el hosting

No existe un `config.php` real en este repositorio. El archivo `mvc/frontend/index.php` carga
`/home/maderas7/demo-informes/config.php` (por medio de `dirname(__DIR__, 2)`) y después
`/home/maderas7/demo-informes/bootstrap.php`. Composer Slim usa las dependencias ya instaladas en
`/home/maderas7/tito-pos-privado/api/vendor/autoload.php`.

El repositorio NO contiene credenciales MySQL, claves SSH ni contraseñas FTP.

## Primera instalación desde cPanel

1. Confirmar que el repositorio esté clonado en `/home/maderas7/repositories/controlab-documents`.
2. Verificar en phpMyAdmin si existe `informe_envios`. Si no existe, ejecutar UNA SOLA VEZ `mvc/database/crear_informe_envios.sql` sobre la base de datos real.
3. Respaldar la carpeta `/home/maderas7/demo-informes` y `/home/maderas7/public_html/demo` (los archivos originales del hosting son la fuente de verdad hasta desplegar).
4. En cPanel > Git Version Control > Manage > Pull or Deploy, **Update from Remote** y luego **Deploy HEAD Commit**. El script comprueba rutas, configuración, dependencias y migración; crea respaldo y copia exclusivamente los archivos permitidos.
5. Comprobar `https://maderasyferreteriasmuena.cl/demo/api/session` y después probar login, inserción, recuperación y sincronización con datos ficticios.

No se copia ni sobrescribe `config.php`, la base de datos ni las dependencias de Tito POS.

> Si el hosting no dispone del ejecutable de PHP en la línea de comandos, el script de despliegue detendrá la operación y se deberá adaptar la comprobación a la instalación de PHP de cPanel.

## Seguridad

- El código fuente actualmente es público. No colocar credenciales reales en commits (ni archivos borrados en commits posteriores).
- Borrar scripts de diagnóstico del directorio público antes del uso real.
- Cambiar la contraseña temporal conocida del usuario administrador.
- Los datos offline permanecen en IndexedDB del navegador: usar equipos de confianza, no eliminar datos pendientes antes de sincronizar.
- No usar imágenes de firma de personas sin su autorización. La firma visual no equivale a una firma electrónica avanzada.

## Funciones pendientes

Generación PDF que preserve la plantilla oficial, control de aprobaciones, archivo versionado de certificados, firma/autorización y envío masivo de informes por SMTP. Ninguno está implementado en este repositorio todavía.
