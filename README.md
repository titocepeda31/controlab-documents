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

## Revisión y firma visual (piloto)

La versión actual añade revisión del registro provisional, **sin cambiar el diseño de impresión que ya se utilizaba**:

1. Ejecutar `mvc/database/crear_revision_firmas.sql` en phpMyAdmin de la base de datos real antes de actualizar cPanel. No modifica las tablas anteriores. Se agregan `informe_revisores`, `informe_revisiones` e `informe_revision_eventos`.
2. Autorizar expresamente a uno o más usuarios revisores, por ejemplo, sustituyendo el correo real:

   ```sql
   INSERT INTO informe_revisores (usuario_id,habilitado)
   SELECT id,1 FROM usuarios WHERE email='CORREO_DEL_REVISOR' AND activo=1
   ON DUPLICATE KEY UPDATE habilitado=1;
   ```

   Si la selección no encuentra al usuario, no habrá revisor autorizado. Verificar el correo en la tabla `usuarios`. No autorizar a usuarios ajenos al laboratorio.
3. En cPanel > Git Version Control > Pull or Deploy, usar **Update from Remote**, seguido de **Deploy HEAD Commit**. La migración debe existir antes de desplegar; el script comprueba las cuatro tablas necesarias y conserva el archivo privado `config.php`.
4. Iniciar sesión: en **Historial**, seleccionar un informe guardado en MySQL y solicitar revisión. El botón **Revisiones** aparece únicamente a revisores autorizados. Pueden aprobar dibujando una firma en pantalla o devolver indicando motivo. Solo el autor puede corregir el informe devuelto, manteniendo su ID; después solicita revisión de nuevo.
5. En **Historial**, usar **PDF firmado (prueba)** para visualizar el documento actual con firma visual, revisor, fecha y huella SHA-256 de los datos guardados. El navegador permite **Imprimir > Guardar como PDF**. No se genera todavía un PDF firmado en servidor.

La firma es **visual**, no una firma electrónica avanzada ni un sello criptográfico verificable por terceros. La hash del contenido se comprueba en la API para detectar cambios en los datos después de aprobar. No usar para documentos oficiales. Las aprobaciones **solo funcionan conectado y con sesión válida**; el guardado de borradores sigue siendo local.

## Funciones pendientes

Plantilla PDF oficial del laboratorio, generación del PDF definitivo, firma electrónica avanzada si llegase a requerirse, y envío masivo por SMTP. **No están implementados**. La impresión existente permanece como **copia de prueba**.

